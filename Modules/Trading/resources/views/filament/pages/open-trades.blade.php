<x-filament-panels::page>
    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-white/10 dark:bg-gray-900" wire:poll.5s>
        <div class="overflow-x-auto">
            <table class="w-full table-auto text-start text-sm">
                <thead class="border-b border-gray-200 text-gray-500 dark:border-white/10 dark:text-gray-400">
                    <tr>
                        <th class="px-3 py-2 font-medium">{{ __('trading.open_trades.ticket') }}</th>
                        <th class="px-3 py-2 font-medium">{{ __('trading.open_trades.symbol') }}</th>
                        <th class="px-3 py-2 font-medium">{{ __('trading.open_trades.type') }}</th>
                        <th class="px-3 py-2 font-medium">{{ __('trading.open_trades.volume') }}</th>
                        <th class="px-3 py-2 font-medium">{{ __('trading.open_trades.open') }}</th>
                        <th class="px-3 py-2 font-medium">{{ __('trading.open_trades.profit') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->positions() as $position)
                        <tr wire:key="open-trade-{{ $position->id }}">
                            <td class="px-3 py-2">{{ $position->mt5_ticket ?? $position->id }}</td>
                            <td class="px-3 py-2">{{ $position->symbol }}</td>
                            <td class="px-3 py-2">{{ $position->side->getLabel() }}</td>
                            <td class="px-3 py-2">{{ number_format((float) $position->lots, 2) }}</td>
                            <td class="px-3 py-2">{{ number_format((float) $position->entry_price, abs((float) $position->entry_price) >= 50 ? 2 : 5, '.', '') }}</td>
                            <td class="px-3 py-2">{{ is_numeric($position->floating_pnl) ? number_format((float) $position->floating_pnl, 2) : '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-3 py-10 text-center text-gray-400 dark:text-gray-500">
                                {{ __('trading.open_trades.empty') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-filament-panels::page>
