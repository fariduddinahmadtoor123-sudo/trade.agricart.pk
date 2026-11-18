<x-filament-panels::page>
    @script
        <script>
            window.agricartPlaceQuote = (symbol, side, lots) => $wire.placeQuote(symbol, side, String(lots));
        </script>
    @endscript
    @php
        $accounts = $this->accounts();
        $activeAccount = $this->activeAccount();
        $rule = $activeAccount?->rule;
        $meters = $this->safetyMeters($activeAccount);
    @endphp

    @if ($accounts->isNotEmpty())
    <section class="prop-desk" aria-label="{{ __('trading.nav.prop_accounts') }}">
        <header class="prop-desk__bar">
            <div>
                <p class="prop-desk__eyebrow">{{ __('trading.prop.terminal.eyebrow') }}</p>
                <h2 class="prop-desk__title">{{ __('trading.prop.terminal.title') }}</h2>
            </div>

            @if ($accounts->isEmpty())
                <a class="prop-desk__manage" href="{{ \Modules\Trading\Filament\Clusters\TradingCluster\Resources\PropAccounts\PropAccountResource::getUrl() }}">
                    {{ __('trading.prop.terminal.manage') }}
                </a>
            @else
                <form class="prop-desk__login" wire:submit="login">
                    <label class="prop-desk__field">
                        <span>{{ __('trading.prop.terminal.account') }}</span>
                        <select wire:model="accountId" required>
                            <option value="">{{ __('trading.prop.terminal.choose') }}</option>
                            @foreach ($accounts as $account)
                                <option value="{{ $account->id }}">{{ $account->sessionLabel() }}</option>
                            @endforeach
                        </select>
                    </label>
                    <button type="submit" class="prop-desk__open">{{ __('trading.prop.terminal.login') }}</button>
                    @if ($activeAccount)
                        <button type="button" class="prop-desk__leave" wire:click="logout">{{ __('trading.prop.terminal.logout') }}</button>
                    @endif
                </form>
            @endif
        </header>

        @if ($accounts->isEmpty())
            <p class="prop-desk__empty">{{ __('trading.prop.terminal.none') }}</p>
        @elseif ($activeAccount === null)
            <p class="prop-desk__empty">{{ __('trading.prop.terminal.empty') }}</p>
        @else
            <div class="prop-desk__session">
                <div class="prop-desk__identity">
                    <p class="prop-desk__firm">{{ $activeAccount->firm_name }}</p>
                    <p class="prop-desk__account">{{ $activeAccount->account_number }}</p>
                    <ul class="prop-desk__tags">
                        <li>{{ $activeAccount->platform->getLabel() }}</li>
                        @if ($activeAccount->model_type)
                            <li>{{ $activeAccount->model_type->getLabel() }}</li>
                            <li>{{ __('trading.prop.terminal.phase', ['phase' => $activeAccount->activePhase()]) }}</li>
                        @endif
                        <li>{{ $activeAccount->status->getLabel() }}</li>
                        <li>{{ $activeAccount->risk_category->getLabel() }}</li>
                        <li>{{ number_format((float) $activeAccount->initial_balance, 2) }}</li>
                        @if ($rule)
                            <li>{{ $rule->source->getLabel() }}</li>
                        @endif
                    </ul>
                </div>

                @if ($rule === null)
                    <p class="prop-desk__empty">{{ __('trading.prop.terminal.no_rules') }}</p>
                @else
                    <div class="prop-desk__limits" wire:poll.5s>
                        <p class="prop-desk__note">{{ __('trading.prop.terminal.limits_note') }}</p>
                        <div class="prop-desk__meters">
                            @foreach ($meters as $meter)
                                <article class="prop-meter prop-meter--{{ $meter['tone'] }}" wire:key="prop-meter-{{ $meter['key'] }}">
                                    <div class="prop-meter__top">
                                        <span>{{ $meter['label'] }}</span>
                                        <strong>{{ $meter['display'] }}</strong>
                                    </div>
                                    <div
                                        class="prop-meter__track"
                                        role="meter"
                                        aria-label="{{ $meter['label'] }}"
                                        aria-valuemin="0"
                                        aria-valuemax="100"
                                        aria-valuenow="{{ number_format($meter['width'], 2, '.', '') }}"
                                    >
                                        <span class="prop-meter__fill" style="width: {{ $meter['width'] }}%"></span>
                                    </div>
                                    <p class="prop-meter__meta">{{ $meter['meta'] }}</p>
                                    @if ($meter['breach'])
                                        <p class="prop-meter__breach">{{ $meter['breach'] }}</p>
                                    @endif
                                </article>
                            @endforeach
                        </div>
                        <form class="prop-desk__risk" wire:submit="checkRisk">
                            <label class="prop-desk__field">
                                <span>{{ __('trading.prop.terminal.risk_override') }}</span>
                                <input type="number" wire:model="riskPercent" min="0.01" max="100" step="0.01" required>
                            </label>
                            <label class="prop-desk__check">
                                <input type="checkbox" wire:model="stopPlaced">
                                <span>{{ __('trading.prop.terminal.stop_placed') }}</span>
                            </label>
                            <button type="submit" class="prop-desk__open">{{ __('trading.prop.terminal.check_risk') }}</button>
                        </form>
                        @if ($riskVerdict)
                            <div class="prop-risk prop-risk--{{ $riskVerdict }}">
                                @foreach ($riskMessages as $message)
                                    <p>{{ $message }}</p>
                                @endforeach
                            </div>
                        @endif
                        <dl class="prop-desk__facts">
                            <div>
                                <dt>{{ __('trading.prop.terminal.min_days') }}</dt>
                                <dd>{{ $rule->minimum_trading_days }}</dd>
                            </div>
                            <div>
                                <dt>{{ __('trading.prop.terminal.leverage') }}</dt>
                                <dd>{{ $rule->leverage }}</dd>
                            </div>
                            <div>
                                <dt>{{ __('trading.prop.terminal.stop_loss') }}</dt>
                                <dd>{{ $rule->stop_loss_mode->getLabel() }}</dd>
                            </div>
                        </dl>
                        @if (filled($rule->stop_loss_notes))
                            <p class="prop-desk__notes">{{ $rule->stop_loss_notes }}</p>
                        @endif
                    </div>
                @endif
            </div>
        @endif
    </section>
    @endif

    @php($desk = $this->desk())
    @php($live = $this->liveAccount())

    <div
        class="term-window"
        x-data="{ open: true, maximized: false }"
        x-bind:class="{ 'is-max': open && maximized, 'is-min': ! open }"
    >
        <header class="term-window__bar">
            <strong class="term-window__title">{{ __('trading.nav.mt5_terminal') }}</strong>
            <div class="term-window__controls">
                <button type="button" class="term-window__btn" x-show="open" x-on:click="open = false; maximized = false" aria-label="{{ __('trading.page_info.minimize') }}" title="{{ __('trading.page_info.minimize') }}">–</button>
                <button type="button" class="term-window__btn" x-on:click="open ? maximized = !maximized : open = true" x-bind:aria-label="maximized ? '{{ __('trading.page_info.restore') }}' : '{{ __('trading.page_info.maximize') }}'" x-bind:title="maximized ? '{{ __('trading.page_info.restore') }}' : '{{ __('trading.page_info.maximize') }}'">
                    <span x-show="!maximized">□</span>
                    <span x-show="maximized" x-cloak>❐</span>
                </button>
            </div>
        </header>
    <section class="term-desk" wire:poll.2s x-show="open" aria-label="{{ __('trading.nav.mt5_terminal') }}">
        @php($strip = $this->accountStrip())
        <div class="term-desk__line">
            <form class="term-desk__connect" wire:submit="connectMt5">
                <label>
                    <span>{{ __('trading.terminal.mt5_login') }}</span>
                    <input class="term-desk__login" wire:model="mt5Login" inputmode="numeric" autocomplete="username" required>
                </label>
                <label>
                    <span>{{ __('trading.terminal.mt5_password') }}</span>
                    <span class="term-desk__secret">
                        <input class="term-desk__password" type="password" data-mt5-password wire:model="mt5Password" autocomplete="current-password">
                        <button type="button" class="term-desk__eye" data-password-toggle aria-label="{{ __('trading.terminal.show_password') }}" title="{{ __('trading.terminal.show_password') }}">◉</button>
                    </span>
                </label>
                <label>
                    <span>{{ __('trading.terminal.mt5_server') }}</span>
                    <input wire:model="mt5Server" autocomplete="off">
                </label>
                <label class="term-desk__remember">
                    <input type="checkbox" wire:model.live="rememberMt5">
                    <span>{{ __('trading.terminal.remember_login') }}</span>
                </label>
                <div class="term-desk__connect-actions">
                    <button type="submit">{{ __('trading.terminal.mt5_connect') }}</button>
                    @if ($live)
                        <button type="button" wire:click="disconnectMt5">{{ __('trading.terminal.mt5_disconnect') }}</button>
                    @endif
                </div>
            </form>
            @if ($mt5Status)
                <p class="term-desk__note term-desk__status">{{ $mt5Status }}</p>
            @endif
            @if (! $desk['summary'] && $desk['session'])
                <p class="term-desk__empty term-desk__status">{{ __('trading.terminal.waiting_account') }}</p>
            @elseif (! $desk['summary'])
                <p class="term-desk__empty term-desk__status">{{ __('trading.terminal.need_session') }}</p>
            @endif
            <dl class="term-desk__stats" data-term-account>
                <div>
                    <dt>{{ __('trading.terminal.leverage') }}</dt>
                    <dd data-account="leverage">{{ isset($strip['leverage']) ? '1:'.$strip['leverage'] : '—' }}</dd>
                </div>
                <div>
                    <dt>{{ __('trading.terminal.currency') }}</dt>
                    <dd data-account="currency">{{ $strip['currency'] ?? '—' }}</dd>
                </div>
                <div>
                    <dt>{{ __('trading.terminal.broker') }}</dt>
                    <dd data-account="company">{{ $strip['company'] ?? '—' }}</dd>
                </div>
            </dl>
        </div>
        <script>
            (() => {
                if (window.agricartPasswordToggle) {
                    return;
                }

                window.agricartPasswordToggle = true;
                window.agricartShowMt5Password = false;

                const applyPasswordVisibility = () => {
                    const input = document.querySelector('[data-mt5-password]');

                    if (!input) {
                        return;
                    }

                    input.type = window.agricartShowMt5Password ? 'text' : 'password';
                };

                document.addEventListener('click', (event) => {
                    const button = event.target.closest('[data-password-toggle]');

                    if (!button) {
                        return;
                    }

                    event.preventDefault();
                    window.agricartShowMt5Password = !window.agricartShowMt5Password;
                    applyPasswordVisibility();
                });

                const watchMorph = () => {
                    if (!window.Livewire || window.agricartPasswordMorph) {
                        return;
                    }

                    window.agricartPasswordMorph = true;
                    Livewire.hook('morph.updated', applyPasswordVisibility);
                };

                document.addEventListener('livewire:init', watchMorph);
                watchMorph();
            })();
        </script>

        <div class="term-desk__body">
            <section class="term-desk__watch">
                <div class="term-desk__toolbar">
                    <h3>{{ __('trading.terminal.watch') }}</h3>
                    <label class="term-desk__click">
                        <input type="checkbox" data-click-mode>
                        <span>{{ __('trading.terminal.double_click') }}</span>
                    </label>
                </div>
                <p class="term-desk__note" data-term-note></p>
                @php($watch = $this->watchRows($desk['quotes']))
                <div class="term-desk__stage">
                    <div class="term-desk__quotes">
                @if ($watch === [])
                    <p class="term-desk__empty">{{ __('trading.terminal.watch_empty') }}</p>
                @else
                    <div class="term-desk__table term-desk__table--fit">
                        <table class="term-grid" data-term-quotes>
                            <thead>
                                <tr>
                                    <th>{{ __('trading.terminal.row') }}</th>
                                    <th>{{ __('trading.terminal.symbol') }}</th>
                                    <th>{{ __('trading.terminal.bid') }}</th>
                                    <th>{{ __('trading.terminal.ask') }}</th>
                                    <th>{{ __('trading.terminal.spread') }}</th>
                                    <th>{{ __('trading.terminal.day_high') }}</th>
                                    <th>{{ __('trading.terminal.day_low') }}</th>
                                    <th>{{ __('trading.terminal.lots') }}</th>
                                    <th>{{ __('trading.terminal.buy') }} / {{ __('trading.terminal.sell') }}</th>
                                </tr>
                            </thead>
                            <tbody wire:ignore>
                                @foreach ($watch as $index => $quote)
                                    <tr data-symbol="{{ $quote['symbol'] }}" wire:key="watch-{{ $quote['symbol'] }}">
                                        <td>{{ $index + 1 }}</td>
                                        <td class="term-desk__symbol" data-chart-symbol="{{ $quote['symbol'] }}">{{ $quote['symbol'] }}</td>
                                        <td data-bid>{{ $quote['bid'] }}</td>
                                        <td data-ask>{{ $quote['ask'] }}</td>
                                        <td data-spread>—</td>
                                        <td data-high>—</td>
                                        <td data-low>—</td>
                                        <td><input data-lots type="number" value="0.01" min="0.01" max="500" step="0.01"></td>
                                        <td class="term-grid__trade">
                                            <button type="button" class="term-desk__buy" data-trade="buy" data-symbol="{{ $quote['symbol'] }}">{{ __('trading.terminal.buy') }}</button>
                                            <button type="button" class="term-desk__sell" data-trade="sell" data-symbol="{{ $quote['symbol'] }}">{{ __('trading.terminal.sell') }}</button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <script>
                        (() => {
                            if (window.agricartQuoteStream) {
                                return;
                            }
                            window.agricartQuoteStream = true;

                            const price = (value) => {
                                const number = Number(value);
                                if (!Number.isFinite(number)) {
                                    return '—';
                                }

                                return number.toFixed(Math.abs(number) >= 50 ? 2 : 5);
                            };

                            const paint = (quote) => {
                                const symbol = String(quote.symbol || '').toUpperCase();
                                const row = document.querySelector(`[data-term-quotes] [data-symbol="${symbol}"]`);
                                if (!row) {
                                    return;
                                }

                                const bid = row.querySelector('[data-bid]');
                                const ask = row.querySelector('[data-ask]');
                                const high = row.querySelector('[data-high]');
                                const low = row.querySelector('[data-low]');
                                const nextBid = price(quote.bid);
                                if (bid && bid.textContent !== nextBid) {
                                    const previous = Number(bid.dataset.last || quote.bid);
                                    bid.dataset.dir = Number(quote.bid) >= previous ? 'up' : 'down';
                                    bid.dataset.last = quote.bid;
                                    bid.textContent = nextBid;
                                }
                                if (ask) {
                                    ask.textContent = price(quote.ask);
                                }
                                if (high) {
                                    high.textContent = price(quote.high);
                                }
                                if (low) {
                                    low.textContent = price(quote.low);
                                }
                                const spread = row.querySelector('[data-spread]');
                                if (spread && quote.spread != null) {
                                    spread.textContent = quote.spread;
                                }
                                row.dataset.tickSize = quote.tick_size || '';
                                row.dataset.tickValue = quote.tick_value || '';
                                row.dataset.step = quote.volume_step || '';
                                row.dataset.min = quote.volume_min || '';
                                row.dataset.max = quote.volume_max || '';
                            };

                            const pullAccount = async () => {
                                try {
                                    const response = await fetch('http://127.0.0.1:8765/account', { cache: 'no-store' });
                                    const body = await response.json();
                                    window.agricartAccount = body;
                                    if (body.ok !== true) {
                                        return;
                                    }
                                    const write = (key, value) => {
                                        const node = document.querySelector(`[data-account="${key}"]`);
                                        if (node) {
                                            node.textContent = value;
                                        }
                                    };
                                    write('margin', body.margin);
                                    write('free', body.margin_free);
                                    write('leverage', body.leverage ? `1:${body.leverage}` : '—');
                                    write('currency', body.currency || '—');
                                    write('company', body.company || '—');
                                } catch (error) {
                                }
                            };

                            const rememberClickMode = () => {
                                const box = document.querySelector('[data-click-mode]');
                                if (!box || document.activeElement === box) {
                                    return;
                                }
                                box.checked = sessionStorage.getItem('agricart-click') === 'double';
                            };

                            document.addEventListener('change', (event) => {
                                if (event.target instanceof Element && event.target.matches('[data-click-mode]')) {
                                    sessionStorage.setItem('agricart-click', event.target.checked ? 'double' : 'single');
                                }
                            });

                            document.addEventListener('click', (event) => trade(event, false));
                            document.addEventListener('dblclick', (event) => trade(event, true));

                            const trade = (event, doubled) => {
                                const button = event.target instanceof Element ? event.target.closest('[data-trade]') : null;
                                if (!button) {
                                    return;
                                }
                                const doubleMode = document.querySelector('[data-click-mode]')?.checked === true;
                                if (doubled !== doubleMode) {
                                    return;
                                }
                                event.preventDefault();
                                if (button.dataset.busy === '1') {
                                    return;
                                }
                                button.dataset.busy = '1';
                                window.setTimeout(() => {
                                    button.dataset.busy = '0';
                                }, 800);
                                const lots = button.closest('tr')?.querySelector('[data-lots]')?.value || '0.01';
                                if (typeof window.agricartPlaceQuote === 'function') {
                                    window.agricartPlaceQuote(button.dataset.symbol, button.dataset.trade, lots);
                                    return;
                                }
                                const root = button.closest('[wire\\:id]');
                                const component = root ? Livewire.find(root.getAttribute('wire:id')) : null;
                                if (!component) {
                                    return;
                                }
                                component.call('placeQuote', button.dataset.symbol, button.dataset.trade, lots);
                            };

                            document.addEventListener('click', (event) => {
                                const cell = event.target instanceof Element ? event.target.closest('[data-chart-symbol]') : null;
                                if (!cell) {
                                    return;
                                }
                                const symbol = cell.dataset.chartSymbol || '';
                                if (symbol === '') {
                                    return;
                                }
                                const wanted = symbol.toUpperCase();
                                document.querySelectorAll('[data-term-quotes] tr[data-symbol]').forEach((row) => {
                                    row.classList.toggle('is-selected', (row.dataset.symbol || '').toUpperCase() === wanted);
                                });
                                const root = cell.closest('[wire\\:id]');
                                const component = root ? Livewire.find(root.getAttribute('wire:id')) : null;
                                if (component) {
                                    component.call('selectChart', symbol);
                                }
                            });

                            const pull = async () => {
                                rememberClickMode();
                                try {
                                    const response = await fetch('http://127.0.0.1:8765/quotes', { cache: 'no-store' });
                                    const body = await response.json();
                                    (Array.isArray(body.quotes) ? body.quotes : []).forEach(paint);
                                } catch (error) {
                                    const note = document.querySelector('[data-term-note]');
                                    if (note) {
                                        note.textContent = @js(__('trading.terminal.quote_offline'));
                                    }
                                }
                            };

                            pull();
                            pullAccount();
                            setInterval(pull, 50);
                            setInterval(pullAccount, 1000);
                        })();
                    </script>
                @endif
                    </div>
                    <aside class="term-desk__pending" aria-label="{{ __('trading.terminal.pending') }}">
                        <div class="term-desk__pending-scroll">
                            <h3>{{ __('trading.terminal.pending') }}</h3>
                            <form class="term-desk__pending-form" wire:submit="placePendingOrder">
                                <label>
                                    <span>{{ __('trading.terminal.symbol') }}</span>
                                    <input wire:model="pendingSymbol">
                                </label>
                                <label>
                                    <span>{{ __('trading.terminal.side') }}</span>
                                    <select wire:model="pendingType">
                                        <option value="buy_limit">{{ __('trading.terminal.buy_limit') }}</option>
                                        <option value="sell_limit">{{ __('trading.terminal.sell_limit') }}</option>
                                        <option value="buy_stop">{{ __('trading.terminal.buy_stop') }}</option>
                                        <option value="sell_stop">{{ __('trading.terminal.sell_stop') }}</option>
                                    </select>
                                </label>
                                <label>
                                    <span>{{ __('trading.terminal.lots') }}</span>
                                    <input wire:model="pendingLots" inputmode="decimal">
                                </label>
                                <label>
                                    <span>{{ __('trading.terminal.price') }}</span>
                                    <input wire:model="pendingPrice" inputmode="decimal">
                                </label>
                                <label>
                                    <span>{{ __('trading.terminal.stop_loss') }}</span>
                                    <input wire:model="pendingStop" inputmode="decimal">
                                </label>
                                <label>
                                    <span>{{ __('trading.terminal.take_profit') }}</span>
                                    <input wire:model="pendingTake" inputmode="decimal">
                                </label>
                                <button type="submit">{{ __('trading.terminal.place_pending') }}</button>
                            </form>
                            <ul class="term-desk__pending-list">
                                @forelse ($this->pendingOrders() as $order)
                                    <li wire:key="pending-{{ $order['ticket'] }}">
                                        <strong>#{{ $order['ticket'] }} {{ $order['symbol'] }}</strong>
                                        <span>{{ $order['type'] }} · {{ $order['lots'] }} @ {{ $order['price'] }}</span>
                                        <button type="button" wire:click="cancelPendingOrder({{ (int) $order['ticket'] }})">{{ __('trading.terminal.cancel') }}</button>
                                    </li>
                                @empty
                                    <li class="is-empty">{{ __('trading.terminal.no_pending') }}</li>
                                @endforelse
                            </ul>
                        </div>
                    </aside>
                    <aside class="term-desk__meter" aria-label="{{ __('trading.terminal.summary') }}">
                        <div class="term-desk__meter-body">
                            <h3>{{ __('trading.terminal.summary') }}</h3>
                            <dl>
                                <div>
                                    <dt>{{ __('trading.terminal.balance') }}</dt>
                                    <dd>{{ $desk['summary']['balance'] ?? '—' }}</dd>
                                </div>
                                <div>
                                    <dt>{{ __('trading.terminal.equity') }}</dt>
                                    <dd>{{ $desk['summary']['equity'] ?? '—' }}</dd>
                                </div>
                                <div>
                                    <dt>{{ __('trading.terminal.today_pnl') }}</dt>
                                    <dd @class(['term-desk__pnl', 'term-desk__pnl--'.($desk['summary']['today_tone'] ?? 'flat')])>{{ $desk['summary']['today'] ?? '—' }}</dd>
                                </div>
                                <div>
                                    <dt>{{ __('trading.terminal.margin') }}</dt>
                                    <dd data-account="margin">{{ $strip['margin'] ?? '—' }}</dd>
                                </div>
                                <div>
                                    <dt>{{ __('trading.terminal.margin_free') }}</dt>
                                    <dd data-account="free">{{ $strip['margin_free'] ?? '—' }}</dd>
                                </div>
                            </dl>
                        </div>
                    </aside>
                    <section class="term-desk__chart" aria-label="{{ __('trading.terminal.candles') }}">
                        <div class="term-desk__chart-body">
                            <div class="term-desk__chart-head">
                                <h3>{{ __('trading.terminal.candles') }}@if ($orderSymbol !== '') · {{ $orderSymbol }}@endif</h3>
                                <div class="term-desk__chips">
                                    @foreach (['1m', '5m', '15m', '30m', '1h', '4h', 'd', 'w'] as $frame)
                                        <button type="button" class="term-desk__chip {{ $candleFrame === $frame ? 'is-on' : '' }}" wire:click="loadCandles('{{ $frame }}')" wire:key="frame-{{ $frame }}">{{ strtoupper($frame) }}</button>
                                    @endforeach
                                </div>
                            </div>
                            @if ($candles === [])
                                <p class="term-desk__empty">{{ $orderSymbol === '' ? __('trading.terminal.candles_empty') : __('trading.terminal.candles_missing') }}</p>
                            @else
                                @php($points = $this->candleBars())
                                <svg class="term-desk__plot" viewBox="0 0 {{ count($points) }} 100" preserveAspectRatio="none" role="img" aria-label="{{ __('trading.terminal.candles') }}">
                                    @foreach ($points as $point)
                                        <g class="{{ $point['up'] ? 'is-up' : 'is-down' }}">
                                            <title>{{ $point['time'] }} {{ $point['close'] }}</title>
                                            <line vector-effect="non-scaling-stroke" x1="{{ $point['x'] }}" y1="{{ $point['high'] }}" x2="{{ $point['x'] }}" y2="{{ $point['low'] }}" />
                                            <rect vector-effect="non-scaling-stroke" x="{{ $point['body'] }}" y="{{ $point['top'] }}" width="0.64" height="{{ $point['height'] }}" />
                                        </g>
                                    @endforeach
                                </svg>
                                <p class="term-desk__chart-last">{{ $orderSymbol }} {{ $points[count($points) - 1]['close'] }}</p>
                            @endif
                        </div>
                    </section>
                </div>
                @if ($orderTone)
                    <div class="prop-risk prop-risk--{{ $orderTone }}">
                        @foreach ($orderMessages as $message)
                            <p>{{ $message }}</p>
                        @endforeach
                    </div>
                @endif
            </section>
        </div>

        <div class="term-desk__book">
            <h3>{{ __('trading.terminal.open_positions') }}</h3>
            <div class="term-desk__table">
                <table>
                    <thead>
                        <tr>
                            <th>{{ __('trading.terminal.ticket_no') }}</th>
                            <th>{{ __('trading.terminal.symbol') }}</th>
                            <th>{{ __('trading.terminal.side') }}</th>
                            <th>{{ __('trading.terminal.lots') }}</th>
                            <th>{{ __('trading.terminal.entry') }}</th>
                            <th>{{ __('trading.terminal.stop_loss') }}</th>
                            <th>{{ __('trading.terminal.take_profit') }}</th>
                            <th>{{ __('trading.terminal.pnl') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($desk['open'] as $row)
                            <tr wire:key="open-{{ $row['id'] }}">
                                <td>{{ $row['ticket'] }}</td>
                                <td>{{ $row['symbol'] }}</td>
                                <td>{{ $row['side'] }}</td>
                                <td>{{ $row['lots'] }}</td>
                                <td>{{ $row['entry'] }}</td>
                                <td>{{ $row['stop'] }}</td>
                                <td>{{ $row['take'] }}</td>
                                <td class="term-desk__pnl term-desk__pnl--{{ $row['pnl_tone'] }}">{{ $row['pnl'] }}</td>
                                <td class="term-grid__trade">
                                    <input data-stop value="{{ $row['stop'] }}" inputmode="decimal">
                                    <input data-take value="{{ $row['take'] }}" inputmode="decimal">
                                    <button type="button" x-on:click="$wire.changeProtection({{ $row['id'] }}, $el.parentElement.querySelector('[data-stop]').value, $el.parentElement.querySelector('[data-take]').value)">{{ __('trading.terminal.save_protection') }}</button>
                                    <input data-part value="0.01" inputmode="decimal">
                                    <button type="button" x-on:click="$wire.partialClose({{ $row['id'] }}, $el.parentElement.querySelector('[data-part]').value)">{{ __('trading.terminal.partial') }}</button>
                                    <button type="button" wire:click="closePosition({{ $row['id'] }})">{{ __('trading.terminal.close') }}</button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9">{{ __('trading.terminal.no_positions') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <h3>{{ __('trading.terminal.history') }}</h3>
            <form class="term-desk__connect" wire:submit="loadHistory">
                <label>
                    <span>{{ __('trading.terminal.history_days') }}</span>
                    <input wire:model="historyDays" inputmode="numeric" min="1" max="365">
                </label>
                <div class="term-desk__connect-actions">
                    <button type="submit">{{ __('trading.terminal.load_history') }}</button>
                </div>
            </form>
            @if ($archive !== [])
                <div class="term-desk__table term-desk__table--fit">
                    <table class="term-grid">
                        <thead>
                            <tr>
                                <th>{{ __('trading.terminal.ticket_no') }}</th>
                                <th>{{ __('trading.terminal.symbol') }}</th>
                                <th>{{ __('trading.terminal.lots') }}</th>
                                <th>{{ __('trading.terminal.exit') }}</th>
                                <th>{{ __('trading.terminal.pnl') }}</th>
                                <th>{{ __('trading.terminal.time') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($archive as $deal)
                                <tr wire:key="archive-{{ $deal['position_ticket'] }}-{{ $deal['time'] }}">
                                    <td>#{{ $deal['position_ticket'] }}</td>
                                    <td>{{ $deal['symbol'] }}</td>
                                    <td>{{ $deal['lots'] }}</td>
                                    <td>{{ $deal['price'] }}</td>
                                    <td>{{ $deal['profit'] }}</td>
                                    <td>{{ $deal['time'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
            <div class="term-desk__table">
                <table>
                    <thead>
                        <tr>
                            <th>{{ __('trading.terminal.ticket_no') }}</th>
                            <th>{{ __('trading.terminal.symbol') }}</th>
                            <th>{{ __('trading.terminal.side') }}</th>
                            <th>{{ __('trading.terminal.lots') }}</th>
                            <th>{{ __('trading.terminal.entry') }}</th>
                            <th>{{ __('trading.terminal.exit') }}</th>
                            <th>{{ __('trading.terminal.pnl') }}</th>
                            <th>{{ __('trading.terminal.time') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($desk['history'] as $row)
                            <tr wire:key="history-{{ $row['id'] }}">
                                <td>{{ $row['ticket'] }}</td>
                                <td>{{ $row['symbol'] }}</td>
                                <td>{{ $row['side'] }}</td>
                                <td>{{ $row['lots'] }}</td>
                                <td>{{ $row['entry'] }}</td>
                                <td>{{ $row['exit'] }}</td>
                                <td class="term-desk__pnl term-desk__pnl--{{ $row['pnl_tone'] }}">{{ $row['pnl'] }}</td>
                                <td>{{ $row['time'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8">{{ __('trading.terminal.no_history') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>
    </div>
</x-filament-panels::page>
