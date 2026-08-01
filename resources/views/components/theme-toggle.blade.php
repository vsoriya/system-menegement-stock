{{--
    Theme switcher with three states: light, dark, or follow the system.
    The choice persists in localStorage; 'system' clears the stored value so
    the OS preference takes over again.
--}}

<div
    x-data="{
        choice: 'system',
        init() {
            this.choice = localStorage.getItem('theme') || 'system';
            // Keep up with the OS while the user is on 'system'.
            window.matchMedia('(prefers-color-scheme: dark)')
                .addEventListener('change', () => {
                    if (this.choice === 'system') this.apply();
                });
        },
        select(value) {
            this.choice = value;
            if (value === 'system') {
                localStorage.removeItem('theme');
            } else {
                localStorage.setItem('theme', value);
            }
            this.apply();
        },
        apply() {
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            const dark = this.choice === 'dark' || (this.choice === 'system' && prefersDark);
            document.documentElement.classList.toggle('dark', dark);
        },
    }"
    class="flex items-center gap-0.5 rounded-xl border border-line bg-surface-sunken p-0.5 print:hidden"
    role="group"
    aria-label="{{ __('app.common.theme') }}"
>
    {{-- Light --}}
    <button
        type="button"
        @click="select('light')"
        :aria-pressed="choice === 'light'"
        :class="choice === 'light'
            ? 'bg-surface text-brand-600 dark:text-brand-400 shadow-soft'
            : 'text-ink-subtle hover:text-ink'"
        class="rounded-lg p-1.5 transition duration-200"
        title="{{ __('app.common.theme_light') }}"
    >
        <span class="sr-only">{{ __('app.common.theme_light') }}</span>
        <svg class="size-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path d="M10 2a.75.75 0 0 1 .75.75v1a.75.75 0 0 1-1.5 0v-1A.75.75 0 0 1 10 2Zm5.657 2.343a.75.75 0 0 1 0 1.06l-.708.708a.75.75 0 1 1-1.06-1.06l.707-.708a.75.75 0 0 1 1.06 0ZM18 10a.75.75 0 0 1-.75.75h-1a.75.75 0 0 1 0-1.5h1A.75.75 0 0 1 18 10Zm-3.051 4.95a.75.75 0 0 1 1.06 1.06l-.707-.707-.354.354a.75.75 0 0 1 0-.708Zm.001 0 .706.707-.707.707a.75.75 0 0 1-1.06-1.06l.354-.354ZM10 16.25a.75.75 0 0 1 .75.75v1a.75.75 0 0 1-1.5 0v-1a.75.75 0 0 1 .75-.75ZM5.05 14.95a.75.75 0 0 1 1.06 1.06l-.706.707a.75.75 0 1 1-1.061-1.06l.707-.707ZM3.75 9.25a.75.75 0 0 1 0 1.5h-1a.75.75 0 0 1 0-1.5h1Zm1.3-4.907a.75.75 0 0 1 1.06 1.06l-.707.708a.75.75 0 0 1-1.06-1.061l.707-.707ZM10 6a4 4 0 1 1 0 8 4 4 0 0 1 0-8Z" />
        </svg>
    </button>

    {{-- Dark --}}
    <button
        type="button"
        @click="select('dark')"
        :aria-pressed="choice === 'dark'"
        :class="choice === 'dark'
            ? 'bg-surface text-brand-600 dark:text-brand-400 shadow-soft'
            : 'text-ink-subtle hover:text-ink'"
        class="rounded-lg p-1.5 transition duration-200"
        title="{{ __('app.common.theme_dark') }}"
    >
        <span class="sr-only">{{ __('app.common.theme_dark') }}</span>
        <svg class="size-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path fill-rule="evenodd" d="M7.455 2.004a.75.75 0 0 1 .26.77 7 7 0 0 0 9.958 7.967.75.75 0 0 1 1.067.853A8.5 8.5 0 1 1 6.647 1.921a.75.75 0 0 1 .808.083Z" clip-rule="evenodd" />
        </svg>
    </button>

    {{-- System --}}
    <button
        type="button"
        @click="select('system')"
        :aria-pressed="choice === 'system'"
        :class="choice === 'system'
            ? 'bg-surface text-brand-600 dark:text-brand-400 shadow-soft'
            : 'text-ink-subtle hover:text-ink'"
        class="rounded-lg p-1.5 transition duration-200"
        title="{{ __('app.common.theme_system') }}"
    >
        <span class="sr-only">{{ __('app.common.theme_system') }}</span>
        <svg class="size-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path fill-rule="evenodd" d="M2 4.25A2.25 2.25 0 0 1 4.25 2h11.5A2.25 2.25 0 0 1 18 4.25v8.5A2.25 2.25 0 0 1 15.75 15h-3.105l.32 1.28.734.245a.75.75 0 0 1-.237 1.462H6.538a.75.75 0 0 1-.237-1.462l.734-.245.32-1.28H4.25A2.25 2.25 0 0 1 2 12.75v-8.5Zm2.25-.75a.75.75 0 0 0-.75.75v8.5c0 .414.336.75.75.75h11.5a.75.75 0 0 0 .75-.75v-8.5a.75.75 0 0 0-.75-.75H4.25Z" clip-rule="evenodd" />
        </svg>
    </button>
</div>
