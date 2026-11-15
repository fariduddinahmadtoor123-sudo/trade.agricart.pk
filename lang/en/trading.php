<?php

return [

    'language' => 'Language',

    'nav' => [
        'trading' => 'Trading',
        'trading_view' => 'TradingView',
        'signals' => 'Signals',
        'economic_calendar' => 'Economic Calendar / News',
        'market_sessions' => 'Market Sessions',
        'smart_alerts' => 'Smart Alerts',
        'open_trades' => 'Open Trades',
        'mt5_terminal' => 'MT5 Terminal',
        'python_bridge_logs' => 'Bridge Logs',
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
        'sentiment' => 'Market Sentiment',
        'rsi' => 'RSI Technical Indicators',
        'macd' => 'MACD Momentum Signals',
        'ma' => 'Moving Averages',
        'bollinger' => 'Bollinger Bands',
        'pivot' => 'Pivot Points',
    ],

    'notes' => [
        'rsi' => 'RSI below 30 signals an oversold buying zone, while above 70 signals an overbought selling zone.',
        'macd' => 'A positive MACD histogram indicates bullish momentum, while a negative histogram shows bearish pressure.',
        'ma' => 'EMA 50 above EMA 200 shows bullish alignment; below it shows a bearish trend.',
        'bollinger' => 'A narrow band width marks a squeeze, while a wide band marks expanded volatility.',
        'pivot' => 'Pivot Point, resistance, and support mark the key reversal levels for each timeframe.',
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

    'ma' => [
        'strong_buy' => 'Strong Bullish',
        'buy' => 'Bullish Trend',
        'sell' => 'Bearish Trend',
        'neutral' => 'Neutral',
        'ema50' => 'EMA 50',
        'ema200' => 'EMA 200',
    ],

    'bollinger' => [
        'squeeze' => 'Squeeze / Low Volatility',
        'expand' => 'Expanded / High Volatility',
        'normal' => 'Normal Volatility',
        'lower' => 'Lower',
        'basis' => 'Mid',
        'upper' => 'Upper',
        'width' => 'Width :value',
    ],

    'pivot' => [
        'pp' => 'PP',
        'r1' => 'R1',
        'r2' => 'R2',
        's1' => 'S1',
        's2' => 'S2',
    ],

    'histogram' => 'Histogram :value',

    'notices' => [
        'unsupported' => 'This symbol is currently not supported or unavailable.',
        'investing_summary' => 'Investing.com :frame summary',
        'data_unavailable' => 'Data not available',
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
        'dock' => 'Workspace dock',
        'open_dock' => 'Open page information',
        'text_size' => 'Text size',
        'text_smaller' => 'A−',
        'text_larger' => 'A+',
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

    'calendar' => [
        'subtitle' => 'This live calendar shows high-impact fundamental news and data releases from global sources, so you can manage risk around scheduled events.',
        'sources' => [
            'label' => 'Calendar source',
            'investing' => 'Investing.com',
        ],
        'investing_filters' => [
            'label' => 'Investing.com filters',
            'range_label' => 'Day range',
            'impact_label' => 'Impact',
            'currency_label' => 'Currencies from Signals',
            'all_currencies' => 'All',
            'current_time' => 'Current Time',
            'timezone_label' => 'Local timezone',
            'timezone_note' => 'Synced automatically from your system clock.',
            'note' => 'Filters use Investing.com’s official widget options only.',
        ],
        'legend' => [
            'title' => 'Legend',
            'event_types' => 'Event Types',
            'volatility' => 'Volatility / Impact',
            'speech' => 'Speech',
            'preliminary' => 'Preliminary Release',
            'revised' => 'Revised Release',
            'retrieving' => 'Retrieving Data',
            'volatility_low' => 'Low Volatility Expected',
            'volatility_medium' => 'Moderate Volatility Expected',
            'volatility_high' => 'High Volatility Expected',
        ],
        'countdown' => [
            'title' => 'Next High-Impact USD Data',
            'subtitle' => 'Tracks FOMC, NFP, CPI, GDP, Retail Sales — Speeches stay in the calendar',
            'live' => 'Live',
            'days' => 'Days',
            'hours' => 'Hours',
            'minutes' => 'Mins',
            'seconds' => 'Secs',
            'empty' => 'No upcoming high-impact USD data releases match the active filters.',
            'today_title' => 'Today’s Primary High-Impact Release',
            'today_badge' => 'Today',
            'upcoming' => 'Upcoming Highlights',
        ],
        'usd_reference' => [
            'title' => 'High-Impact USD Data Reference',
            'items' => [
                'fomc' => [
                    'name' => 'FOMC Rate Decision',
                    'description' => 'Interest rate decision — creates the biggest storm in forex and gold markets.',
                ],
                'nfp' => [
                    'name' => 'Non-Farm Payrolls (NFP)',
                    'description' => 'US employment data — the most volatile and important figure every month.',
                ],
                'cpi' => [
                    'name' => 'CPI (Consumer Price Index)',
                    'description' => 'Inflation gauge — the core measure that sets the long-term path of USD and gold.',
                ],
                'gdp' => [
                    'name' => 'GDP Growth Rate',
                    'description' => 'Gross domestic product — the strongest proof of US economic speed and health.',
                ],
                'retail' => [
                    'name' => 'Retail Sales',
                    'description' => 'Retail sales — mirrors US consumer spending and economic activity.',
                ],
            ],
        ],
        'filters' => [
            'label' => 'Calendar filters',
            'day_label' => 'Day range',
            'impact_label' => 'Impact',
            'today' => 'Today',
            'tomorrow' => 'Tomorrow',
            'upcoming' => 'Upcoming',
            'week' => 'This week',
        ],
        'columns' => [
            'time' => 'Time',
            'currency' => 'Currency',
            'impact' => 'Impact',
            'event' => 'Event',
            'forecast' => 'Forecast',
            'previous' => 'Previous',
        ],
        'impact' => [
            'high' => 'High',
            'medium' => 'Medium',
            'low' => 'Low',
            'holiday' => 'Holiday',
        ],
    ],

    'market_sessions' => [
        'title' => 'Global Market Sessions',
        'live' => 'Live',
        'local_timezone' => 'Local timezone',
        'pkt_label' => 'PKT',
        'weekend_banner' => 'Saturday–Sunday: the forex market is normally closed. Some brokers still run a few symbols — check your terminal if anything is moving.',
        'open' => 'Open',
        'closed' => 'Closed',
        'active_prefix' => 'Active now:',
        'none_active' => 'No major forex session is open right now.',
        'status_idle' => 'No overlap',
        'status_closed' => 'Market closed',
        'status_single' => ':name only',
        'status_overlap' => ':count-session overlap · :names',
        'activity' => [
            'low' => 'Low risk / thin liquidity',
            'moderate' => 'Moderate risk',
            'high' => 'High risk / high liquidity',
            'peak' => 'Peak risk / peak liquidity',
        ],
        'sessions' => [
            'sydney' => [
                'name' => 'Sydney',
                'hours' => '03:00–12:00 PKT',
                'summary' => 'Opens the trading week and sets early tone in AUD/NZD crosses before Asia fully wakes up.',
                'traits' => [
                    'Quieter liquidity outside AUD/NZD focus pairs',
                    'Useful for range setups into Tokyo open',
                    'Can gap on Monday reopen after the weekend',
                ],
            ],
            'tokyo' => [
                'name' => 'Tokyo',
                'hours' => '05:00–14:00 PKT',
                'summary' => 'Asia’s core session — JPY flows, China-linked headlines, and orderly trend continuation.',
                'traits' => [
                    'Strong focus on USDJPY and Asia crosses',
                    'Typically cleaner technical movement than late NY',
                    'Volatility rises around Tokyo fix and data prints',
                ],
            ],
            'london' => [
                'name' => 'London',
                'hours' => '13:00–22:00 PKT',
                'summary' => 'Europe’s primary liquidity engine — often creates the day’s directional break.',
                'traits' => [
                    'Highest European volume and tighter spreads',
                    'Major EUR/GBP reaction window for news',
                    'Sets the stage for the New York handoff',
                ],
            ],
            'new_york' => [
                'name' => 'New York',
                'hours' => '18:00–03:00 PKT',
                'summary' => 'US session drives dollar, gold, and index-linked FX with heavy institutional flow.',
                'traits' => [
                    'USD, XAU, and equity-correlated FX dominate',
                    'Large moves around US data and Fed speakers',
                    'Liquidity fades into the late afternoon drift',
                ],
            ],
        ],
    ],

    'smart_alerts' => [
        'saved' => 'Alert preferences saved',
        'rules' => [
            'eyebrow' => 'Configuration',
            'title' => 'Alert Rules',
            'subtitle' => 'Toggle the warnings that should arm around high-impact releases.',
            'high_impact_news' => [
                'label' => 'High-Impact News Alerts',
                'description' => 'Arm the desk for major data releases from the USD reference list.',
            ],
            'warn_30' => [
                'label' => '30-Minute Pre-Event Warning',
                'description' => 'Trigger a warning thirty minutes before the scheduled release.',
            ],
            'warn_15' => [
                'label' => '15-Minute Pre-Event Warning',
                'description' => 'Trigger a final warning fifteen minutes before the release.',
            ],
        ],
        'currencies' => [
            'label' => 'Currency Filters',
            'note' => 'Only matching currencies appear in the activity feed.',
        ],
        'activity' => [
            'eyebrow' => 'Activity',
            'title' => 'Triggered and Upcoming Alerts',
            'subtitle' => 'Live desk log built from your rules and the high-impact schedule.',
            'count' => '{0} No alerts|{1} :count alert|[2,*] :count alerts',
            'empty_title' => 'No alerts match the current rules',
            'empty_body' => 'Enable High-Impact News and keep at least one currency selected to populate the feed.',
        ],
        'columns' => [
            'status' => 'Status',
            'when' => 'Timestamp',
            'impact' => 'Impact',
            'event' => 'Event',
            'kind' => 'Alert Type',
        ],
        'status' => [
            'scheduled' => 'Scheduled',
            'upcoming' => 'Upcoming',
            'triggered' => 'Triggered',
            'passed' => 'Passed',
        ],
        'kinds' => [
            'warn_30' => '30-min warning',
            'warn_15' => '15-min warning',
            'release' => 'Release window',
        ],
        'descriptions' => [
            'warn_30' => '30-minute warning for :event (:when).',
            'warn_15' => '15-minute warning for :event (:when).',
            'release' => 'Release window for :event (:when).',
        ],
    ],

    'placeholders' => [
        'economic_calendar' => 'Economic releases and market headlines will appear in this space.',
        'smart_alerts' => 'Price and signal alerts will appear in this space.',
    ],

    'guides' => [
        'sessions' => [
            'user' => <<<'TEXT'
AgriCart Market Sessions is a live forex liquidity clock for the trading desk. It maps the four major hubs on a fixed Pakistan Standard Time (PKT, UTC+5) day axis. The moving clock line is always PKT. It is a decision-support map, not trade execution and not investment advice.

1) What the timeline shows
Four session bars only: Sydney, Tokyo, London, New York. Open / Closed updates live. The vertical clock line moves across the PKT day (00–24). Where that line sits tells you the live state:
• Over 0 bars (none Open): market closed or no session active
• Over 1 Open bar: that single session only
• Over 2 Open bars: 2-session overlap (those two hubs — the usual maximum with these windows)
The header status chip repeats the same reading (e.g. “2-session overlap · London · New York”).

2) Risk color ladder
• Green: low risk / thinner liquidity (Sydney)
• Amber: moderate risk (Tokyo)
• Deep orange: high risk / high liquidity (London)
• Blood red: high risk / high liquidity (New York)
Darker and redder means faster flow and larger typical moves — treat as higher caution, not a trade signal. Hours use common standard-time UTC windows and may differ by about one hour around daylight-saving changes.

3) Bank holidays and crypto
US, UK, Japan, and Australia public holidays can close desks early or thin liquidity inside normal hours. Check the Economic Calendar before sizing risk. Crypto (e.g. BTC) can trade 24/7 even when FX sessions are closed; FX, gold, and index CFDs follow this clock and your broker’s symbol hours.

4) Language
Use EN | اردو in the header. Urdu switches the admin panel to right-to-left and translates AgriCart labels. The timeline stays left-to-right so the clock line still matches the hours.
TEXT,
            'prompt' => <<<'TEXT'
You are the AgriCart Market Sessions desk assistant for this admin page only.

Scope
• Explain the four sessions (Sydney, Tokyo, London, New York).
• Overlap is read from the moving clock line: how many session bars are Open under the line (0 = closed/idle, 1 = single session, 2+ = overlap of those hubs).
• Use the page’s risk color ladder: green = low, amber = moderate, deep orange / blood red = high liquidity risk.
• Respect weekend FX close (Friday ~22:00 UTC to Sunday ~22:00 UTC) and note that crypto may still trade.

Rules
• Do not invent live session states; prefer the Open/Closed labels and overlap status chip on the page.
• Do not give trade entries, lot sizes, or investment advice.
• Keep answers short and structured.
TEXT,
        ],
        'signals' => [
            'user' => <<<'TEXT'
AgriCart Signals is a multi-timeframe technical monitoring desk for one selected market at a time. All panels on this page are live market readings. They are decision-support tools, not automated trade execution and not investment advice.

1) Symbol selection
Use the sector bar: Currencies, Indices, Commodities, and Crypto. One click sets the active symbol. Every panel below updates to that symbol.

