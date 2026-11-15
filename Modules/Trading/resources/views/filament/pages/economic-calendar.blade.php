<x-filament-panels::page>
    <section
        class="ff-calendar mx-auto w-full max-w-[100rem] px-2 sm:px-3 lg:px-4"
        aria-label="{{ __('trading.nav.economic_calendar') }}"
        x-data="{
            clock: '',
            offsetHours: -(new Date().getTimezoneOffset() / 60),
            tick() {
                const now = new Date();
                const offsetMinutes = -now.getTimezoneOffset();
                const sign = offsetMinutes >= 0 ? '+' : '-';
                const abs = Math.abs(offsetMinutes);
                const hours = String(Math.floor(abs / 60)).padStart(2, '0');
                const minutes = String(abs % 60).padStart(2, '0');
                const time = now.toLocaleTimeString([], {
                    hour: '2-digit',
                    minute: '2-digit',
                    hour12: false,
                });

                this.offsetHours = offsetMinutes / 60;
                this.clock = `${time} (GMT ${sign}${hours}:${minutes})`;
            },
        }"
        x-init="
            tick();
            setInterval(() => tick(), 30000);
            $wire.syncInvestingTimeZoneFromBrowser(offsetHours);
        "
    >
        @php
            $nextHighImpactEvent = $this->nextHighImpactUsdEvent();
            $todaysHighImpactEvent = $this->todaysHighImpactUsdHighlight();
            $upcomingHighImpactEvents = $this->upcomingHighImpactUsdEvents();
        @endphp

        <div class="ff-calendar__split">
            <aside
                wire:key="highlights-{{ $nextHighImpactEvent['iso'] ?? 'none' }}-{{ $investingRange }}-{{ $this->selectedInvestingImpactFilter() }}-{{ $this->selectedInvestingCurrencyFilter() }}"
                class="ff-calendar__rail ff-calendar__highlights"
                aria-label="{{ __('trading.calendar.countdown.title') }}"
                @if ($nextHighImpactEvent)
                    x-data="{
                        targetMs: Date.parse(@js($nextHighImpactEvent['iso'])),
                        days: '00',
                        hours: '00',
                        minutes: '00',
                        seconds: '00',
                        tickCountdown() {
                            const diff = Math.max(0, this.targetMs - Date.now());
                            const totalSeconds = Math.floor(diff / 1000);
                            const days = Math.floor(totalSeconds / 86400);
                            const hours = Math.floor((totalSeconds % 86400) / 3600);
                            const minutes = Math.floor((totalSeconds % 3600) / 60);
                            const seconds = totalSeconds % 60;
                            const pad = (value) => String(value).padStart(2, '0');

                            this.days = pad(days);
                            this.hours = pad(hours);
                            this.minutes = pad(minutes);
                            this.seconds = pad(seconds);
                        },
                    }"
                    x-init="tickCountdown(); setInterval(() => tickCountdown(), 1000)"
                @endif
            >
                <div class="ff-calendar__countdown">
                    <div class="ff-calendar__countdown-header">
                        <span class="ff-calendar__countdown-live" aria-hidden="true">
                            <span class="ff-calendar__countdown-live-dot"></span>
                            {{ __('trading.calendar.countdown.live') }}
                        </span>
                        <p class="ff-calendar__countdown-title">{{ __('trading.calendar.countdown.title') }}</p>
                        <p class="ff-calendar__countdown-subtitle">{{ __('trading.calendar.countdown.subtitle') }}</p>
                    </div>

                    @if ($nextHighImpactEvent)
                        <div class="ff-calendar__countdown-event">
                            <strong>{{ $nextHighImpactEvent['name'] }}</strong>
                            <span>{{ $nextHighImpactEvent['when'] }}</span>
                        </div>

                        <div class="ff-calendar__countdown-timer" role="timer" aria-live="polite">
                            <div class="ff-calendar__countdown-unit">
                                <strong x-text="days">00</strong>
                                <span>{{ __('trading.calendar.countdown.days') }}</span>
                            </div>
                            <div class="ff-calendar__countdown-unit">
                                <strong x-text="hours">00</strong>
                                <span>{{ __('trading.calendar.countdown.hours') }}</span>
                            </div>
                            <div class="ff-calendar__countdown-unit">
                                <strong x-text="minutes">00</strong>
                                <span>{{ __('trading.calendar.countdown.minutes') }}</span>
                            </div>
                            <div class="ff-calendar__countdown-unit">
                                <strong x-text="seconds">00</strong>
                                <span>{{ __('trading.calendar.countdown.seconds') }}</span>
                            </div>
                        </div>
                    @else
                        <p class="ff-calendar__countdown-empty">{{ __('trading.calendar.countdown.empty') }}</p>
                    @endif
                </div>

                @if ($todaysHighImpactEvent)
                    <div class="ff-calendar__today-banner" role="status">
                        <span class="ff-calendar__today-badge">{{ __('trading.calendar.countdown.today_badge') }}</span>
                        <p class="ff-calendar__today-title">{{ __('trading.calendar.countdown.today_title') }}</p>
                        <strong class="ff-calendar__today-name">{{ $todaysHighImpactEvent['name'] }}</strong>
                        <span class="ff-calendar__today-when">{{ $todaysHighImpactEvent['when'] }}</span>
                    </div>
                @endif

                @if ($upcomingHighImpactEvents !== [])
                    <div class="ff-calendar__upcoming">
                        <p class="ff-calendar__upcoming-title">{{ __('trading.calendar.countdown.upcoming') }}</p>
                        <ul class="ff-calendar__upcoming-list">
                            @foreach ($upcomingHighImpactEvents as $event)
                                <li class="ff-calendar__upcoming-item {{ $event['is_today'] ? 'is-today' : '' }}">
                                    <span class="ff-calendar__upcoming-dot" aria-hidden="true"></span>
                                    <div class="ff-calendar__upcoming-copy">
                                        <strong>{{ $event['name'] }}</strong>
                                        <span>{{ $event['when'] }}</span>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </aside>

            <article class="ff-calendar__card ff-calendar__card--investing">
                <header class="ff-calendar__card-header">
                    <div class="ff-calendar__filters" role="toolbar" aria-label="{{ __('trading.calendar.investing_filters.label') }}">
                        <div class="ff-calendar__filter-cell" role="group" aria-label="{{ __('trading.calendar.investing_filters.range_label') }}">
                            <label class="ff-calendar__select-label" for="investing-range-filter">
                                {{ __('trading.calendar.filters.day_label') }}
                            </label>
                            <select
                                id="investing-range-filter"
                                class="ff-calendar__select"
                                wire:change="setInvestingRange($event.target.value)"
                            >
                                <option value="day" @selected($investingRange === 'day')>
                                    {{ __('trading.calendar.filters.today') }}
                                </option>
                                <option value="week" @selected($investingRange === 'week')>
                                    {{ __('trading.calendar.filters.week') }}
                                </option>
                            </select>
                        </div>

                        <div class="ff-calendar__filter-cell" role="group" aria-label="{{ __('trading.calendar.investing_filters.impact_label') }}">
                            <label class="ff-calendar__select-label" for="investing-impact-filter">
                                {{ __('trading.calendar.investing_filters.impact_label') }}
                            </label>
                            <select
                                id="investing-impact-filter"
                                class="ff-calendar__select"
                                wire:change="setInvestingImpactFilter($event.target.value)"
                            >
                                <option value="all" @selected($this->selectedInvestingImpactFilter() === 'all')>
                                    {{ __('trading.calendar.investing_filters.all_currencies') }}
                                </option>
                                @foreach (['high', 'medium', 'low'] as $impact)
                                    <option value="{{ $impact }}" @selected($this->selectedInvestingImpactFilter() === $impact)>
                                        {{ __('trading.calendar.impact.'.$impact) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="ff-calendar__filter-cell" role="group" aria-label="{{ __('trading.calendar.investing_filters.currency_label') }}">
                            <label class="ff-calendar__select-label" for="investing-currency-filter">
                                {{ __('trading.calendar.columns.currency') }}
                            </label>
                            <select
                                id="investing-currency-filter"
                                class="ff-calendar__select"
                                wire:change="setInvestingCurrencyFilter($event.target.value)"
                            >
                                <option value="all" @selected($this->selectedInvestingCurrencyFilter() === 'all')>
                                    {{ __('trading.calendar.investing_filters.all_currencies') }}
                                </option>
                                @foreach ($this->investingCurrencyOptions() as $currency)
                                    <option value="{{ $currency }}" @selected($this->selectedInvestingCurrencyFilter() === $currency)>
                                        {{ $currency }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="ff-calendar__filter-cell ff-calendar__filter-cell--clock">
                            <span class="ff-calendar__select-label">
                                {{ __('trading.calendar.investing_filters.timezone_label') }}
                            </span>
                            <div
                                class="ff-calendar__clock"
                                role="status"
                                aria-live="polite"
                                aria-label="{{ __('trading.calendar.investing_filters.timezone_label') }}"
                                title="{{ __('trading.calendar.investing_filters.timezone_note') }}"
                            >
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                    <circle cx="12" cy="12" r="8.5" />
                                    <path d="M12 7.5V12l3 2" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                                <strong x-text="clock"></strong>
                            </div>
                        </div>
                    </div>
                </header>

                <div
                    wire:key="investing-calendar-{{ $investingRange }}-{{ $investingTimeZone }}-{{ $investingAllImpacts ? 'all' : implode('-', $investingImpacts) }}-{{ $investingAllCurrencies ? 'all' : implode('-', $investingCurrencies) }}"
                    class="ff-calendar__investing"
                    data-range="{{ $investingRange }}"
                >
                    <iframe
                        class="ff-calendar__investing-frame"
                        src="{{ $this->investingCalendarUrl() }}"
                        title="{{ __('trading.calendar.sources.investing') }}"
                        loading="lazy"
                        referrerpolicy="no-referrer-when-downgrade"
                    ></iframe>
                </div>
            </article>

            <aside class="ff-calendar__side" aria-label="{{ __('trading.calendar.legend.title') }}">
                <div
                    class="ff-calendar__legend"
                    aria-label="{{ __('trading.calendar.legend.title') }}"
                >
                    <div class="ff-calendar__legend-header">
                        <p class="ff-calendar__legend-title">{{ __('trading.calendar.legend.title') }}</p>
                    </div>

                    <div class="ff-calendar__legend-grid">
                        <section class="ff-calendar__legend-group" aria-label="{{ __('trading.calendar.legend.event_types') }}">
                            <h3 class="ff-calendar__legend-heading">{{ __('trading.calendar.legend.event_types') }}</h3>

                            <ul class="ff-calendar__legend-list">
                                <li class="ff-calendar__legend-item">
                                    <span class="ff-calendar__legend-icon" aria-hidden="true">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M3 10.5v3a1 1 0 0 0 1 1h1.6l7.4 3.2V6.3L5.6 9.5H4a1 1 0 0 0-1 1Z" />
                                            <path d="M5.2 14.5V17a1.6 1.6 0 0 0 3.2 0v-1.2" />
                                            <path d="M15.2 9.2a3.4 3.4 0 0 1 0 5.6" />
                                        </svg>
                                    </span>
                                    <span>{{ __('trading.calendar.legend.speech') }}</span>
                                </li>
                                <li class="ff-calendar__legend-item">
                                    <span class="ff-calendar__legend-icon ff-calendar__legend-icon--letter" aria-hidden="true">P</span>
                                    <span>{{ __('trading.calendar.legend.preliminary') }}</span>
                                </li>
                                <li class="ff-calendar__legend-item">
                                    <span class="ff-calendar__legend-icon" aria-hidden="true">
                                        <span class="ff-calendar__legend-dot"></span>
                                    </span>
                                    <span>{{ __('trading.calendar.legend.revised') }}</span>
                                </li>
                                <li class="ff-calendar__legend-item">
                                    <span class="ff-calendar__legend-icon" aria-hidden="true">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
                                            <path d="M7 3h10" />
                                            <path d="M7 21h10" />
                                            <path d="M8 3c0 5 8 5 8 10s-8 5-8 10" />
                                            <path d="M16 3c0 5-8 5-8 10s8 5 8 10" />
                                        </svg>
                                    </span>
                                    <span>{{ __('trading.calendar.legend.retrieving') }}</span>
                                </li>
                            </ul>
                        </section>

                        <section class="ff-calendar__legend-group" aria-label="{{ __('trading.calendar.legend.volatility') }}">
                            <h3 class="ff-calendar__legend-heading">{{ __('trading.calendar.legend.volatility') }}</h3>

                            <ul class="ff-calendar__legend-list">
                                <li class="ff-calendar__legend-item">
                                    <span class="ff-calendar__legend-bulls" aria-hidden="true">
                                        <span class="is-on"></span><span></span><span></span>
                                    </span>
                                    <span>{{ __('trading.calendar.legend.volatility_low') }}</span>
                                </li>
                                <li class="ff-calendar__legend-item">
                                    <span class="ff-calendar__legend-bulls" aria-hidden="true">
                                        <span class="is-on"></span><span class="is-on"></span><span></span>
                                    </span>
                                    <span>{{ __('trading.calendar.legend.volatility_medium') }}</span>
                                </li>
                                <li class="ff-calendar__legend-item">
                                    <span class="ff-calendar__legend-bulls" aria-hidden="true">
                                        <span class="is-on"></span><span class="is-on"></span><span class="is-on"></span>
                                    </span>
                                    <span>{{ __('trading.calendar.legend.volatility_high') }}</span>
                                </li>
                            </ul>
                        </section>
                    </div>
                </div>

                <div
                    class="ff-calendar__usd-ref"
                    aria-label="{{ __('trading.calendar.usd_reference.title') }}"
                >
                    <div class="ff-calendar__usd-ref-header">
                        <span class="ff-calendar__usd-ref-pulse" aria-hidden="true"></span>
                        <p class="ff-calendar__usd-ref-title">{{ __('trading.calendar.usd_reference.title') }}</p>
                    </div>

                    <ol class="ff-calendar__usd-ref-list">
                        @foreach (__('trading.calendar.usd_reference.items') as $key => $item)
                            <li @class([
                                'ff-calendar__usd-ref-item',
                                'is-next' => ($nextHighImpactEvent['key'] ?? null) === $key,
                            ])>
                                <span class="ff-calendar__usd-ref-marker" aria-hidden="true">
                                    <span class="ff-calendar__usd-ref-dot"></span>
                                    <span class="ff-calendar__usd-ref-number">{{ $loop->iteration }}</span>
                                </span>
                                <div class="ff-calendar__usd-ref-copy">
                                    <strong class="ff-calendar__usd-ref-name">{{ $item['name'] }}</strong>
                                    <span class="ff-calendar__usd-ref-desc">{{ $item['description'] }}</span>
                                </div>
                            </li>
                        @endforeach
                    </ol>
                </div>
            </aside>
        </div>
    </section>
</x-filament-panels::page>
