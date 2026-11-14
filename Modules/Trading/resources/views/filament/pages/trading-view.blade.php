<x-filament-panels::page>
    <div
        wire:ignore.self
        x-data="tradingViewWorkspace(@js([
            'symbol' => $symbol,
            'investingPairs' => $investingPairs,
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
                            <p class="tv-investing__empty" x-show="!investingSelection()" x-cloak>
                                {{ __('trading.notices.investing_missing') }}
                            </p>
                        </div>
                    </section>
                @endforeach
            </div>
        </section>

        <section class="tv-indicators" aria-label="{{ __('trading.sections.rsi') }}">
            <h2 class="tv-indicators__title">{{ __('trading.sections.rsi') }}</h2>
            <div class="tv-workspace__gauges">
                @foreach ($indicatorFrames as $frame)
                    <section class="tv-panel tv-panel--indicator" wire:key="rsi-{{ $symbol }}-{{ $frame['label'] }}">
                        <div class="tv-panel__label">{{ $frame['label'] }}</div>
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
            <h2 class="tv-indicators__title">{{ __('trading.sections.macd') }}</h2>
            <div class="tv-workspace__gauges">
                @foreach ($indicatorFrames as $frame)
                    <section class="tv-panel tv-panel--indicator" wire:key="macd-{{ $symbol }}-{{ $frame['label'] }}">
                        <div class="tv-panel__label">{{ $frame['label'] }}</div>
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
    </div>

    @script
    <script>
        Alpine.data('tradingViewWorkspace', (config) => ({
            symbol: config.symbol,
            symbolNotice: false,
            gaugeReady: false,
            investingPairs: config.investingPairs,
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

                this.$watch('symbol', () => {
                    this.renderAllWidgets()
                })
            },

            destroy() {
                window.removeEventListener('message', this.onWidgetMessage)
                this.observer?.disconnect()
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
                })
            },

            investingSelection() {
                return this.investingPairs[this.symbol] ?? null
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

                this.mountWidget(host, 'https://s3.tradingview.com/external-embedding/embed-widget-technical-analysis.js', {
                    interval,
                    width: naturalWidth,
                    height: naturalHeight,
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
