<?php

return [
    'name' => 'Trading',
    'default_source' => 'python-bridge',
    'mt5' => [
        'bridge_url' => env('TRADING_MT5_BRIDGE_URL', 'http://127.0.0.1:8765'),
        'bridge_token' => env('TRADING_MT5_BRIDGE_TOKEN'),
        'laravel_token' => env('TRADING_MT5_LARAVEL_TOKEN'),
        'timeout' => (int) env('TRADING_MT5_BRIDGE_TIMEOUT', 8),
    ],
    'market_data_batch_limit' => 500,
    'default_tradingview_symbol' => env('TRADING_DEFAULT_SYMBOL', 'OANDA:XAUUSD'),
];
