"""MT5 terminal bridge.

Talks to the MetaTrader 5 terminal on this Windows machine and to the Laravel API.

The prop account number in the desk must be the MT5 login, so balance and positions land on that session.

Environment:
  MT5_TERMINAL_PATH   optional path to terminal64.exe
  MT5_LOGIN           optional login when the terminal is not already signed in
  MT5_PASSWORD
  MT5_SERVER
  MT5_FIRM_NAME       optional, matched with the prop account firm
  MT5_SYMBOLS         comma-separated symbols, default EURUSD,GBPUSD,USDJPY,XAUUSD
  MT5_POLL_SECONDS    account sync, default 1
  MT5_QUOTE_SECONDS   in-memory tick poll, default 0.02
  MT5_HISTORY_DAYS    default 3
  LARAVEL_API_URL     for example http://all.agricart.pk.test
  LARAVEL_API_TOKEN   Sanctum token for /api/v1/trading/*
  BRIDGE_TOKEN        shared secret the website sends to this process
  BRIDGE_HOST         default 127.0.0.1
  BRIDGE_PORT         default 8765

Run:
  py -m pip install -r requirements.txt
  py mt5_bridge.py
"""

from __future__ import annotations

import json
import os
import queue
import subprocess
import threading
import time
import urllib.error
import urllib.parse
import urllib.request
from datetime import datetime, timedelta
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer

try:
    import MetaTrader5 as mt5
except ImportError:  # pragma: no cover - exercised only on a machine without the package
    mt5 = None

HOST = os.environ.get("BRIDGE_HOST", "127.0.0.1")
PORT = int(os.environ.get("BRIDGE_PORT", "8765"))
TOKEN = os.environ.get("BRIDGE_TOKEN", "")
LARAVEL = os.environ.get("LARAVEL_API_URL", "").rstrip("/")
LARAVEL_TOKEN = os.environ.get("LARAVEL_API_TOKEN", "")
def env_float(name: str, default: float, low: float, high: float) -> float:
    try:
        value = float(os.environ.get(name, str(default)))
    except ValueError:
        value = default

    return min(high, max(low, value))


POLL_SECONDS = env_float("MT5_POLL_SECONDS", 1, 0.5, 5)
QUOTE_SECONDS = env_float("MT5_QUOTE_SECONDS", 0.02, 0.01, 1)
HISTORY_DAYS = max(1, int(os.environ.get("MT5_HISTORY_DAYS", "3")))
FIRM = os.environ.get("MT5_FIRM_NAME", "")
MAGIC = 510510
REQUESTS: queue.Queue = queue.Queue()
PUBLISH: queue.Queue = queue.Queue(1)
QUOTES: dict[str, dict] = {}
QUOTES_LOCK = threading.Lock()
RANGES: dict[str, tuple[float, float]] = {}
SPECS: dict[str, dict] = {}
ACCOUNT: dict = {}
CATALOG: dict = {"ok": True, "categories": [], "symbols": []}
PENDING: list[dict] = []
BOARDS: dict[str, dict] = {}
CUSTOM: list[str] = []
WATCHED: list[str] = []
WATCHED_AT = 0.0
SYMBOLS = [part.strip() for part in os.environ.get("MT5_SYMBOLS", "EURUSD,GBPUSD,USDJPY,XAUUSD").split(",") if part.strip()]
WATCH_LIMIT = 10
FRAMES = {
    "1m": "TIMEFRAME_M1",
    "5m": "TIMEFRAME_M5",
    "15m": "TIMEFRAME_M15",
    "30m": "TIMEFRAME_M30",
    "1h": "TIMEFRAME_H1",
    "4h": "TIMEFRAME_H4",
    "d": "TIMEFRAME_D1",
    "w": "TIMEFRAME_W1",
}
PENDING_TYPES = {
    "buy_limit": "ORDER_TYPE_BUY_LIMIT",
    "sell_limit": "ORDER_TYPE_SELL_LIMIT",
    "buy_stop": "ORDER_TYPE_BUY_STOP",
    "sell_stop": "ORDER_TYPE_SELL_STOP",
}
CATEGORY_NAMES = {
    "forex": "Forex",
    "metals": "Metals",
    "metal": "Metals",
    "crypto": "Crypto",
    "cryptocurrencies": "Crypto",
    "energies": "Commodities",
    "energy": "Commodities",
    "commodities": "Commodities",
    "commodity": "Commodities",
    "indices": "Indices",
    "indexes": "Indices",
    "index": "Indices",
    "stocks": "Stocks",
    "stock": "Stocks",
}


def authorized(header: str) -> bool:
    if TOKEN == "":
        return False

    expected = f"Bearer {TOKEN}"
    if len(header) != len(expected):
        return False

    mismatch = 0
    for left, right in zip(header, expected):
        mismatch |= ord(left) ^ ord(right)

    return mismatch == 0


def terminal_process_running() -> bool:
    """True only when the user already has the MT5 window open.

    mt5.initialize() launches terminal64.exe when it is closed. A closed
    live account must stay closed.
    """
    path = (os.environ.get("MT5_TERMINAL_PATH") or "").strip()
    image = os.path.basename(path) or "terminal64.exe"

    try:
        completed = subprocess.run(
            ["tasklist", "/FI", f"IMAGENAME eq {image}", "/NH"],
            capture_output=True,
            text=True,
            timeout=5,
            check=False,
            creationflags=0x08000000,
        )
    except (OSError, subprocess.SubprocessError, ValueError):
        return False

    listing = (completed.stdout or "").lower()

    return image.lower() in listing and "no tasks are running" not in listing


