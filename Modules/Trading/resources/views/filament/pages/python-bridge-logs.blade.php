<x-filament-panels::page>
    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-white/10 dark:bg-gray-900">
        <div class="flex items-center justify-end border-b border-gray-100 px-3 py-2 dark:border-white/10">
            <x-filament::button wire:click="refreshLogs" color="gray" size="sm">
                {{ __('trading.logs.refresh') }}
            </x-filament::button>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full table-auto text-start text-sm">
                <thead class="border-b border-gray-200 text-gray-500 dark:border-white/10 dark:text-gray-400">
                    <tr>
                        <th class="px-3 py-2 font-medium">{{ __('trading.logs.symbol') }}</th>
                        <th class="px-3 py-2 font-medium">{{ __('trading.logs.bid') }}</th>
                        <th class="px-3 py-2 font-medium">{{ __('trading.logs.ask') }}</th>
                        <th class="px-3 py-2 font-medium">{{ __('trading.logs.last') }}</th>
                        <th class="px-3 py-2 font-medium">{{ __('trading.logs.source') }}</th>
                        <th class="px-3 py-2 font-medium">{{ __('trading.logs.feed_at') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                    @forelse ($this->recentLogs as $log)
                        <tr wire:key="trading-log-{{ $log->id }}">
                            <td class="px-3 py-2 font-medium text-gray-950 dark:text-white">{{ $log->symbol }}</td>
                            <td class="px-3 py-2 text-gray-700 dark:text-gray-200">{{ $log->bid }}</td>
                            <td class="px-3 py-2 text-gray-700 dark:text-gray-200">{{ $log->ask }}</td>
                            <td class="px-3 py-2 text-gray-700 dark:text-gray-200">{{ $log->last }}</td>
                            <td class="px-3 py-2 text-gray-700 dark:text-gray-200">{{ $log->source }}</td>
                            <td class="px-3 py-2 text-gray-700 dark:text-gray-200">
                                {{ $log->feed_at?->toDateTimeString() ?? '—' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-3 py-10 text-center text-gray-400 dark:text-gray-500">
                                {{ __('trading.logs.empty') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-filament-panels::page>
