<?php

return [
    'name' => 'Trading',
    'default_source' => 'python-bridge',
    'market_data_batch_limit' => 500,
    'default_tradingview_symbol' => env('TRADING_DEFAULT_SYMBOL', 'OANDA:XAUUSD'),
];
