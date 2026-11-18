<x-filament-panels::page>
    <div
        wire:ignore.self
        x-data="tradingViewWorkspace(@js([
            'symbol' => $symbol,
            'investingPairs' => $investingPairs,
            'myfxbookSentimentSymbols' => $myfxbookSentimentSymbols,
            'myfxbookOutlookEmbedBase' => url('/trading/embeds/myfxbook-outlook'),
            'symbolLabels' => $this->symbolLabels(),
        ]))"
        x-init="init()"
        class="tv-workspace"
    >
        <div class="tv-sectors" aria-label="{{ __('trading.sectors.label') }}">
            @foreach ($sectors as $sector)
                <section class="tv-sector" aria-label="{{ __('trading.sectors.'.$sector['key']) }}">
                    <div class="tv-sector__header">
                        <x-filament::icon :icon="$sector['icon']" class="tv-sector__icon" />
                        <span>{{ __('trading.sectors.'.$sector['key']) }}</span>
                    </div>
                    <div class="tv-sector__symbols">
                        @foreach ($sector['symbols'] as $item)
                            <button
                                type="button"
                                class="tv-symbol-pill"
                                :class="{ 'is-active': symbol === @js($item['value']) }"
                                :aria-pressed="(symbol === @js($item['value'])).toString()"
                                @click="setSymbol(@js($item['value']))"
                            >
                                {{ $item['label'] }}
                            </button>
                        @endforeach
                    </div>
                </section>
            @endforeach
        </div>

        <p class="tv-symbol-notice" x-show="symbolNotice" x-cloak>
            {{ __('trading.notices.unsupported') }}
        </p>

        <section class="tv-indicators" wire:poll.60s.visible="refreshIndicators" aria-label="{{ __('trading.sections.pivot') }}">
            <div class="tv-pivot-head">
                <p class="tv-indicators__note">{{ __('trading.notes.pivot') }}</p>
                <p class="tv-live-rate" aria-live="polite">
                    <span class="tv-live-rate__label">{{ __('trading.notices.live_price') }}</span>
                    <span class="tv-live-rate__symbol">{{ $this->symbolLabels()[$symbol] ?? $symbol }}</span>
                    <span class="tv-live-rate__value">{{ $livePrice ?? __('trading.notices.data_unavailable') }}</span>
                </p>
            </div>
            <div class="tv-workspace__gauges">
                @foreach ($indicatorFrames as $frame)
                    <section class="tv-panel tv-panel--pivot" wire:key="pivot-{{ $symbol }}-{{ $frame['label'] }}">
                        <div class="tv-panel__label">
                            <span class="tv-panel__frame">{{ $frame['label'] }}</span>
                            <span class="tv-panel__name">{{ __('trading.sections.pivot') }}</span>
                        </div>
                        <div class="tv-panel__host tv-panel__host--indicator tv-pivot">
                            @if (($frame['pivot'] ?? null) === null)
                                <p class="tv-indicator__empty">{{ __('trading.notices.unsupported') }}</p>
                            @else
                                <table class="tv-pivot__table">
                                    <tbody>
                                        <tr class="is-resistance">
                                            <th scope="row">{{ __('trading.pivot.r2') }}</th>
                                            <td>{{ $frame['pivot']['r2'] }}</td>
                                        </tr>
                                        <tr class="is-resistance">
                                            <th scope="row">{{ __('trading.pivot.r1') }}</th>
                                            <td>{{ $frame['pivot']['r1'] }}</td>
                                        </tr>
                                        <tr class="is-pivot">
                                            <th scope="row">{{ __('trading.pivot.pp') }}</th>
                                            <td>{{ $frame['pivot']['pp'] }}</td>
                                        </tr>
                                        <tr class="is-support">
                                            <th scope="row">{{ __('trading.pivot.s1') }}</th>
                                            <td>{{ $frame['pivot']['s1'] }}</td>
                                        </tr>
                                        <tr class="is-support">
                                            <th scope="row">{{ __('trading.pivot.s2') }}</th>
                                            <td>{{ $frame['pivot']['s2'] }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            @endif
                        </div>
                    </section>
                @endforeach
            </div>
        </section>

        <div class="tv-workspace__gauges">
            @foreach ($gauges as $gauge)
                <section class="tv-panel tv-panel--gauge">
                    <div class="tv-panel__label">{{ $gauge['label'] }}</div>
                    <div
                        wire:ignore
                        data-interval="{{ $gauge['interval'] }}"
                        class="tv-panel__host tv-panel__host--gauge"
                    ></div>
                </section>
            @endforeach
        </div>

        <section class="tv-investing" aria-label="{{ __('trading.sections.investing') }}">
            <div class="tv-workspace__gauges">
                @foreach ($investingFrames as $frame)
                    <section class="tv-panel tv-panel--gauge" aria-label="{{ __('trading.notices.investing_summary', ['frame' => $frame['label']]) }}">
                        <div class="tv-panel__label">{{ $frame['label'] }}</div>
                        <div
                            wire:ignore
                            class="tv-panel__host tv-panel__host--investing"
                        >
                            <div class="tv-investing__clip" x-show="investingSelection()" x-cloak>
                                <iframe
                                    class="tv-investing__frame"
                                    data-timeframe="{{ $frame['timeframe'] }}"
                                    title="{{ __('trading.notices.investing_summary', ['frame' => $frame['label']]) }}"
                                    scrolling="no"
                                    referrerpolicy="no-referrer-when-downgrade"
                                ></iframe>
                            </div>
                            <p class="tv-investing__empty" x-show="!investingSelection()" x-cloak role="status">
                                {{ __('trading.notices.data_unavailable') }}
                            </p>
                        </div>
                    </section>
                @endforeach
            </div>
        </section>

        <section class="tv-sentiment" aria-label="{{ __('trading.sections.sentiment') }}">
            <header class="tv-sentiment__header">
                <p class="tv-sentiment__eyebrow">{{ __('trading.sections.sentiment') }}</p>
            </header>

            <div class="tv-sentiment__panel" wire:ignore>
                <iframe
                    class="tv-sentiment__frame"
                    x-show="sentimentUrl()"
                    x-cloak
                    :src="sentimentUrl() || 'about:blank'"
                    :title="sentimentTitle()"
                    loading="lazy"
                    referrerpolicy="no-referrer-when-downgrade"
                ></iframe>
                <p class="tv-sentiment__empty" x-show="!sentimentUrl()" x-cloak role="status">
                    {{ __('trading.notices.data_unavailable') }}
                </p>
            </div>
        </section>

        <section class="tv-indicators" aria-label="{{ __('trading.sections.rsi') }}">
            <p class="tv-indicators__note">{{ __('trading.notes.rsi') }}</p>
            <div class="tv-workspace__gauges">
                @foreach ($indicatorFrames as $frame)
                    <section class="tv-panel tv-panel--indicator" wire:key="rsi-{{ $symbol }}-{{ $frame['label'] }}">
                        <div class="tv-panel__label">
                            <span class="tv-panel__frame">{{ $frame['label'] }}</span>
                            <span class="tv-panel__name">{{ __('trading.sections.rsi') }}</span>
                        </div>
                        <div class="tv-panel__host tv-panel__host--indicator tv-indicator">
                            @if ($frame['rsi'] === null)
                                <p class="tv-indicator__empty">{{ __('trading.notices.unsupported') }}</p>
                            @else
                                <p class="tv-indicator__value">{{ $frame['rsi']['display'] }}</p>
                                <div class="tv-indicator__track" aria-hidden="true">
                                    <span class="tv-indicator__fill is-{{ $frame['rsi']['tone'] }}" style="width: {{ $frame['rsi']['width'] }}%"></span>
                                </div>
                                <p class="tv-indicator__badge is-{{ $frame['rsi']['tone'] }}">{{ __('trading.rsi.'.$frame['rsi']['tone']) }}</p>
                            @endif
                        </div>
                    </section>
                @endforeach
            </div>
        </section>

        <section class="tv-indicators" aria-label="{{ __('trading.sections.macd') }}">
            <p class="tv-indicators__note">{{ __('trading.notes.macd') }}</p>
            <div class="tv-workspace__gauges">
                @foreach ($indicatorFrames as $frame)
                    <section class="tv-panel tv-panel--indicator" wire:key="macd-{{ $symbol }}-{{ $frame['label'] }}">
                        <div class="tv-panel__label">
                            <span class="tv-panel__frame">{{ $frame['label'] }}</span>
                            <span class="tv-panel__name">{{ __('trading.sections.macd') }}</span>
                        </div>
                        <div class="tv-panel__host tv-panel__host--indicator tv-indicator">
                            @if ($frame['macd'] === null)
                                <p class="tv-indicator__empty">{{ __('trading.notices.unsupported') }}</p>
                            @else
                                <p class="tv-indicator__badge is-{{ $frame['macd']['tone'] }}">{{ __('trading.macd.'.$frame['macd']['tone']) }}</p>
                                <p class="tv-indicator__histogram">{{ __('trading.histogram', ['value' => $frame['macd']['display']]) }}</p>
                                <div class="tv-macd-track" aria-hidden="true">
                                    <span
                                        class="tv-macd-fill is-{{ $frame['macd']['tone'] }}"
                                        style="width: {{ $frame['macd']['strength'] / 2 }}%"
                                    ></span>
                                </div>
                            @endif
                        </div>
                    </section>
                @endforeach
            </div>
        </section>

        <section class="tv-indicators" aria-label="{{ __('trading.sections.ma') }}">
            <p class="tv-indicators__note">{{ __('trading.notes.ma') }}</p>
            <div class="tv-workspace__gauges">
                @foreach ($indicatorFrames as $frame)
                    <section class="tv-panel tv-panel--ma" wire:key="ma-{{ $symbol }}-{{ $frame['label'] }}">
                        <div class="tv-panel__label">
                            <span class="tv-panel__frame">{{ $frame['label'] }}</span>
                            <span class="tv-panel__name">{{ __('trading.sections.ma') }}</span>
                        </div>
                        <div class="tv-panel__host tv-panel__host--indicator tv-ma">
                            @if (($frame['ma'] ?? null) === null)
                                <p class="tv-indicator__empty">{{ __('trading.notices.unsupported') }}</p>
                            @else
                                <span class="tv-ma__icon is-{{ $frame['ma']['tone'] }}" aria-hidden="true">
                                    @if ($frame['ma']['tone'] === 'buy')
                                        ▲
                                    @elseif ($frame['ma']['tone'] === 'sell')
                                        ▼
                                    @else
                                        ◆
                                    @endif
                                </span>
                                <p class="tv-ma__status is-{{ $frame['ma']['tone'] }}">{{ __('trading.ma.'.$frame['ma']['key']) }}</p>
                                <div class="tv-ma__meta">
                                    <span>{{ __('trading.ma.ema50') }} {{ $frame['ma']['short'] }}</span>
                                    <span>{{ __('trading.ma.ema200') }} {{ $frame['ma']['long'] }}</span>
                                </div>
                            @endif
                        </div>
                    </section>
                @endforeach
            </div>
        </section>

        <section class="tv-indicators" aria-label="{{ __('trading.sections.bollinger') }}">
            <p class="tv-indicators__note">{{ __('trading.notes.bollinger') }}</p>
            <div class="tv-workspace__gauges">
                @foreach ($indicatorFrames as $frame)
                    <section class="tv-panel tv-panel--bollinger" wire:key="bb-{{ $symbol }}-{{ $frame['label'] }}">
                        <div class="tv-panel__label">
                            <span class="tv-panel__frame">{{ $frame['label'] }}</span>
                            <span class="tv-panel__name">{{ __('trading.sections.bollinger') }}</span>
                        </div>
                        <div class="tv-panel__host tv-panel__host--indicator tv-bb">
                            @if (($frame['bollinger'] ?? null) === null)
                                <p class="tv-indicator__empty">{{ __('trading.notices.unsupported') }}</p>
                            @else
                                <p class="tv-bb__badge is-{{ $frame['bollinger']['tone'] }}">{{ __('trading.bollinger.'.$frame['bollinger']['key']) }}</p>
                                <div class="tv-bb__meter" aria-hidden="true">
                                    <span class="tv-bb__band"></span>
                                    <span class="tv-bb__mid"></span>
                                    <span class="tv-bb__price" style="left: {{ $frame['bollinger']['position'] }}%"></span>
                                </div>
                                <div class="tv-bb__scale" aria-hidden="true">
                                    <span>{{ __('trading.bollinger.lower') }}</span>
                                    <span>{{ __('trading.bollinger.basis') }}</span>
                                    <span>{{ __('trading.bollinger.upper') }}</span>
                                </div>
                                <p class="tv-bb__width">{{ __('trading.bollinger.width', ['value' => $frame['bollinger']['width_display']]) }}</p>
                            @endif
                        </div>
                    </section>
                @endforeach
            </div>
        </section>

    </div>

    @script
    <script>
        Alpine.data('tradingViewWorkspace', (config) => ({
            symbol: config.symbol,
            symbolNotice: false,
            gaugeReady: false,
            investingPairs: config.investingPairs,
            myfxbookSentimentSymbols: config.myfxbookSentimentSymbols ?? {},
            myfxbookOutlookEmbedBase: config.myfxbookOutlookEmbedBase ?? '',
            symbolLabels: config.symbolLabels ?? {},
            investingGroups: {
                forex: [1, 2, 3, 5, 7, 9, 10],
                commodities: [8830, 8836, 8831, 8849, 8833, 8862, 8832],
                indices: [166, 172, 27, 179, 170, 174, 175],
                stocks: [345, 346, 347, 348, 349, 350, 352],
            },
            investingTabs: {
                forex: '1',
                commodities: '2',
                indices: '3',
                stocks: '4',
            },
            theme: 'light',
            observer: null,
            resizeObserver: null,
            fittedWidth: 0,
            onWidgetMessage: null,
            renderToken: 0,

            init() {
                this.theme = this.resolveTheme()
                this.onWidgetMessage = (event) => this.handleWidgetMessage(event)
                window.addEventListener('message', this.onWidgetMessage)
                this.$nextTick(() => this.renderAllWidgets())

                this.observer = new MutationObserver(() => {
                    const nextTheme = this.resolveTheme()

                    if (nextTheme !== this.theme) {
                        this.theme = nextTheme
                        this.renderAllWidgets()
                    }
                })

                this.observer.observe(document.documentElement, {
                    attributes: true,
                    attributeFilter: ['class'],
                })

                this.resizeObserver = new ResizeObserver(() => this.fitMountedWidgets())
                this.resizeObserver.observe(this.$root)

                this.$watch('symbol', () => {
                    this.renderAllWidgets()
                })
            },

            destroy() {
                window.removeEventListener('message', this.onWidgetMessage)
                this.observer?.disconnect()
                this.resizeObserver?.disconnect()
            },

            handleWidgetMessage(event) {
                const name = event?.data?.name

                if (name === 'tv-widget-ready' || name === 'tv-widget-load') {
                    this.gaugeReady = true
                    this.symbolNotice = false

                    return
                }

                if (name === 'tv-widget-no-data' && ! this.gaugeReady) {
                    this.symbolNotice = true
                }
            },

            resolveTheme() {
                return document.documentElement.classList.contains('dark') ? 'dark' : 'light'
            },

            setSymbol(symbol) {
                if (! symbol || symbol === this.symbol) {
                    this.renderAllWidgets()

                    return
                }

                this.symbol = symbol
                this.$wire.set('symbol', symbol)
            },

            renderAllWidgets() {
                this.symbolNotice = false
                this.gaugeReady = false
                const token = ++this.renderToken

                this.$nextTick(() => {
                    if (token !== this.renderToken) {
                        return
                    }

                    this.$root.querySelectorAll('.tv-panel__host--gauge[data-interval]').forEach((host) => {
                        this.mountTechnicalAnalysis(host, host.dataset.interval)
                    })

                    this.mountInvestingSummaries()
                    this.fittedWidth = this.$root.clientWidth
                })
            },

            fitMountedWidgets() {
                const width = this.$root.clientWidth

                if (! width || width === this.fittedWidth) {
                    return
                }

                this.fittedWidth = width

                this.$root.querySelectorAll('.tv-panel__host--gauge[data-interval]').forEach((host) => {
                    const container = host.querySelector('.tv-gauge-frame')

                    if (container) {
                        this.positionGauge(host, container)
                    }
                })

                this.$root.querySelectorAll('.tv-investing__frame').forEach((frame) => {
                    const host = frame.closest('.tv-panel__host--investing')

                    if (host && frame.getAttribute('src')) {
                        this.fitInvestingFrame(frame, host)
                    }
                })
            },

            investingSelection() {
                return this.investingPairs[this.symbol] ?? null
            },

            sentimentUrl() {
                const active = this.myfxbookSentimentSymbols[this.symbol]

                if (! active || ! this.myfxbookOutlookEmbedBase) {
                    return null
                }

                return String(this.myfxbookOutlookEmbedBase).replace(/\/$/, '') + '/' + String(active)
            },

            sentimentTitle() {
                const label = this.symbolLabels[this.symbol] ?? this.symbol

                return @js(__('trading.sections.sentiment')) + ' · ' + label
            },

            pairList(selectedId, fallback) {
                const ids = []
                const source = selectedId ? [selectedId, ...fallback] : fallback

                source.forEach((id) => {
                    const number = Number(id)

                    if (! Number.isInteger(number) || ids.includes(number) || ids.length >= 7) {
                        return
                    }

                    ids.push(number)
                })

                return ids.join(',')
            },

            investingWidgetUrl(timeframe) {
                const selected = this.investingSelection()

                if (! selected || ! timeframe || ! this.investingGroups[selected.group]) {
                    return ''
                }

                const params = new URLSearchParams({
                    timeframe: String(timeframe),
                    lang: '1',
                    forex: this.pairList(selected.group === 'forex' ? selected.id : null, this.investingGroups.forex),
                    commodities: this.pairList(selected.group === 'commodities' ? selected.id : null, this.investingGroups.commodities),
                    indices: this.pairList(selected.group === 'indices' ? selected.id : null, this.investingGroups.indices),
                    stocks: this.pairList(selected.group === 'stocks' ? selected.id : null, this.investingGroups.stocks),
                    tabs: this.investingTabs[selected.group],
                })

                return 'https://ssltsw.investing.com/?' + params.toString()
            },

            mountInvestingSummaries() {
                this.$root.querySelectorAll('.tv-investing__frame').forEach((frame) => {
                    const host = frame.closest('.tv-panel__host--investing')
                    const next = this.investingWidgetUrl(frame.dataset.timeframe)

                    if (! next) {
                        frame.removeAttribute('src')

                        return
                    }

                    if (frame.getAttribute('src') !== next) {
                        frame.setAttribute('src', next)
                    }

                    this.fitInvestingFrame(frame, host)
                })
            },

            fitInvestingFrame(frame, host) {
                const clip = frame.parentElement
                const summaryWidth = 282
                const summaryHeight = 162
                const cardWidth = Math.max(host?.clientWidth || 0, 120)
                const scale = cardWidth / summaryWidth

                host.style.height = Math.ceil(summaryHeight * scale) + 'px'
                clip.style.width = '320px'
                clip.style.height = '430px'
                clip.style.transformOrigin = 'top left'
                clip.style.transform = `scale(${scale})`
            },

            mountTechnicalAnalysis(host, interval) {
                this.mountWidget(host, 'https://s3.tradingview.com/external-embedding/embed-widget-technical-analysis.js', {
                    interval,
                    width: 425,
                    height: 450,
                    isTransparent: true,
                    symbol: this.symbol,
                    showIntervalTabs: false,
                    displayMode: 'single',
                    locale: 'en',
                    colorTheme: this.theme,
                })

                const container = host.querySelector('.tradingview-widget-container')

                if (! container) {
                    return
                }

                container.classList.add('tv-gauge-frame')
                this.positionGauge(host, container)
            },

            positionGauge(host, container) {
                const naturalWidth = 425
                const naturalHeight = 450
                const cardWidth = Math.max(host?.clientWidth || 0, 120)
                const cardHeight = Math.max(host?.clientHeight || 0, 150)
                const scale = cardWidth / naturalWidth
                const visibleSpan = cardHeight / scale
                const contentCenter = 228
                const offset = Math.max(0, Math.min(
                    naturalHeight - visibleSpan,
                    contentCenter - (visibleSpan / 2),
                ))

                container.style.left = '50%'
                container.style.marginLeft = (-naturalWidth / 2) + 'px'
                container.style.top = '0'
                container.style.transformOrigin = 'top center'
                container.style.transform = `scale(${scale}) translateY(${-offset}px)`
            },

            mountWidget(host, src, options) {
                if (! host) {
                    return
                }

                host.innerHTML = ''

                const container = document.createElement('div')
                container.className = 'tradingview-widget-container'
                container.style.width = '100%'
                container.style.height = '100%'

                const widget = document.createElement('div')
                widget.className = 'tradingview-widget-container__widget'
                widget.style.width = '100%'
                widget.style.height = '100%'

                const script = document.createElement('script')
                script.type = 'text/javascript'
                script.src = src
                script.async = true
                script.textContent = JSON.stringify(options)

                const frameObserver = new MutationObserver(() => {
                    host.querySelectorAll('iframe').forEach((frame) => {
                        frame.setAttribute('scrolling', 'no')
                    })
                })

                frameObserver.observe(host, { childList: true, subtree: true })
                setTimeout(() => frameObserver.disconnect(), 8000)

                container.appendChild(widget)
                container.appendChild(script)
                host.appendChild(container)
            },
        }))
    </script>
    @endscript
</x-filament-panels::page>