def ensure_terminal() -> bool:
    if mt5 is None:
        return False

    try:
        connected = mt5.terminal_info() is not None and mt5.account_info() is not None
    except Exception:
        connected = False

    if connected:
        return True

    if not terminal_process_running():
        try:
            mt5.shutdown()
        except Exception:
            pass

        return False

    path = (os.environ.get("MT5_TERMINAL_PATH") or "").strip()
    attached = mt5.initialize(path) if path else mt5.initialize()
    if not attached:
        return False

    login = os.environ.get("MT5_LOGIN", "")
    if login:
        return bool(mt5.login(int(login), password=os.environ.get("MT5_PASSWORD", ""), server=os.environ.get("MT5_SERVER", "")))

    return mt5.account_info() is not None


def terminal_identity() -> dict:
    info = mt5.account_info()
    if info is None:
        return {"ok": False, "message": "MT5 terminal is not connected."}

    return {
        "ok": True,
        "login": info.login,
        "balance": info.balance,
        "equity": info.equity,
        "server": getattr(info, "server", "") or "",
        "name": getattr(info, "name", "") or "",
        "company": getattr(info, "company", "") or "",
    }


def sign_in(payload: dict) -> dict:
    raw_login = str(payload.get("login") or "").strip()
    if not raw_login.isdigit():
        return {"ok": False, "message": "Account number is required."}

    login = int(raw_login)
    password = str(payload.get("password") or "")
    server = str(payload.get("server") or "")
    if password or server:
        if not mt5.login(login, password=password, server=server):
            error = mt5.last_error()
            message = error[1] if isinstance(error, tuple) and len(error) > 1 else "MT5 login failed."
            return {"ok": False, "message": str(message)}

    state = terminal_identity()
    if not state.get("ok") or str(state.get("login")) != str(login):
        return {"ok": False, "message": "The open terminal is signed in to a different account."}

    return state


def account_payload() -> dict | None:
    info = mt5.account_info()
    if info is None:
        return None

    day_start = datetime.now().replace(hour=0, minute=0, second=0, microsecond=0)
    deals = mt5.history_deals_get(day_start, datetime.now())
    if deals is None:
        deals = []
    realized_today = 0.0
    for deal in deals:
        if datetime.fromtimestamp(deal.time) < day_start:
            continue
        if deal.entry not in (mt5.DEAL_ENTRY_OUT, mt5.DEAL_ENTRY_OUT_BY, mt5.DEAL_ENTRY_INOUT):
            continue
        realized_today += deal.profit + deal.swap + deal.commission

    payload = {
        "account_number": str(info.login),
        "balance": info.balance,
        "equity": info.equity,
        "day_start_balance": info.balance - realized_today,
        "today_pnl": realized_today + info.profit,
        "floating_pnl": info.profit,
    }
    if FIRM:
        payload["firm_name"] = FIRM

    return payload


def resolve_symbol(name: str) -> str | None:
    for candidate in (name, f"{name}m", f"{name}.", f"{name}.m"):
        if not mt5.symbol_select(candidate, True):
            continue
        tick = mt5.symbol_info_tick(candidate)
        if tick is not None and tick.bid:
            return candidate

    return None


def match_symbol(name: str) -> str | None:
    """Broker names keep their own case, such as XAUUSDm. The desk sends XAUUSDM."""
    cleaned = str(name or "").strip()
    if cleaned == "":
        return None

    resolved = resolve_symbol(cleaned)
    if resolved is not None:
        return resolved

    wanted = cleaned.upper()
    discovered = mt5.symbols_get()
    if discovered is None:
        return None

    for symbol in discovered:
        if symbol.name.upper() != wanted:
            continue
        if not mt5.symbol_select(symbol.name, True):
            continue
        tick = mt5.symbol_info_tick(symbol.name)
        if tick is not None and tick.bid:
            return symbol.name

    return None


def watched_symbols() -> list[str]:
    names: list[str] = []
    for name in SYMBOLS:
        resolved = resolve_symbol(name)
        if resolved and resolved not in names:
            names.append(resolved)

    discovered = mt5.symbols_get()
    if discovered is not None:
        for symbol in discovered:
            if symbol.visible and symbol.name not in names:
                names.append(symbol.name)

    return names[:40]


def active_symbols() -> list[str]:
    global WATCHED_AT
    if CUSTOM:
        return CUSTOM[:WATCH_LIMIT]

    now = time.time()
    if WATCHED and now - WATCHED_AT < 3:
        return WATCHED

    names = watched_symbols()
    WATCHED.clear()
    WATCHED.extend(names)
    WATCHED_AT = now

    return WATCHED


