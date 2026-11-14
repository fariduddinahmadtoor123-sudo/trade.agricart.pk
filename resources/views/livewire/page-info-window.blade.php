<div
    class="page-info"
    x-data="{
        open: false,
        minimized: false,
        maximized: false,
        copied: null,
        x: 0,
        y: 0,
        show() {
            this.open = true
            this.minimized = false
        },
        minimize() {
            this.minimized = true
        },
        restore() {
            this.open = true
            this.minimized = false
        },
        toggleMax() {
            this.maximized = !this.maximized
            this.x = 0
            this.y = 0
        },
        close() {
            this.open = false
            this.minimized = false
            this.maximized = false
            this.x = 0
            this.y = 0
        },
        async copy(text, key) {
            try {
                await navigator.clipboard.writeText(text)
            } catch (error) {
                const area = document.createElement('textarea')
                area.value = text
                document.body.appendChild(area)
                area.select()
                document.execCommand('copy')
                area.remove()
            }
            this.copied = key
            setTimeout(() => {
                if (this.copied === key) {
                    this.copied = null
                }
            }, 1600)
        },
        startDrag(event) {
            if (this.maximized || event.button !== 0) {
                return
            }
            const originX = this.x
            const originY = this.y
            const startX = event.clientX
            const startY = event.clientY
            const move = (pointer) => {
                this.x = originX + (pointer.clientX - startX)
                this.y = originY + (pointer.clientY - startY)
            }
            const stop = () => {
                window.removeEventListener('pointermove', move)
                window.removeEventListener('pointerup', stop)
            }
            window.addEventListener('pointermove', move)
            window.addEventListener('pointerup', stop)
        },
    }"
    x-on:keydown.escape.window="close()"
