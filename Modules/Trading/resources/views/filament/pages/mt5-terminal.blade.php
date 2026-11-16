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

    <section class="term-desk" wire:poll.2s aria-label="{{ __('trading.nav.mt5_terminal') }}">
        <form class="term-desk__connect" wire:submit="connectMt5">
            <label>
                <span>{{ __('trading.terminal.mt5_login') }}</span>
                <input wire:model="mt5Login" inputmode="numeric" autocomplete="off" required>
            </label>
            <label>
                <span>{{ __('trading.terminal.mt5_password') }}</span>
                <input type="password" wire:model="mt5Password" autocomplete="new-password">
            </label>
            <label>
                <span>{{ __('trading.terminal.mt5_server') }}</span>
                <input wire:model="mt5Server" autocomplete="off">
            </label>
            <div class="term-desk__connect-actions">
                <button type="submit">{{ __('trading.terminal.mt5_connect') }}</button>
                @if ($live)
                    <button type="button" wire:click="disconnectMt5">{{ __('trading.terminal.mt5_disconnect') }}</button>
                @endif
            </div>
        </form>
        @if ($mt5Status)
            <p class="term-desk__note">{{ $mt5Status }}</p>
        @endif
        <header class="term-desk__summary">
            <p class="term-desk__eyebrow">{{ __('trading.terminal.summary') }}</p>
            @if ($desk['summary'])
                <dl class="term-desk__stats">
                    <div>
                        <dt>{{ __('trading.terminal.balance') }}</dt>
                        <dd>{{ $desk['summary']['balance'] }}</dd>
                    </div>
                    <div>
                        <dt>{{ __('trading.terminal.equity') }}</dt>
                        <dd>{{ $desk['summary']['equity'] }}</dd>
                    </div>
                    <div>
                        <dt>{{ __('trading.terminal.today_pnl') }}</dt>
                        <dd class="term-desk__pnl term-desk__pnl--{{ $desk['summary']['today_tone'] }}">{{ $desk['summary']['today'] }}</dd>
                    </div>
                </dl>
            @elseif ($desk['session'])
                <p class="term-desk__empty">{{ __('trading.terminal.waiting_account') }}</p>
            @else
                <p class="term-desk__empty">{{ __('trading.terminal.need_session') }}</p>
            @endif
        </header>

        <div class="term-desk__body">
            <section class="term-desk__watch">
                <div class="term-desk__toolbar">
                    <h3>{{ __('trading.terminal.watch') }}</h3>
                    <label class="term-desk__click">
                        <input type="checkbox" data-click-mode>
                        <span>{{ __('trading.terminal.double_click') }}</span>
                    </label>
                </div>
                <p class="term-desk__note" data-term-note>{{ __('trading.terminal.fixed_risk') }}</p>
                @if ($desk['quotes'] === [])
                    <p class="term-desk__empty">{{ __('trading.terminal.watch_empty') }}</p>
                @else
                    <div class="term-desk__table">
                        <table class="term-grid" data-term-quotes>
                            <thead>
                                <tr>
                                    <th>{{ __('trading.terminal.row') }}</th>
                                    <th>{{ __('trading.terminal.symbol') }}</th>
                                    <th>{{ __('trading.terminal.bid') }}</th>
                                    <th>{{ __('trading.terminal.ask') }}</th>
                                    <th>{{ __('trading.terminal.day_high') }}</th>
                                    <th>{{ __('trading.terminal.day_low') }}</th>
                                    <th>{{ __('trading.terminal.time') }}</th>
                                    <th>{{ __('trading.terminal.lots') }}</th>
                                    <th>{{ __('trading.terminal.buy') }} / {{ __('trading.terminal.sell') }}</th>
                                </tr>
                            </thead>
                            <tbody wire:ignore>
                                @foreach ($desk['quotes'] as $index => $quote)
                                    <tr data-symbol="{{ $quote['symbol'] }}" wire:key="watch-{{ $quote['symbol'] }}">
                                        <td>{{ $index + 1 }}</td>
                                        <td>{{ $quote['symbol'] }}</td>
                                        <td data-bid>{{ $quote['bid'] }}</td>
                                        <td data-ask>{{ $quote['ask'] }}</td>
                                        <td data-high>—</td>
                                        <td data-low>—</td>
                                        <td data-stamp>—</td>
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

                            const clock = (ms) => {
                                const date = new Date(ms);
                                const pad = (value, width = 2) => String(value).padStart(width, '0');

                                return pad(date.getHours()) + ':' + pad(date.getMinutes()) + ':' + pad(date.getSeconds()) + '.' + pad(date.getMilliseconds(), 3);
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
                                const stamp = row.querySelector('[data-stamp]');
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
                                if (stamp) {
                                    stamp.textContent = clock(quote.time_msc) + ' · ' + quote.age_ms + 'ms';
                                }
                                row.classList.toggle('is-stale', Math.abs(Number(quote.age_ms)) > 1500);
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
                            setInterval(pull, 50);
                        })();
                    </script>
                @endif
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
                                <td>
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
</x-filament-panels::page>