def set_watchlist(payload: dict) -> dict:
    global WATCHED_AT
    requested = payload.get("symbols") if isinstance(payload, dict) else None
    if not isinstance(requested, list):
        return {"ok": False, "message": "Send a list of symbols."}

    names: list[str] = []
    for item in requested:
        resolved = match_symbol(str(item))
        if resolved and resolved not in names:
            names.append(resolved)
        if len(names) >= WATCH_LIMIT:
            break

    CUSTOM.clear()
    CUSTOM.extend(names)
    WATCHED.clear()
    WATCHED_AT = 0.0

    return {"ok": True, "symbols": names, "limit": WATCH_LIMIT}


def category_name(info) -> str:
    path = str(getattr(info, "path", "") or "")
    parts = [part.strip() for part in path.replace("/", "\\").split("\\") if part.strip()]
    head = parts[0] if parts else ""
    mapped = CATEGORY_NAMES.get(head.lower())
    if mapped:
        return mapped
    if head:
        return head

    name = str(getattr(info, "name", "") or "").upper()
    if name.startswith(("XAU", "XAG", "XPT", "XPD")):
        return "Metals"
    if name.startswith(("BTC", "ETH", "LTC", "XRP")):
        return "Crypto"
    if name.startswith(("USOIL", "UKOIL", "XNG", "BRENT")):
        return "Commodities"

    return "Other"


def spec_row(info) -> dict:
    return {
        "symbol": info.name,
        "category": category_name(info),
        "digits": info.digits,
        "spread_points": info.spread,
        "volume_min": info.volume_min,
        "volume_max": info.volume_max,
        "volume_step": info.volume_step,
        "swap_long": info.swap_long,
        "swap_short": info.swap_short,
        "contract_size": info.trade_contract_size,
        "tick_size": info.trade_tick_size or info.point,
        "tick_value": info.trade_tick_value,
        "point": info.point,
    }


def refresh_specs() -> None:
    for symbol in active_symbols():
        info = mt5.symbol_info(symbol)
        if info is None:
            continue
        SPECS[symbol.upper()] = spec_row(info)


def refresh_account() -> None:
    info = mt5.account_info()
    ACCOUNT.clear()
    if info is None:
        ACCOUNT.update({"ok": False, "message": "MT5 terminal is not connected."})
        return

    ACCOUNT.update({
        "ok": True,
        "login": info.login,
        "balance": info.balance,
        "equity": info.equity,
        "margin": info.margin,
        "margin_free": info.margin_free,
        "margin_level": info.margin_level,
        "leverage": info.leverage,
        "currency": getattr(info, "currency", "") or "",
        "company": getattr(info, "company", "") or "",
        "server": getattr(info, "server", "") or "",
        "name": getattr(info, "name", "") or "",
        "profit": info.profit,
    })


def refresh_catalog() -> None:
    discovered = mt5.symbols_get()
    rows = []
    if discovered is not None:
        for info in discovered:
            if info.trade_mode == mt5.SYMBOL_TRADE_MODE_DISABLED:
                continue
            rows.append({
                "symbol": info.name,
                "category": category_name(info),
                "description": getattr(info, "description", "") or "",
            })

    categories = []
    for row in rows:
        if row["category"] not in categories:
            categories.append(row["category"])

    CATALOG.clear()
    CATALOG.update({"ok": True, "categories": categories, "symbols": rows})


def refresh_pending() -> None:
    orders = mt5.orders_get()
    if orders is None:
        orders = []

    labels = {
        mt5.ORDER_TYPE_BUY_LIMIT: "buy_limit",
        mt5.ORDER_TYPE_SELL_LIMIT: "sell_limit",
        mt5.ORDER_TYPE_BUY_STOP: "buy_stop",
        mt5.ORDER_TYPE_SELL_STOP: "sell_stop",
        mt5.ORDER_TYPE_BUY_STOP_LIMIT: "buy_stop_limit",
        mt5.ORDER_TYPE_SELL_STOP_LIMIT: "sell_stop_limit",
    }
    rows = []
    for order in orders:
        rows.append({
            "ticket": order.ticket,
            "symbol": order.symbol,
            "type": labels.get(order.type, str(order.type)),
            "lots": order.volume_current,
            "price": order.price_open,
            "sl": order.sl or None,
            "tp": order.tp or None,
        })

    PENDING.clear()
    PENDING.extend(rows)


def board(payload: dict) -> dict:
    category = str((payload or {}).get("category") or "")
    cached = BOARDS.get(category)
    if cached is not None and time.time() - float(cached.get("at") or 0) < 1:
        return {"ok": True, "category": category, "quotes": cached["quotes"]}

    if not CATALOG.get("symbols"):
        refresh_catalog()

    rows = []
    for item in CATALOG.get("symbols", []):
        if category and item["category"] != category:
            continue
        if len(rows) >= 40:
            break
        info = mt5.symbol_info(item["symbol"])
        tick = mt5.symbol_info_tick(item["symbol"])
        if info is None or tick is None or not tick.bid:
            rows.append({"symbol": item["symbol"], "category": item["category"], "bid": None, "ask": None})
            continue
        spec = spec_row(info)
        SPECS[item["symbol"].upper()] = spec
        rows.append({
            **spec,
            "bid": tick.bid,
            "ask": tick.ask,
            "spread": round(tick.ask - tick.bid, info.digits),
        })

    BOARDS[category] = {"at": time.time(), "quotes": rows}

    return {"ok": True, "category": category, "quotes": rows}


