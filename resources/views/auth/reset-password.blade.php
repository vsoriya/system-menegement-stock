<x-guest-layout :title="__('app.auth.reset_title')">
    <h2 class="text-lg font-semibold tracking-tight text-ink">{{ __('app.auth.reset_title') }}</h2>
    <p class="mt-1 text-sm text-ink-muted">{{ __('app.auth.reset_intro') }}</p>

    <form method="POST" action="{{ route('password.store') }}" class="mt-6 space-y-5">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <x-field
            :label="__('app.auth.email')"
            name="email"
            type="email"
            :value="$email"
            autocomplete="username"
            required
        />

        <x-field
            :label="__('app.auth.new_password')"
            name="password"
            type="password"
            autocomplete="new-password"
            autofocus
            required
        />

        <x-field
            :label="__('app.auth.confirm_password')"
            name="password_confirmation"
            type="password"
            autocomplete="new-password"
            required
        />

        <x-button type="submit" class="w-full">{{ __('app.auth.reset_password') }}</x-button>

        <p class="text-center text-sm">
            <a href="{{ route('login') }}" class="font-medium text-brand-600 dark:text-brand-400 transition hover:text-brand-700 dark:hover:text-brand-300">
                {{ __('app.auth.back_to_login') }}
            </a>
        </p>
    </form>
</x-guest-layout>
