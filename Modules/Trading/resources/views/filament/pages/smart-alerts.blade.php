<x-filament-panels::page>
    <section class="ff-alerts" aria-label="{{ __('trading.nav.smart_alerts') }}">
        @php
            $rules = $this->alertRules();
            $activity = $this->activityFeed();
            $currencyOptions = $this->currencyOptions();
        @endphp

        <div class="ff-alerts__split">
            <aside class="ff-alerts__rules" aria-labelledby="smart-alerts-rules-title">
                <header class="ff-alerts__panel-header">
                    <span class="ff-alerts__eyebrow">{{ __('trading.smart_alerts.rules.eyebrow') }}</span>
                    <h2 id="smart-alerts-rules-title" class="ff-alerts__title">
                        {{ __('trading.smart_alerts.rules.title') }}
                    </h2>
                    <p class="ff-alerts__subtitle">{{ __('trading.smart_alerts.rules.subtitle') }}</p>
                </header>

                <ul class="ff-alerts__rule-list">
                    @foreach ($rules as $rule)
                        <li class="ff-alerts__rule {{ $rule['enabled'] ? 'is-on' : 'is-off' }}">
                            <div class="ff-alerts__rule-copy">
                                <strong>{{ $rule['label'] }}</strong>
                                <span>{{ $rule['description'] }}</span>
                            </div>

                            <button
                                type="button"
                                class="ff-alerts__toggle {{ $rule['enabled'] ? 'is-on' : '' }}"
                                wire:click="{{ $rule['action'] }}"
                                wire:key="rule-{{ $rule['key'] }}"
                                role="switch"
                                aria-checked="{{ $rule['enabled'] ? 'true' : 'false' }}"
                                aria-label="{{ $rule['label'] }}"
                            >
                                <span class="ff-alerts__toggle-knob" aria-hidden="true"></span>
                            </button>
                        </li>
                    @endforeach
                </ul>

                <div class="ff-alerts__currencies" role="group" aria-label="{{ __('trading.smart_alerts.currencies.label') }}">
                    <p class="ff-alerts__currencies-title">{{ __('trading.smart_alerts.currencies.label') }}</p>
                    <p class="ff-alerts__currencies-note">{{ __('trading.smart_alerts.currencies.note') }}</p>

                    <div class="ff-alerts__currency-chips">
                        @foreach ($currencyOptions as $currency)
                            <button
                                type="button"
                                class="ff-alerts__chip {{ in_array($currency, $currencies, true) ? 'is-on' : '' }}"
                                wire:click="toggleCurrency('{{ $currency }}')"
                                wire:key="currency-{{ $currency }}"
                                aria-pressed="{{ in_array($currency, $currencies, true) ? 'true' : 'false' }}"
                            >
                                {{ $currency }}
                            </button>
                        @endforeach
                    </div>
                </div>
            </aside>

            <article class="ff-alerts__activity" aria-labelledby="smart-alerts-activity-title">
                <header class="ff-alerts__panel-header ff-alerts__panel-header--row">
                    <div>
                        <span class="ff-alerts__eyebrow">{{ __('trading.smart_alerts.activity.eyebrow') }}</span>
                        <h2 id="smart-alerts-activity-title" class="ff-alerts__title">
                            {{ __('trading.smart_alerts.activity.title') }}
                        </h2>
                        <p class="ff-alerts__subtitle">{{ __('trading.smart_alerts.activity.subtitle') }}</p>
                    </div>

                    <span class="ff-alerts__count" aria-live="polite">
                        {{ trans_choice('trading.smart_alerts.activity.count', count($activity), ['count' => count($activity)]) }}
                    </span>
                </header>

                @if ($activity === [])
                    <div class="ff-alerts__empty" role="status">
                        <strong>{{ __('trading.smart_alerts.activity.empty_title') }}</strong>
                        <span>{{ __('trading.smart_alerts.activity.empty_body') }}</span>
                    </div>
                @else
                    <div class="ff-alerts__table-wrap">
                        <table class="ff-alerts__table">
                            <thead>
                                <tr>
                                    <th scope="col">{{ __('trading.smart_alerts.columns.status') }}</th>
                                    <th scope="col">{{ __('trading.smart_alerts.columns.when') }}</th>
                                    <th scope="col">{{ __('trading.smart_alerts.columns.impact') }}</th>
                                    <th scope="col">{{ __('trading.smart_alerts.columns.event') }}</th>
                                    <th scope="col">{{ __('trading.smart_alerts.columns.kind') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($activity as $alert)
                                    <tr wire:key="alert-{{ $alert['id'] }}" class="ff-alerts__row is-{{ $alert['status'] }}">
                                        <td>
                                            <span class="ff-alerts__status is-{{ $alert['status'] }}">
                                                {{ $alert['status_label'] }}
                                            </span>
                                        </td>
                                        <td>
                                            <time datetime="{{ $alert['iso'] }}">{{ $alert['when'] }}</time>
                                        </td>
                                        <td>
                                            <span class="ff-alerts__impact">
                                                <span class="ff-alerts__impact-dot" aria-hidden="true"></span>
                                                {{ $alert['currency'] }} · {{ $alert['impact_label'] }}
                                            </span>
                                        </td>
                                        <td>
                                            <div class="ff-alerts__event">
                                                <strong>{{ $alert['title'] }}</strong>
                                                <span>{{ $alert['description'] }}</span>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="ff-alerts__kind">{{ $alert['kind_label'] }}</span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <ul class="ff-alerts__cards" aria-label="{{ __('trading.smart_alerts.activity.title') }}">
                        @foreach ($activity as $alert)
                            <li wire:key="alert-card-{{ $alert['id'] }}" class="ff-alerts__card is-{{ $alert['status'] }}">
                                <div class="ff-alerts__card-top">
                                    <span class="ff-alerts__status is-{{ $alert['status'] }}">
                                        {{ $alert['status_label'] }}
                                    </span>
                                    <time datetime="{{ $alert['iso'] }}">{{ $alert['when'] }}</time>
                                </div>
                                <strong class="ff-alerts__card-title">{{ $alert['title'] }}</strong>
                                <p class="ff-alerts__card-copy">{{ $alert['description'] }}</p>
                                <div class="ff-alerts__card-meta">
                                    <span class="ff-alerts__impact">
                                        <span class="ff-alerts__impact-dot" aria-hidden="true"></span>
                                        {{ $alert['currency'] }} · {{ $alert['impact_label'] }}
                                    </span>
                                    <span class="ff-alerts__kind">{{ $alert['kind_label'] }}</span>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </article>
        </div>
    </section>
</x-filament-panels::page>