def candle_rows(payload: dict) -> dict:
    symbol = match_symbol(str((payload or {}).get("symbol") or ""))
    frame = str((payload or {}).get("timeframe") or "").lower()
    if symbol is None:
        return {"ok": False, "message": "This symbol is not on the MT5 terminal."}
    if frame not in FRAMES:
        return {"ok": False, "message": "Choose 1m, 5m, 15m, 30m, 1h, 4h, D, or W."}

    count = (payload or {}).get("count") or 80
    try:
        count = int(count)
    except (TypeError, ValueError):
        count = 80
    count = min(200, max(10, count))
    timeframe = getattr(mt5, FRAMES[frame])
    rates = mt5.copy_rates_from_pos(symbol, timeframe, 0, count)
    if rates is None:
        return {"ok": False, "message": "MT5 did not return candles for this symbol."}

    candles = []
    for bar in rates:
        candles.append({
            "time": datetime.fromtimestamp(int(bar["time"])).isoformat(),
            "open": float(bar["open"]),
            "high": float(bar["high"]),
            "low": float(bar["low"]),
            "close": float(bar["close"]),
        })

    return {"ok": True, "symbol": symbol, "timeframe": frame, "candles": candles}


def history_rows(payload: dict) -> dict:
    try:
        days = int((payload or {}).get("days") or 30)
    except (TypeError, ValueError):
        days = 30
    days = min(365, max(1, days))
    start = datetime.now() - timedelta(days=days)
    deals = mt5.history_deals_get(start, datetime.now())
    if deals is None:
        deals = []

    rows = []
    for deal in deals:
        if deal.entry not in (mt5.DEAL_ENTRY_OUT, mt5.DEAL_ENTRY_OUT_BY):
            continue
        rows.append({
            "position_ticket": deal.position_id,
            "symbol": deal.symbol,
            "side": "buy" if deal.type == mt5.DEAL_TYPE_BUY else "sell",
            "lots": deal.volume,
            "price": deal.price,
            "profit": deal.profit + deal.swap + deal.commission,
            "time": datetime.fromtimestamp(deal.time).isoformat(),
        })

    return {"ok": True, "days": days, "deals": rows}


def refresh_quotes() -> None:
    now_ms = int(time.time() * 1000)
    with QUOTES_LOCK:
        previous = {key: dict(value) for key, value in QUOTES.items()}

    rows: dict[str, dict] = {}
    for symbol in active_symbols():
        tick = mt5.symbol_info_tick(symbol)
        if tick is None or not tick.bid:
            continue

        key = symbol.upper()
        stamp = int(tick.time_msc)
        prior = previous.get(key)
        received = now_ms
        if prior is not None and prior.get("time_msc") == stamp:
            received = int(prior.get("received_ms") or now_ms)

        high, low = RANGES.get(key, (None, None))
        spec = SPECS.get(key, {})
        digits = int(spec.get("digits") or 5)
        rows[key] = {
            "symbol": symbol,
            "bid": tick.bid,
            "ask": tick.ask,
            "high": high,
            "low": low,
            "spread": round(tick.ask - tick.bid, digits),
            "category": spec.get("category"),
            "volume_min": spec.get("volume_min"),
            "volume_max": spec.get("volume_max"),
            "volume_step": spec.get("volume_step"),
            "swap_long": spec.get("swap_long"),
            "swap_short": spec.get("swap_short"),
            "contract_size": spec.get("contract_size"),
            "tick_size": spec.get("tick_size"),
            "tick_value": spec.get("tick_value"),
            "time_msc": stamp,
            "received_ms": received,
            "age_ms": max(0, now_ms - received),
        }

    with QUOTES_LOCK:
        QUOTES.clear()
        QUOTES.update(rows)


def refresh_ranges() -> None:
    for symbol in active_symbols():
        rates = mt5.copy_rates_from_pos(symbol, mt5.TIMEFRAME_D1, 0, 1)
        if rates is None or len(rates) == 0:
            continue
        bar = rates[0]
        RANGES[symbol.upper()] = (float(bar["high"]), float(bar["low"]))


def quote_payload() -> dict:
    with QUOTES_LOCK:
        rows = list(QUOTES.values())

    return {"ok": True, "quotes": rows}


def cached_ticks() -> list[dict]:
    with QUOTES_LOCK:
        return [
            {"symbol": row["symbol"], "bid": row["bid"], "ask": row["ask"]}
            for row in QUOTES.values()
        ]


def ticks() -> list[dict]:
    refresh_quotes()

    return cached_ticks()


def client_order_id(comment: str) -> int | None:
    marker = "web-"
    if marker not in (comment or ""):
        return None

    digits = comment.split(marker, 1)[1].split()[0]
    return int(digits) if digits.isdigit() else None


def positions() -> list[dict]:
    rows = []
    open_positions = mt5.positions_get()
    if open_positions is None:
        open_positions = []
    for position in open_positions:
        rows.append({
            "ticket": position.ticket,
            "symbol": position.symbol,
            "side": "buy" if position.type == mt5.POSITION_TYPE_BUY else "sell",
            "lots": position.volume,
            "price_open": position.price_open,
            "sl": position.sl or None,
            "tp": position.tp or None,
            "profit": position.profit + position.swap,
            "client_order_id": client_order_id(position.comment),
            "opened_at": datetime.fromtimestamp(position.time).isoformat(),
        })

    return rows


