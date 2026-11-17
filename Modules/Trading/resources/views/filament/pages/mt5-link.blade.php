<x-filament-panels::page>
    <style>
        .fi-page:has(.mt5-link) .fi-page-content {
            display: block;
            width: 100%;
            max-width: none;
            margin-inline: 0;
        }

        .mt5-link {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            width: 100%;
            max-width: none;
            margin-inline: 0;
            box-sizing: border-box;
        }

        .mt5-link-cards {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 1rem;
            width: 100%;
        }

        .mt5-link-card,
        .mt5-link-panel {
            min-width: 0;
            width: 100%;
            box-sizing: border-box;
            border: 1px solid #e2e8f0;
            border-radius: 0.75rem;
            background: #ffffff;
        }

        .mt5-link-card,
        .mt5-link-card * {
            overflow-wrap: anywhere;
            word-break: break-word;
            max-width: 100%;
        }

        .mt5-link-card {
            padding: 1rem;
            color: #334155;
            font-size: 0.875rem;
        }

        .mt5-link-card h2,
        .mt5-link-panel h2 {
            margin: 0;
            color: #0f172a;
            font-size: 1rem;
            font-weight: 700;
        }

        .mt5-link-card ul,
        .mt5-link-card ol {
            margin: 0.5rem 0 0;
            padding-inline-start: 1.25rem;
        }

        .mt5-link-card li + li,
        .mt5-link-card p {
            margin-top: 0.35rem;
        }

        .mt5-link-panel {
            overflow: hidden;
        }

        .mt5-link-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #e2e8f0;
        }

        .mt5-link-actions {
            display: flex;
            flex: 0 0 auto;
            gap: 0.5rem;
        }

        .mt5-link-button {
            flex: 0 0 auto;
            border: 0;
            border-radius: 0.5rem;
            background: #10b981;
            color: #ffffff;
            padding: 0.4rem 0.75rem;
            font-size: 0.875rem;
            font-weight: 600;
            cursor: pointer;
        }

        .mt5-link-field {
            display: block;
            width: 100%;
            max-width: 100%;
            box-sizing: border-box;
            border: 0;
            margin: 0;
            padding: 0.9rem 1rem;
            background: #0f172a;
            color: #e2e8f0;
            font-family: Consolas, "Courier New", monospace;
            font-size: 12px;
            line-height: 1.5;
        }

        .mt5-link-command {
            min-height: 2.75rem;
        }

        .mt5-link-code {
            min-height: 24rem;
            resize: vertical;
        }

        .mt5-link-launcher {
            min-height: 12rem;
            resize: vertical;
        }

        .mt5-link-missing {
            margin: 0;
            padding: 0.75rem 1rem;
            color: #dc2626;
            font-size: 0.875rem;
            border-bottom: 1px solid #e2e8f0;
        }

        @media (max-width: 900px) {
            .mt5-link-cards {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <div class="mt5-link">
        <div class="mt5-link-cards">
            @foreach (['en', 'ur'] as $locale)
                <section
                    class="mt5-link-card"
                    dir="{{ $locale === 'ur' ? 'rtl' : 'ltr' }}"
                    lang="{{ $locale }}"
                >
                    <h2>{{ trans('trading.connector.needed', [], $locale) }}</h2>
                    <ul>
                        @foreach (trans('trading.connector.needed_items', [], $locale) as $item)
                            <li wire:key="connector-needed-{{ $locale }}-{{ $loop->index }}">{{ $item }}</li>
                        @endforeach
                    </ul>

                    <h2 style="margin-top: 1rem;">{{ trans('trading.connector.steps_title', [], $locale) }}</h2>
                    <ol>
                        @foreach (trans('trading.connector.steps', [], $locale) as $step)
                            <li wire:key="connector-step-{{ $locale }}-{{ $loop->index }}">{{ $step }}</li>
                        @endforeach
                    </ol>

                    <p>{{ trans('trading.connector.account', [], $locale) }}</p>
                    <p>{{ trans('trading.connector.address', [], $locale) }}</p>
                </section>
            @endforeach
        </div>

        <div class="mt5-link-panel" x-data="{ copied: false }">
            <div class="mt5-link-bar">
                <h2>start-mt5.bat</h2>
                <div class="mt5-link-actions">
                    <button type="button" class="mt5-link-button" wire:click="downloadLauncher">
                        {{ trans('trading.connector.download', [], 'en') }} / {{ trans('trading.connector.download', [], 'ur') }}
                    </button>
                    <button
                        type="button"
                        class="mt5-link-button"
                        x-on:click="
                            const box = $refs.launcher;
                            box.focus();
                            box.select();
                            copied = true;
                            try { document.execCommand('copy'); } catch (error) {}
                        "
                    >
                        <span x-show="!copied">{{ trans('trading.connector.copy', [], 'en') }} / {{ trans('trading.connector.copy', [], 'ur') }}</span>
                        <span x-show="copied" x-cloak>{{ trans('trading.connector.copied', [], 'en') }} / {{ trans('trading.connector.copied', [], 'ur') }}</span>
                    </button>
                </div>
            </div>
            <textarea
                x-ref="launcher"
                readonly
                class="mt5-link-field mt5-link-launcher"
            >{{ $this->launcherScript }}</textarea>
        </div>

        <div class="mt5-link-panel" x-data="{ copied: false }">
            @unless ($this->connectorReady)
                <p class="mt5-link-missing">{{ __('trading.connector.missing') }}</p>
            @endunless

            <div class="mt5-link-bar">
                <h2>connect-mt5.py</h2>
                <div class="mt5-link-actions">
                    <button type="button" class="mt5-link-button" wire:click="downloadConnector">
                        {{ trans('trading.connector.download', [], 'en') }} / {{ trans('trading.connector.download', [], 'ur') }}
                    </button>
                    <button
                        type="button"
                        class="mt5-link-button"
                        x-on:click="
                            const box = $refs.code;
                            box.focus();
                            box.select();
                            copied = true;
                            try { document.execCommand('copy'); } catch (error) {}
                        "
                    >
                        <span x-show="!copied">{{ trans('trading.connector.copy', [], 'en') }} / {{ trans('trading.connector.copy', [], 'ur') }}</span>
                        <span x-show="copied" x-cloak>{{ trans('trading.connector.copied', [], 'en') }} / {{ trans('trading.connector.copied', [], 'ur') }}</span>
                    </button>
                </div>
            </div>
            <textarea
                x-ref="code"
                readonly
                class="mt5-link-field mt5-link-code"
            >{{ $this->connectorScript }}</textarea>
        </div>
    </div>
</x-filament-panels::page>
