<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->isLocale('ur') ? 'rtl' : 'ltr' }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $title }}</title>
        <link rel="stylesheet" href="{{ asset('css/filament/page-info.css') }}?v={{ filemtime(public_path('css/filament/page-info.css')) }}">
    </head>
    <body class="page-info min-h-screen bg-slate-100 text-slate-900" data-copied="{{ __('trading.page_info.copied') }}">
        <main class="mx-auto flex max-w-3xl flex-col gap-4 px-6 py-10" id="page-info-root" style="--page-info-scale: 1">
            <header class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <p class="text-xs font-bold uppercase tracking-wide text-emerald-700">{{ __('trading.page_info.eyebrow') }}</p>
                    <h1 class="mt-1 text-2xl font-bold">{{ $title }}</h1>
                </div>
                <div class="page-info-text-size page-info-text-size--light" role="group" aria-label="{{ __('trading.page_info.text_size') }}">
                    <button type="button" class="page-info-text-size__btn" data-text-smaller aria-label="{{ __('trading.page_info.text_smaller') }}" title="{{ __('trading.page_info.text_smaller') }}">{{ __('trading.page_info.text_smaller') }}</button>
                    <button type="button" class="page-info-text-size__btn" data-text-larger aria-label="{{ __('trading.page_info.text_larger') }}" title="{{ __('trading.page_info.text_larger') }}">{{ __('trading.page_info.text_larger') }}</button>
                </div>
            </header>

            @if (filled($userGuide))
                <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="mb-3 flex items-center justify-between gap-3">
                        <h2 class="text-base font-bold">{{ __('trading.page_info.user_guide') }}</h2>
                        <button type="button" class="rounded-full bg-emerald-500 px-3 py-1 text-xs font-bold text-white" data-copy>{{ __('trading.page_info.copy') }}</button>
                    </div>
                    <div class="page-info-copy whitespace-pre-wrap break-words font-bold" data-copy-text>{{ $userGuide }}</div>
                </section>
            @endif

            @if (filled($aiPrompt))
                <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="mb-3 flex items-center justify-between gap-3">
                        <h2 class="text-base font-bold">{{ __('trading.page_info.ai_prompt') }}</h2>
                        <button type="button" class="rounded-full bg-emerald-500 px-3 py-1 text-xs font-bold text-white" data-copy>{{ __('trading.page_info.copy') }}</button>
                    </div>
                    <div class="page-info-copy whitespace-pre-wrap break-words font-bold" data-copy-text>{{ $aiPrompt }}</div>
                </section>
            @endif
        </main>
        <script>
            (() => {
                const root = document.getElementById('page-info-root')
                const key = 'agricart:page-info:font-scale'
                let scale = Number(localStorage.getItem(key) || 1)

                const apply = () => {
                    root.style.setProperty('--page-info-scale', String(scale))
                    localStorage.setItem(key, String(scale))
                }

                apply()

                document.querySelector('[data-text-smaller]')?.addEventListener('click', () => {
                    scale = Math.max(0.85, Number((scale - 0.1).toFixed(2)))
                    apply()
                })

                document.querySelector('[data-text-larger]')?.addEventListener('click', () => {
                    scale = Math.min(1.45, Number((scale + 0.1).toFixed(2)))
                    apply()
                })

                document.querySelectorAll('[data-copy]').forEach((button) => {
                    button.addEventListener('click', async () => {
                        const text = button.closest('section')?.querySelector('[data-copy-text]')?.innerText ?? ''
                        await navigator.clipboard.writeText(text)
                        const label = button.textContent
                        button.textContent = document.body.dataset.copied || 'Copied'
                        setTimeout(() => {
                            button.textContent = label
                        }, 1600)
                    })
                })
            })()
        </script>
    </body>
</html>