def closing_deals() -> list[dict]:
    start = datetime.now() - timedelta(days=HISTORY_DAYS)
    rows = []
    deals = mt5.history_deals_get(start, datetime.now())
    if deals is None:
        deals = []
    for deal in deals:
        if deal.entry not in (mt5.DEAL_ENTRY_OUT, mt5.DEAL_ENTRY_OUT_BY):
            continue
        rows.append({
            "position_ticket": deal.position_id,
            "symbol": deal.symbol,
            "side": "buy" if deal.type == mt5.DEAL_TYPE_BUY else "sell",
            "lots": deal.volume,
            "price": deal.price,
            "profit": deal.profit + deal.swap + deal.commission,
            "entry": "out_by" if deal.entry == mt5.DEAL_ENTRY_OUT_BY else "out",
            "time": datetime.fromtimestamp(deal.time).isoformat(),
        })

    return rows


def filling_mode(symbol: str) -> int:
    info = mt5.symbol_info(symbol)
    mode = 0 if info is None else info.filling_mode
    if mode & 1:
        return mt5.ORDER_FILLING_FOK
    if mode & 2:
        return mt5.ORDER_FILLING_IOC

    return mt5.ORDER_FILLING_RETURN


def prepare_volume(symbol: str, lots: float) -> tuple[float | None, str]:
    info = mt5.symbol_info(symbol)
    if info is None and mt5.symbol_select(symbol, True):
        info = mt5.symbol_info(symbol)
    if info is None:
        return None, "This symbol is not on the MT5 terminal."

    step = info.volume_step or 0.01
    if step <= 0:
        step = 0.01

    step_text = f"{step:.8f}".rstrip("0").rstrip(".")
    decimals = len(step_text.split(".")[1]) if "." in step_text else 0
    volume = round(round(lots / step) * step, decimals)
    if volume < info.volume_min or volume > info.volume_max:
        return None, (
            f"Lot size for {symbol} must be between {info.volume_min:g} and {info.volume_max:g}, "
            f"in steps of {step:g}."
        )

    return volume, ""


def protective_prices(symbol: str, is_buy: bool, volume: float, price: float) -> tuple[float, float] | None:
    """Stop and target at 1 percent of current equity, widened to the broker minimum."""
    info = mt5.symbol_info(symbol)
    account = mt5.account_info()
    if info is None or account is None or volume <= 0 or price <= 0:
        return None

    tick_size = info.trade_tick_size or info.point or 0
    tick_value = info.trade_tick_value or 0
    if tick_size <= 0 or tick_value <= 0 or account.equity <= 0:
        return None

    money_per_unit = (tick_value / tick_size) * volume
    if money_per_unit <= 0:
        return None

    distance = (float(account.equity) * 0.01) / money_per_unit
    minimum = (info.trade_stops_level or 0) * (info.point or 0)
    if minimum and distance < minimum:
        distance = minimum

    digits = info.digits
    if is_buy:
        return round(price - distance, digits), round(price + distance, digits)

    return round(price + distance, digits), round(price - distance, digits)


def find_web_position(order_id: int, symbol: str):
    marker = f"web-{order_id}"
    positions = mt5.positions_get(symbol=symbol)
    if positions is None:
        return None
    for position in positions:
        if marker in (position.comment or ""):
            return position

    return None


def send_order(order: dict) -> dict:
    symbol = match_symbol(str(order.get("symbol") or ""))
    if symbol is None:
        return {"ok": False, "message": "This symbol is not on the MT5 terminal."}

    side = str(order["side"])
    order_id = int(order["client_order_id"])
    existing = find_web_position(order_id, symbol)
    if existing is not None:
        return {"ok": True, "ticket": existing.ticket, "price": existing.price_open}

    volume, volume_error = prepare_volume(symbol, float(order["lots"]))
    if volume is None:
        return {"ok": False, "message": volume_error}

    tick = mt5.symbol_info_tick(symbol)
    if tick is None:
        return {"ok": False, "message": "No live price for this symbol."}

    info = mt5.symbol_info(symbol)
    digits = 5 if info is None else info.digits
    is_buy = side == "buy"
    price = tick.ask if is_buy else tick.bid
    stop_loss = round(float(order["stop_loss"]), digits) if order.get("stop_loss") else 0.0
    take_profit = round(float(order["take_profit"]), digits) if order.get("take_profit") else 0.0
    if not stop_loss and not take_profit and order.get("risk_percent"):
        protective = protective_prices(symbol, is_buy, volume, price)
        if protective is not None:
            stop_loss, take_profit = protective
    request = {
        "action": mt5.TRADE_ACTION_DEAL,
        "symbol": symbol,
        "volume": volume,
        "type": mt5.ORDER_TYPE_BUY if is_buy else mt5.ORDER_TYPE_SELL,
        "price": price,
        "sl": stop_loss,
        "tp": take_profit,
        "deviation": 20,
        "magic": MAGIC,
        "comment": f"web-{order_id}",
        "type_time": mt5.ORDER_TIME_GTC,
        "type_filling": filling_mode(symbol),
    }
    result = mt5.order_send(request)
    if result is None or result.retcode != mt5.TRADE_RETCODE_DONE:
        comment = "" if result is None else result.comment
        return {"ok": False, "message": comment or "MT5 did not accept the order."}

    position = find_web_position(order_id, symbol)
    ticket = position.ticket if position is not None else result.order

    return {
        "ok": True,
        "ticket": ticket,
        "price": result.price,
        "stop_loss": stop_loss or None,
        "take_profit": take_profit or None,
    }