2) TradingView Summary (row 1)
Six official TradingView technical-analysis summary gauges for 30M, 1H, 4H, 1D, 1W, and 1M. Each gauge shows the composite Summary bias for that timeframe.

3) Investing.com Summary (row 2)
Six Investing.com technical-summary cards for 15M, 30M, 1H, 5H, 1D, and 1W. If Investing.com does not publish a summary for the selected symbol, the page shows a clear unavailable notice instead of a substitute market.

4) RSI Technical Indicators
Relative Strength Index on a 0–100 scale for the same six TradingView timeframes.
• Below 30: Buying Area / Oversold
• 30 to 70: Neutral
• Above 70: Selling Area / Overbought

5) MACD Momentum Signals
MACD histogram direction for each timeframe.
• Positive histogram: Bullish momentum
• Negative histogram: Bearish pressure
• Flat histogram: Neutral

6) Moving Averages (EMA 50 vs EMA 200)
Short-term versus long-term trend alignment.
• Strong Bullish: EMA 50 above EMA 200 and price holding with the short average
• Bullish Trend / Bearish Trend: directional EMA alignment
• Neutral: averages essentially flat against each other

7) Bollinger Bands
Volatility state and price location between the lower and upper bands.
• Squeeze / Low Volatility: compressed band width
• Expanded / High Volatility: wide band width
• The meter shows where price sits between Lower, Mid, and Upper

