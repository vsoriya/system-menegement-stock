@props(['title' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ? $title.' · '.config('app.name') : config('app.name') }}</title>
    @include('partials.theme-script')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
{{--
    The sign-in backdrop is intentionally dark in both themes, so text sitting
    directly on it uses fixed light colours rather than the semantic ink tokens.
    The card itself uses --color-surface, so it still follows the theme.
--}}
<body class="relative flex min-h-full items-center justify-center overflow-hidden bg-slate-950 px-4 py-12 antialiased">
    {{-- Decorative background. Purely visual, so hidden from assistive tech. --}}
    <div class="pointer-events-none absolute inset-0 overflow-hidden" aria-hidden="true">
        <div class="animate-float absolute -top-32 -left-32 size-96 rounded-full bg-brand-600/30 blur-3xl"></div>
        <div class="animate-float absolute -right-32 -bottom-32 size-96 rounded-full bg-sky-500/20 blur-3xl" style="animation-delay: 2s"></div>
        <div class="animate-float absolute top-1/3 left-1/2 size-72 rounded-full bg-fuchsia-500/15 blur-3xl" style="animation-delay: 4s"></div>
    </div>

    <div class="animate-pop relative w-full max-w-md">
        <div class="mb-8 flex flex-col items-center gap-3">
            <span
                class="flex size-14 items-center justify-center rounded-2xl bg-linear-to-br from-brand-400 to-brand-600 text-lg font-bold text-white shadow-glow"
                aria-hidden="true"
            >SM</span>
            <h1 class="text-xl font-semibold tracking-tight text-white">{{ config('app.name') }}</h1>
            <p class="text-sm text-slate-400">{{ __('app.auth.tagline') }}</p>
        </div>

        <div class="rounded-2xl border border-white/10 bg-surface/95 p-6 text-ink shadow-lift backdrop-blur-xl sm:p-8">
            {{ $slot }}
        </div>

        {{--
            The language switcher belongs here, not just inside the app. Someone
            who cannot read English has to be able to change the language before
            signing in, which is why the locale route sits outside the auth
            middleware group.
        --}}
        <div class="mt-6 flex flex-wrap items-center justify-center gap-3">
            <x-language-switcher />
            <x-theme-toggle />
        </div>
    </div>
</body>
</html>