def close_order(order: dict) -> dict:
    symbol = match_symbol(str(order.get("symbol") or ""))
    if symbol is None:
        return {"ok": False, "message": "This symbol is not on the MT5 terminal."}

    ticket = int(order["ticket"])
    open_positions = mt5.positions_get(ticket=ticket)
    position = open_positions[0] if open_positions else None
    requested = float(order["lots"])
    if position is not None and requested > float(position.volume):
        requested = float(position.volume)
    volume, volume_error = prepare_volume(symbol, requested)
    tick = mt5.symbol_info_tick(symbol)
    if volume is None or tick is None:
        return {"ok": False, "message": volume_error or "The position cannot be closed at the current price."}

    is_buy = str(order["side"]) == "buy"
    request = {
        "action": mt5.TRADE_ACTION_DEAL,
        "symbol": symbol,
        "position": ticket,
        "volume": volume,
        "type": mt5.ORDER_TYPE_SELL if is_buy else mt5.ORDER_TYPE_BUY,
        "price": tick.bid if is_buy else tick.ask,
        "deviation": 20,
        "magic": MAGIC,
        "comment": f"close-{order['client_order_id']}",
        "type_time": mt5.ORDER_TIME_GTC,
        "type_filling": filling_mode(symbol),
    }
    result = mt5.order_send(request)
    if result is None or result.retcode != mt5.TRADE_RETCODE_DONE:
        comment = "" if result is None else result.comment
        return {"ok": False, "message": comment or "MT5 did not close the position."}

    profit = 0.0
    price = result.price
    deals = mt5.history_deals_get(position=ticket)
    if deals:
        deal = deals[-1]
        price = deal.price
        profit = deal.profit + deal.swap + deal.commission

    remaining = mt5.positions_get(ticket=ticket)
    lots_left = float(remaining[0].volume) if remaining else 0.0

    return {"ok": True, "price": price, "profit": profit, "lots_left": lots_left}


def place_pending(order: dict) -> dict:
    symbol = match_symbol(str(order.get("symbol") or ""))
    kind = str(order.get("type") or "")
    if symbol is None:
        return {"ok": False, "message": "This symbol is not on the MT5 terminal."}
    if kind not in PENDING_TYPES:
        return {"ok": False, "message": "Choose a buy or sell limit or stop."}

    volume, volume_error = prepare_volume(symbol, float(order.get("lots") or 0))
    if volume is None:
        return {"ok": False, "message": volume_error}

    info = mt5.symbol_info(symbol)
    digits = 5 if info is None else info.digits
    try:
        price = round(float(order.get("price")), digits)
    except (TypeError, ValueError):
        return {"ok": False, "message": "Enter the pending order price."}
    if price <= 0:
        return {"ok": False, "message": "Enter the pending order price."}

    stop_loss = round(float(order["sl"]), digits) if order.get("sl") else 0.0
    take_profit = round(float(order["tp"]), digits) if order.get("tp") else 0.0
    request = {
        "action": mt5.TRADE_ACTION_PENDING,
        "symbol": symbol,
        "volume": volume,
        "type": getattr(mt5, PENDING_TYPES[kind]),
        "price": price,
        "sl": stop_loss,
        "tp": take_profit,
        "deviation": 20,
        "magic": MAGIC,
        "comment": "web-pending",
        "type_time": mt5.ORDER_TIME_GTC,
        "type_filling": filling_mode(symbol),
    }
    result = mt5.order_send(request)
    if result is None or result.retcode not in (mt5.TRADE_RETCODE_DONE, mt5.TRADE_RETCODE_PLACED):
        comment = "" if result is None else result.comment
        return {"ok": False, "message": comment or "MT5 did not place the pending order."}

    refresh_pending()

    return {"ok": True, "ticket": result.order, "price": price}


def cancel_pending(order: dict) -> dict:
    try:
        ticket = int(order.get("ticket"))
    except (TypeError, ValueError):
        return {"ok": False, "message": "That pending order has no ticket."}

    request = {
        "action": mt5.TRADE_ACTION_REMOVE,
        "order": ticket,
    }
    result = mt5.order_send(request)
    if result is None or result.retcode != mt5.TRADE_RETCODE_DONE:
        comment = "" if result is None else result.comment
        return {"ok": False, "message": comment or "MT5 did not cancel the pending order."}

    refresh_pending()

    return {"ok": True, "ticket": ticket}