8) Pivot Points
Classic pivot reference levels for each timeframe: R2, R1, Pivot Point (PP), S1, and S2. Traders use these as support, resistance, and potential reversal checkpoints.

9) Language and layout
Use EN | اردو in the header. English is left-to-right. Urdu switches the admin panel to right-to-left and translates the AgriCart labels on this page.

Data integrity
TradingView widgets, Investing.com widgets, and AgriCart indicator cards (RSI, MACD, Moving Averages, Bollinger Bands, Pivot Points) are live feeds for the selected symbol. Readings can differ slightly between providers because each uses its own calculation feed. Always confirm risk, position size, and execution in your broker terminal before trading.
TEXT,
            'prompt' => <<<'TEXT'
You are the AgriCart Signals desk assistant for this admin page only.

Scope
• Use only the currently selected symbol and the values visible on the page.
• Cover, when present: TradingView summary gauges (30M, 1H, 4H, 1D, 1W, 1M), Investing.com summaries (15M, 30M, 1H, 5H, 1D, 1W), RSI zones, MACD histogram direction, Moving Average alignment (EMA 50 vs EMA 200), Bollinger Band volatility/position, and Pivot levels (PP, R1, R2, S1, S2).

Rules
• Do not invent prices, levels, or signals that are not shown.
• If a panel is unavailable or unsupported for the symbol, say so plainly.
• Treat the page as live technical monitoring, not trade execution and not investment advice.
• Keep answers short, structured, and timeframe-aware. Prefer bullet points when comparing multiple frames.
TEXT,
        ],
    ],

];
