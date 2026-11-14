<?php

return [

    'language' => 'Language',

    'nav' => [
        'trading' => 'Trading',
        'trading_view' => 'TradingView',
        'open_trades' => 'Open Trades',
        'mt5_terminal' => 'MT5 Terminal',
        'python_bridge_logs' => 'Python Bridge Logs',
        'settings' => 'Settings',
        'page_info' => 'Page Info',
    ],

    'quick' => [
        'pos' => 'POS',
        'pos_title' => 'Point of Sale',
        'sale' => 'Sale',
        'sale_title' => 'Create Sale',
        'purchase' => 'Purchase',
        'purchase_title' => 'Create Purchase',
    ],

    'sectors' => [
        'label' => 'Market sectors',
        'currencies' => 'Currencies',
        'indices' => 'Indices',
        'commodities' => 'Commodities',
        'crypto' => 'Crypto',
    ],

    'sections' => [
        'investing' => 'Investing.com summaries',
        'rsi' => 'RSI Technical Indicators',
        'macd' => 'MACD Momentum Signals',
    ],

    'rsi' => [
        'buy' => 'Buying Area / Oversold',
        'sell' => 'Selling Area / Overbought',
        'neutral' => 'Neutral',
    ],

    'macd' => [
        'buy' => 'Bullish',
        'sell' => 'Bearish',
        'neutral' => 'Neutral',
    ],

    'histogram' => 'Histogram :value',

    'notices' => [
        'unsupported' => 'This symbol is currently not supported or unavailable.',
        'investing_missing' => 'Investing.com does not publish a technical summary for this symbol.',
        'investing_summary' => 'Investing.com :frame summary',
    ],

    'page_info' => [
        'title' => 'Page information',
        'eyebrow' => 'Page information',
        'user_guide' => 'User Guide',
        'ai_prompt' => 'AI Master Prompt',
        'copy' => 'Copy',
        'copied' => 'Copied',
        'open_tab' => 'Open in New Tab',
        'minimize' => 'Minimize',
        'maximize' => 'Maximize',
        'restore' => 'Restore',
        'close' => 'Close',
        'empty' => 'No guide has been published for this page.',
        'minimized' => 'Minimized windows',
    ],

    'open_trades' => [
        'ticket' => 'Ticket',
        'symbol' => 'Symbol',
        'type' => 'Type',
        'volume' => 'Volume',
        'open' => 'Open',
        'profit' => 'P/L',
        'empty' => 'No open trades',
    ],

    'terminal' => [
        'offline' => 'Terminal offline',
    ],

    'logs' => [
        'refresh' => 'Refresh',
        'symbol' => 'Symbol',
        'bid' => 'Bid',
        'ask' => 'Ask',
        'last' => 'Last',
        'source' => 'Source',
        'feed_at' => 'Feed at',
        'empty' => 'No data',
    ],

    'guides' => [
        'trading-view' => [
            'user' => <<<'TEXT'
The top row is the TradingView summary for 30M, 1H, 4H, 1D, 1W, and 1M.

The next row is the Investing.com summary for 15M, 30M, 1H, 5H, 1D, and 1W.

RSI shows the value, a 0–100 bar, and a zone: Buying Area / Oversold under 30, Neutral from 30 to 70, and Selling Area / Overbought over 70.

MACD shows Bullish when the MACD line is above the signal line and Bearish when it is below, plus the histogram.

Pick a symbol from Currencies, Indices, Commodities, or Crypto. Every row follows that symbol.
TEXT,
            'prompt' => <<<'TEXT'
You are the AgriCart trading desk assistant. Use only the symbol and timeframes visible on this page. Describe the TradingView summary, the Investing.com summary, the RSI zone, and whether MACD is bullish or bearish. Do not invent prices or signals that are not on the page. Keep the answer short, and say when a reading is unavailable.
TEXT,
        ],
    ],

];
