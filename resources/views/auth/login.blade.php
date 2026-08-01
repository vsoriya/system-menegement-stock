<x-guest-layout :title="__('app.auth.sign_in')">
    <h2 class="text-lg font-semibold tracking-tight text-ink">{{ __('app.auth.welcome_back') }}</h2>
    <p class="mt-1 text-sm text-ink-muted">{{ __('app.auth.subtitle') }}</p>

    @if (session('status'))
        <div role="status" class="animate-toast mt-4 rounded-xl border border-emerald-200 dark:border-emerald-500/30 bg-emerald-50 dark:bg-emerald-500/15 px-3 py-2 text-sm font-medium text-emerald-800 dark:text-emerald-200">
            {{ session('status') }}
        </div>
    @endif

    <form method="POST" action="{{ route('login') }}" class="mt-6 space-y-5">
        @csrf

        <div class="animate-rise stagger" style="--d: 80ms">
            <x-field
                :label="__('app.auth.email')"
                name="email"
                type="email"
                placeholder="you@example.com"
                autocomplete="username"
                autofocus
                required
            />
        </div>

        <div class="animate-rise stagger" style="--d: 160ms">
            <x-field
                :label="__('app.auth.password')"
                name="password"
                type="password"
                placeholder="••••••••"
                autocomplete="current-password"
                required
            />
        </div>

        <div class="animate-rise stagger flex flex-wrap items-center justify-between gap-2" style="--d: 240ms">
            <label for="remember" class="group flex cursor-pointer items-center gap-2 text-sm text-ink-muted">
                <input
                    type="checkbox"
                    id="remember"
                    name="remember"
                    value="1"
                    @checked(old('remember'))
                    class="size-4 rounded border-line text-brand-600 dark:text-brand-400 transition focus:ring-2 focus:ring-brand-500/40"
                >
                <span class="transition group-hover:text-ink">{{ __('app.auth.remember') }}</span>
            </label>

            <a
                href="{{ route('password.request') }}"
                class="text-sm font-medium text-brand-600 dark:text-brand-400 transition hover:text-brand-700 dark:hover:text-brand-300"
            >
                {{ __('app.auth.forgot') }}
            </a>
        </div>

        <div class="animate-rise stagger" style="--d: 320ms">
            <x-button type="submit" class="w-full">
                {{ __('app.auth.sign_in') }}
                <svg class="size-4 transition-transform duration-200 group-hover:translate-x-1" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M3 10a.75.75 0 0 1 .75-.75h10.638L10.23 5.29a.75.75 0 1 1 1.04-1.08l5.5 5.25a.75.75 0 0 1 0 1.08l-5.5 5.25a.75.75 0 1 1-1.04-1.08l4.158-3.96H3.75A.75.75 0 0 1 3 10Z" clip-rule="evenodd" />
                </svg>
            </x-button>
        </div>
    </form>
</x-guest-layout>
