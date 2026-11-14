<x-filament-panels::page>
    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-white/10 dark:bg-gray-900">
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
                    <tr>
                        <td colspan="6" class="px-3 py-10 text-center text-gray-400 dark:text-gray-500">
                            {{ __('trading.open_trades.empty') }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</x-filament-panels::page>