def modify_position(order: dict) -> dict:
    try:
        ticket = int(order.get("ticket"))
    except (TypeError, ValueError):
        return {"ok": False, "message": "That position has no ticket."}

    found = mt5.positions_get(ticket=ticket)
    if not found:
        return {"ok": False, "message": "That position is not open."}

    position = found[0]
    info = mt5.symbol_info(position.symbol)
    digits = 5 if info is None else info.digits
    stop_loss = round(float(order["sl"]), digits) if order.get("sl") else 0.0
    take_profit = round(float(order["tp"]), digits) if order.get("tp") else 0.0
    request = {
        "action": mt5.TRADE_ACTION_SLTP,
        "symbol": position.symbol,
        "position": ticket,
        "sl": stop_loss,
        "tp": take_profit,
    }
    result = mt5.order_send(request)
    if result is None or result.retcode != mt5.TRADE_RETCODE_DONE:
        comment = "" if result is None else result.comment
        return {"ok": False, "message": comment or "MT5 did not change the stop or target."}

    return {"ok": True, "ticket": ticket, "stop_loss": stop_loss or None, "take_profit": take_profit or None}


def laravel_json(method: str, path: str, payload: dict | None = None) -> dict:
    if LARAVEL == "" or LARAVEL_TOKEN == "":
        return {}

    data = None if payload is None else json.dumps(payload).encode()
    request = urllib.request.Request(
        LARAVEL + path,
        data=data,
        headers={
            "Authorization": f"Bearer {LARAVEL_TOKEN}",
            "Accept": "application/json",
            "Content-Type": "application/json",
        },
        method=method,
    )
    with urllib.request.urlopen(request, timeout=8) as response:
        raw = response.read().decode() or "{}"

    return json.loads(raw)


def enqueue_snapshot(payload: dict) -> None:
    try:
        PUBLISH.put_nowait(payload)
        return
    except queue.Full:
        pass

    try:
        PUBLISH.get_nowait()
    except queue.Empty:
        pass

    try:
        PUBLISH.put_nowait(payload)
    except queue.Full:
        pass


def push_snapshot() -> None:
    account = account_payload()
    if account is None:
        return

    enqueue_snapshot({
        "account": account,
        "ticks": cached_ticks(),
        "positions": positions(),
        "deals": closing_deals(),
    })


def publish_loop() -> None:
    """Laravel HTTP stays off the MetaTrader thread so an order is not waiting on it."""
    while True:
        payload = PUBLISH.get()
        try:
            laravel_json("POST", "/api/v1/trading/mt5/sync", payload)
            print("mt5 bridge: snapshot sent", flush=True)
        except Exception as error:
            print(f"mt5 bridge publish failed: {error}", flush=True)


def drain_pending() -> None:
    info = mt5.account_info()
    if info is None:
        return

    query = urllib.parse.urlencode({"account_number": str(info.login)})
    body = laravel_json("GET", f"/api/v1/trading/mt5/orders/pending?{query}")
    for order in body.get("data", []):
        result = send_order(order)
        status = "filled" if result.get("ok") else "rejected"
        laravel_json("POST", f"/api/v1/trading/mt5/orders/{order['client_order_id']}/execution", {
            "status": status,
            "ticket": result.get("ticket"),
            "price": result.get("price"),
            "message": result.get("message"),
        })


def dispatch_job(job: dict) -> None:
    if job["kind"] == "health":
        job["reply"].put((200, terminal_identity()))
        return

    if job["kind"] == "login":
        job["reply"].put((200, sign_in(job["order"] or {})))
        return

    if job["kind"] == "order":
        job["reply"].put((200, send_order(job["order"])))
        return

    if job["kind"] == "close":
        job["reply"].put((200, close_order(job["order"])))
        return

    if job["kind"] == "watchlist":
        job["reply"].put((200, set_watchlist(job["order"] or {})))
        return

    if job["kind"] == "pending":
        job["reply"].put((200, place_pending(job["order"] or {})))
        return

    if job["kind"] == "cancel":
        job["reply"].put((200, cancel_pending(job["order"] or {})))
        return

    if job["kind"] == "modify":
        job["reply"].put((200, modify_position(job["order"] or {})))
        return

    if job["kind"] == "rates":
        job["reply"].put((200, candle_rows(job["order"] or {})))
        return

    if job["kind"] == "history":
        job["reply"].put((200, history_rows(job["order"] or {})))
        return

    if job["kind"] == "board":
        job["reply"].put((200, board(job["order"] or {})))
        return

    if job["kind"] == "catalog":
        refresh_catalog()
        job["reply"].put((200, dict(CATALOG)))
        return

    if job["kind"] == "account":
        refresh_account()
        job["reply"].put((200, dict(ACCOUNT)))
        return

    job["reply"].put((404, {"ok": False, "message": "Not found."}))


