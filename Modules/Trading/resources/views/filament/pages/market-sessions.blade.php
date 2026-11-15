<x-filament-panels::page>
    @php
        $sessionCards = $this->sessionCards();
        $timelineSessions = $this->timelineSessions();
        $timeline = $this->timelineConfig();
        $clock = $this->clockSnapshot();
        $statusClosed = __('trading.market_sessions.status_closed');
        $statusIdle = __('trading.market_sessions.status_idle');
        $statusSingle = __('trading.market_sessions.status_single');
        $statusOverlap = __('trading.market_sessions.status_overlap');
        $activePrefix = __('trading.market_sessions.active_prefix');
        $noneActive = __('trading.market_sessions.none_active');
        $labelOpen = __('trading.market_sessions.open');
        $labelClosed = __('trading.market_sessions.closed');
        $activeKeys = $clock['active_keys'];
    @endphp

    {{-- Synchronous factory so Alpine can call it even if @script has not run yet. --}}
    <script>
        @include('trading::filament.pages.partials.market-sessions-clock-script')
    </script>

    <section
        class="ff-sessions"
        aria-label="{{ __('trading.nav.market_sessions') }}"
        x-data="ffMarketSessionsClock({
            sessions: @js($timeline['sessions']),
            local_clock: @js($clock['local_clock']),
            local_clock_short: @js($clock['local_clock_short']),
            now_percent: {{ $clock['now_percent'] }},
            current_pkt_hour: {{ $clock['current_pkt_hour'] }},
            forex_week_open: {{ $clock['forex_week_open'] ? 'true' : 'false' }},
            active_keys: @js($clock['active_keys']),
            active_names: @js($clock['active_names']),
            overlap_status: @js($clock['overlap_status']),
            track_label: @js($clock['track_label']),
            status_closed: @js($statusClosed),
            status_idle: @js($statusIdle),
            status_single: @js($statusSingle),
            status_overlap: @js($statusOverlap),
            active_prefix: @js($activePrefix),
            none_active: @js($noneActive),
        })"
    >
        <header class="ff-sessions__hero">
            <div class="ff-sessions__hero-copy">
                <h2 class="ff-sessions__title">{{ __('trading.market_sessions.title') }}</h2>
            </div>

            <div class="ff-sessions__clock-meta">
                <span class="ff-sessions__live">
                    <span class="ff-sessions__live-dot" aria-hidden="true"></span>
                    {{ __('trading.market_sessions.live') }}
                </span>
                <strong dir="ltr" x-text="localClock">{{ $clock['local_clock'] }}</strong>
                <span>{{ __('trading.market_sessions.pkt_label') }}</span>
                <span
                    class="ff-sessions__overlap-status{{ count($activeKeys) >= 2 ? ' is-overlap' : (count($activeKeys) === 1 ? ' is-single' : ' is-idle') }}"
                    role="status"
                    aria-live="polite"
                    :class="{
                        'is-overlap': activeKeys.length >= 2,
                        'is-single': activeKeys.length === 1,
                        'is-idle': activeKeys.length === 0,
                    }"
                    :title="overlapStatus"
                    x-text="overlapStatus"
                >{{ $clock['overlap_status'] }}</span>
            </div>
        </header>

        <article class="ff-sessions__gauge">
            <p
                class="ff-sessions__weekend"
                @if ($clock['forex_week_open'])
                    x-cloak
                    style="display: none;"
                @endif
                x-show="!forexWeekOpen"
                role="status"
            >
                <strong>{{ __('trading.market_sessions.weekend_banner') }}</strong>
            </p>

            <div
                class="ff-sessions__track{{ $clock['forex_week_open'] ? '' : ' is-weekend' }}{{ count($activeKeys) >= 2 ? ' is-overlap' : '' }}"
                dir="ltr"
                :class="{
                    'is-weekend': !forexWeekOpen,
                    'is-overlap': activeKeys.length >= 2,
                }"
                :aria-label="trackLabel"
            >
                @foreach ($timelineSessions as $session)
                    @php
                        $sessionIsActive = in_array($session['key'], $activeKeys, true);
                    @endphp
                    <div
                        class="ff-sessions__lane{{ $sessionIsActive ? ' is-active' : '' }}"
                        wire:key="session-lane-{{ $session['key'] }}"
                        :class="{ 'is-active': activeKeys.includes(@js($session['key'])) }"
                    >
                        <div class="ff-sessions__lane-label">
                            <span class="ff-sessions__lane-swatch" style="background: {{ $session['color'] }}"></span>
                            <strong>{{ $session['name'] }}</strong>
                            <span
                                class="ff-sessions__lane-state"
                                x-text="activeKeys.includes(@js($session['key'])) ? @js($labelOpen) : @js($labelClosed)"
                            >{{ $sessionIsActive ? $labelOpen : $labelClosed }}</span>
                        </div>
                        <div class="ff-sessions__lane-bar">
                            @foreach ($session['segments'] as $segment)
                                <span
                                    class="ff-sessions__segment"
                                    style="left: {{ $segment['left'] }}%; width: {{ $segment['width'] }}%; background: {{ $session['color'] }}"
                                ></span>
                            @endforeach
                        </div>
                    </div>
                @endforeach

                <div class="ff-sessions__plot" aria-hidden="true">
                    <div
                        class="ff-sessions__now{{ count($activeKeys) >= 2 ? ' is-overlap' : '' }}{{ $clock['now_percent'] > 88 ? ' is-edge-right' : '' }}{{ $clock['now_percent'] < 8 ? ' is-edge-left' : '' }}"
                        :class="{
                            'is-overlap': activeKeys.length >= 2,
                            'is-edge-right': nowPercent > 88,
                            'is-edge-left': nowPercent < 8,
                        }"
                        style="left: {{ $clock['now_percent'] }}%"
                        :style="`left:${nowPercent}%`"
                    >
                        <span class="ff-sessions__now-line"></span>
                        <span class="ff-sessions__now-label">
                            <span x-text="localClockShort">{{ $clock['local_clock_short'] }}</span>
                            <span
                                class="ff-sessions__now-count"
                                @unless (count($activeKeys) > 0) x-cloak style="display: none;" @endunless
                                x-show="activeKeys.length > 0"
                                x-text="activeKeys.length + '×'"
                            >{{ count($activeKeys) > 0 ? count($activeKeys).'×' : '' }}</span>
                        </span>
                    </div>

                    <div class="ff-sessions__hours">
                        @for ($hour = 0; $hour <= 24; $hour++)
                            <span
                                class="ff-sessions__hour{{ $clock['current_pkt_hour'] === $hour ? ' is-now' : '' }}"
                                style="left: {{ ($hour / 24) * 100 }}%"
                                :class="{ 'is-now': currentPktHour === {{ $hour }} || ({{ $hour }} === 24 && nowPercent >= 99.9) }"
                            >{{ sprintf('%02d', $hour) }}</span>
                        @endfor
                    </div>
                </div>
            </div>
        </article>

        <div class="ff-sessions__cards">
            @foreach ($sessionCards as $card)
                @php
                    $cardIsActive = in_array($card['key'], $activeKeys, true);
                @endphp
                <article
                    class="ff-sessions__card{{ $cardIsActive ? ' is-active' : '' }}"
                    :class="{ 'is-active': activeKeys.includes(@js($card['key'])) }"
                    style="--session-color: {{ $card['color'] }}"
                >
                    <header class="ff-sessions__card-head">
                        <div>
                            <p class="ff-sessions__card-name">{{ $card['name'] }}</p>
                            <p class="ff-sessions__card-hours" dir="ltr">{{ $card['hours_label'] }}</p>
                        </div>
                        <span
                            class="ff-sessions__card-badge{{ $cardIsActive ? ' is-on' : '' }}"
                            :class="{ 'is-on': activeKeys.includes(@js($card['key'])) }"
                            x-text="activeKeys.includes(@js($card['key'])) ? @js($labelOpen) : @js($labelClosed)"
                        >{{ $cardIsActive ? $labelOpen : $labelClosed }}</span>
                    </header>

                    <p class="ff-sessions__card-summary">{{ $card['summary'] }}</p>

                    <div class="ff-sessions__card-meta">
                        <span class="ff-sessions__activity is-{{ $card['activity'] }}">
                            {{ $card['activity_label'] }}
                        </span>
                    </div>

                    <ul class="ff-sessions__traits">
                        @foreach ($card['traits'] as $trait)
                            <li>{{ $trait }}</li>
                        @endforeach
                    </ul>
                </article>
            @endforeach
        </div>
    </section>

    {{-- Re-bind after Livewire / Filament SPA navigations (inline script above may not re-run). --}}
    @script
    <script>
        @include('trading::filament.pages.partials.market-sessions-clock-script')
    </script>
    @endscript
</x-filament-panels::page>
