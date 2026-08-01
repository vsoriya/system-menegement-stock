<x-guest-layout :title="__('app.auth.forgot_title')">
    <h2 class="text-lg font-semibold tracking-tight text-ink">{{ __('app.auth.forgot_title') }}</h2>
    <p class="mt-1 text-sm text-ink-muted">{{ __('app.auth.forgot_intro') }}</p>

    @if (session('status'))
        <div role="status" class="animate-toast mt-4 rounded-xl border border-emerald-200 dark:border-emerald-500/30 bg-emerald-50 dark:bg-emerald-500/15 px-3 py-2 text-sm font-medium text-emerald-800 dark:text-emerald-200">
            {{ session('status') }}
        </div>
    @endif

    <form method="POST" action="{{ route('password.email') }}" class="mt-6 space-y-5">
        @csrf

        <x-field
            :label="__('app.auth.email')"
            name="email"
            type="email"
            placeholder="you@example.com"
            autocomplete="username"
            autofocus
            required
        />

        <x-button type="submit" class="w-full">{{ __('app.auth.send_link') }}</x-button>

        <p class="text-center text-sm">
            <a href="{{ route('login') }}" class="font-medium text-brand-600 dark:text-brand-400 transition hover:text-brand-700 dark:hover:text-brand-300">
                {{ __('app.auth.back_to_login') }}
            </a>
        </p>
    </form>
</x-guest-layout>