def bridge_loop() -> None:
    """All MetaTrader calls stay on this thread. Orders run before quote polls and account sync."""
    next_sync = 0.0
    next_quotes = 0.0
    next_ranges = 0.0
    while True:
        job = None
        try:
            try:
                job = REQUESTS.get_nowait()
            except queue.Empty:
                job = None

            if not ensure_terminal():
                if job is not None:
                    job["reply"].put((503, {"ok": False, "message": "MT5 terminal is not connected."}))
                time.sleep(1)
                continue

            if job is not None:
                dispatch_job(job)
                continue

            now = time.time()
            did_work = False
            if now >= next_quotes:
                refresh_quotes()
                next_quotes = time.time() + QUOTE_SECONDS
                did_work = True

            now = time.time()
            if now >= next_ranges and REQUESTS.empty():
                refresh_ranges()
                next_ranges = time.time() + 1
                did_work = True

            now = time.time()
            if now >= next_sync and REQUESTS.empty():
                refresh_account()
                refresh_specs()
                refresh_pending()
                if not CATALOG.get("symbols"):
                    refresh_catalog()
                push_snapshot()
                drain_pending()
                next_sync = time.time() + POLL_SECONDS
                did_work = True

            if did_work:
                continue

            timeout = max(0.005, min(next_quotes, next_sync) - time.time())
            try:
                job = REQUESTS.get(timeout=timeout)
            except queue.Empty:
                continue

            dispatch_job(job)
        except Exception as error:
            print(f"mt5 bridge sync failed: {error}", flush=True)
            if job is not None:
                try:
                    job["reply"].put((500, {"ok": False, "message": str(error)}))
                except Exception:
                    pass


def submit(kind: str, order: dict | None = None) -> tuple[int, dict]:
    reply: queue.Queue = queue.Queue(1)
    REQUESTS.put({"kind": kind, "order": order, "reply": reply})
    return reply.get(timeout=30)


class BridgeHandler(BaseHTTPRequestHandler):
    def do_OPTIONS(self) -> None:  # noqa: N802
        self.respond(204, {})

    def do_GET(self) -> None:  # noqa: N802
        parsed = urllib.parse.urlsplit(self.path)
        path = parsed.path
        query = {key: values[-1] for key, values in urllib.parse.parse_qs(parsed.query).items()}
        if path == "/quotes":
            self.respond(200, quote_payload())
            return

        if path == "/account" and ACCOUNT.get("ok"):
            self.respond(200, dict(ACCOUNT))
            return

        if path == "/symbols" and CATALOG.get("symbols"):
            self.respond(200, dict(CATALOG))
            return

        if path == "/pending":
            self.respond(200, {"ok": True, "orders": list(PENDING)})
            return

        kind = {
            "/health": "health",
            "/account": "account",
            "/symbols": "catalog",
            "/board": "board",
            "/rates": "rates",
            "/history": "history",
        }.get(path)
        if kind is None:
            self.respond(404, {"ok": False, "message": "Not found."})
            return

        try:
            status, payload = submit(kind, query or None)
        except queue.Empty:
            self.respond(503, {"ok": False, "message": "MT5 terminal is not connected."})
            return

        self.respond(status, payload)

    def do_POST(self) -> None:  # noqa: N802
        if not authorized(self.headers.get("Authorization", "")):
            self.respond(401, {"ok": False, "message": "Bridge token was refused."})
            return

        length = int(self.headers.get("Content-Length", "0"))
        raw = self.rfile.read(length).decode() if length else "{}"
        try:
            order = json.loads(raw)
        except json.JSONDecodeError:
            self.respond(422, {"ok": False, "message": "Invalid JSON."})
            return

        try:
            if self.path == "/orders":
                status, payload = submit("order", order)
            elif self.path == "/orders/close":
                status, payload = submit("close", order)
            elif self.path == "/orders/pending":
                status, payload = submit("pending", order)
            elif self.path == "/orders/cancel":
                status, payload = submit("cancel", order)
            elif self.path == "/orders/modify":
                status, payload = submit("modify", order)
            elif self.path == "/watchlist":
                status, payload = submit("watchlist", order)
            elif self.path == "/session":
                status, payload = submit("login", order)
            else:
                self.respond(404, {"ok": False, "message": "Not found."})
                return
        except queue.Empty:
            self.respond(503, {"ok": False, "message": "MT5 terminal is not connected."})
            return

        self.respond(status, payload)

    def respond(self, status: int, payload: dict) -> None:
        body = json.dumps(payload).encode()
        self.send_response(status)
        self.send_header("Content-Type", "application/json")
        self.send_header("Cache-Control", "no-store")
        self.send_header("Access-Control-Allow-Origin", "*")
        self.send_header("Access-Control-Allow-Methods", "GET, POST, OPTIONS")
        self.send_header("Access-Control-Allow-Headers", "Authorization, Content-Type, Accept")
        self.send_header("Content-Length", str(len(body)))
        self.end_headers()
        self.wfile.write(body)

    def log_message(self, format: str, *args) -> None:  # noqa: A003
        print(f"mt5 bridge: {self.address_string()} {format % args}", flush=True)


if __name__ == "__main__":
    threading.Thread(target=publish_loop, daemon=True).start()
    threading.Thread(target=bridge_loop, daemon=True).start()
    try:
        server = ThreadingHTTPServer((HOST, PORT), BridgeHandler)
    except OSError as error:
        print(f"mt5 bridge: cannot listen on {HOST}:{PORT}: {error}", flush=True)
        raise SystemExit(1)
    print(
        "mt5 bridge config "
        f"laravel={'yes' if LARAVEL else 'no'} "
        f"token={'yes' if LARAVEL_TOKEN else 'no'} "
        f"terminal={'yes' if os.environ.get('MT5_TERMINAL_PATH') else 'no'} "
        f"quotes={int(QUOTE_SECONDS * 1000)}ms",
        flush=True,
    )
    print(f"mt5 bridge listening on http://{HOST}:{PORT}", flush=True)
    server.serve_forever()