>
    <button
        type="button"
        class="page-info-launch fixed right-3 z-[45] inline-flex h-7 w-7 items-center justify-center rounded-full border border-white/30 bg-white/10 text-[13px] font-bold text-white shadow-sm transition hover:border-emerald-300 hover:bg-emerald-500"
        style="top: calc(var(--topbar-height, 3.0625rem) + (var(--agricart-subnav-height, 3.0625rem) - 1.75rem) / 2)"
        x-on:click="show()"
        aria-label="{{ __('trading.page_info.title') }}"
        title="{{ __('trading.page_info.title') }}"
    >
        i
    </button>

    <section
        x-show="open && !minimized"
        x-cloak
        x-transition.opacity.duration.150ms
        class="fixed z-[60] flex flex-col overflow-hidden rounded-xl border border-slate-200 bg-white text-slate-900 shadow-2xl dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100"
        x-bind:class="maximized ? 'inset-3' : 'h-[min(36rem,72vh)] w-[min(42rem,calc(100vw-2rem))]'"
        x-bind:style="maximized ? '' : `left: calc(50% + ${x}px); top: calc(7.5rem + ${y}px); transform: translateX(-50%)`"
        role="dialog"
        aria-modal="false"
        aria-label="{{ $title }}"
    >
        <header
            class="flex h-11 shrink-0 cursor-grab items-center gap-2 border-b border-white/10 bg-slate-950 px-3 text-white active:cursor-grabbing"
            x-on:pointerdown="startDrag($event)"
        >
            <h2 class="min-w-0 flex-1 truncate text-sm font-bold">{{ $title }}</h2>
            <div class="flex shrink-0 items-center gap-1" x-on:pointerdown.stop>
                @if ($standaloneUrl)
                    <a
                        href="{{ $standaloneUrl }}"
                        target="_blank"
                        rel="noopener"
                        class="inline-flex h-7 items-center rounded-md px-2 text-xs font-bold text-slate-200 hover:bg-white/10 hover:text-white"
                    >
                        {{ __('trading.page_info.open_tab') }}
                    </a>
                @endif
                <button type="button" class="inline-flex h-7 w-7 items-center justify-center rounded-md text-sm font-bold text-slate-200 hover:bg-white/10" x-on:click="minimize()" aria-label="{{ __('trading.page_info.minimize') }}" title="{{ __('trading.page_info.minimize') }}">–</button>
                <button type="button" class="inline-flex h-7 w-7 items-center justify-center rounded-md text-sm font-bold text-slate-200 hover:bg-white/10" x-on:click="toggleMax()" x-bind:aria-label="maximized ? '{{ __('trading.page_info.restore') }}' : '{{ __('trading.page_info.maximize') }}'" x-bind:title="maximized ? '{{ __('trading.page_info.restore') }}' : '{{ __('trading.page_info.maximize') }}'">
                    <span x-show="!maximized">□</span>
                    <span x-show="maximized" x-cloak>❐</span>
                </button>
                <button type="button" class="inline-flex h-7 w-7 items-center justify-center rounded-md text-sm font-bold text-slate-200 hover:bg-rose-500" x-on:click="close()" aria-label="{{ __('trading.page_info.close') }}" title="{{ __('trading.page_info.close') }}">×</button>
            </div>
        </header>

        <div class="min-h-0 flex-1 space-y-4 overflow-y-auto p-4">
            @if (blank($userGuide) && blank($aiPrompt))
                <p class="text-sm font-semibold text-slate-500 dark:text-slate-400">{{ __('trading.page_info.empty') }}</p>
            @endif

            @if (filled($userGuide))
                <section class="rounded-lg border border-slate-200 p-3 dark:border-slate-700">
                    <div class="mb-2 flex items-center justify-between gap-3">
                        <h3 class="text-sm font-bold">{{ __('trading.page_info.user_guide') }}</h3>
                        <button type="button" class="rounded-full bg-emerald-500 px-3 py-1 text-xs font-bold text-white hover:bg-emerald-600" x-on:click="copy(@js($userGuide), 'guide')">
                            <span x-show="copied !== 'guide'">{{ __('trading.page_info.copy') }}</span>
                            <span x-show="copied === 'guide'" x-cloak>{{ __('trading.page_info.copied') }}</span>
                        </button>
                    </div>
                    <div class="whitespace-pre-wrap break-words text-sm font-bold leading-6">{{ $userGuide }}</div>
                </section>
            @endif

            @if (filled($aiPrompt))
                <section class="rounded-lg border border-slate-200 p-3 dark:border-slate-700">
                    <div class="mb-2 flex items-center justify-between gap-3">
                        <h3 class="text-sm font-bold">{{ __('trading.page_info.ai_prompt') }}</h3>
                        <button type="button" class="rounded-full bg-emerald-500 px-3 py-1 text-xs font-bold text-white hover:bg-emerald-600" x-on:click="copy(@js($aiPrompt), 'prompt')">
                            <span x-show="copied !== 'prompt'">{{ __('trading.page_info.copy') }}</span>
                            <span x-show="copied === 'prompt'" x-cloak>{{ __('trading.page_info.copied') }}</span>
                        </button>
                    </div>
                    <div class="whitespace-pre-wrap break-words text-sm font-bold leading-6">{{ $aiPrompt }}</div>
                </section>
            @endif
        </div>
    </section>

    <div
        x-show="minimized"
        x-cloak
        class="fixed inset-x-0 bottom-0 z-[70] flex h-11 items-center gap-2 border-t border-white/10 bg-slate-950 px-3"
        role="toolbar"
        aria-label="{{ __('trading.page_info.minimized') }}"
    >
        <button
            type="button"
            class="inline-flex h-8 max-w-xs items-center truncate rounded-md bg-emerald-500/20 px-3 text-sm font-bold text-emerald-200 hover:bg-emerald-500/30"
            x-on:click="restore()"
        >
            {{ $title }}
        </button>
        <button type="button" class="inline-flex h-8 w-8 items-center justify-center rounded-md text-sm font-bold text-slate-300 hover:bg-white/10" x-on:click="close()" aria-label="{{ __('trading.page_info.close') }} {{ $title }}">×</button>
    </div>
</div>
