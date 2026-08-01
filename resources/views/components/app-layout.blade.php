@props(['title' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title.' · '.config('app.name') : config('app.name') }}</title>
    @include('partials.theme-script')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="app-canvas h-full text-ink antialiased">
    <a href="#main-content" class="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-50 focus:rounded-lg focus:bg-surface focus:px-4 focus:py-2 focus:text-sm focus:font-semibold focus:text-ink focus:ring-2 focus:ring-brand-500">
        {{ __('app.nav.skip') }}
    </a>

    <div x-data="{ sidebarOpen: false }" class="min-h-full">
        <!-- Mobile sidebar backdrop -->
        <div
            x-show="sidebarOpen"
            x-transition.opacity.duration.250ms
            @click="sidebarOpen = false"
            class="fixed inset-0 z-30 bg-slate-900/40 backdrop-blur-sm lg:hidden"
            aria-hidden="true"
            x-cloak
        ></div>

        <!-- Sidebar -->
        <aside
            class="fixed inset-y-0 left-0 z-40 flex w-64 flex-col border-r border-line bg-surface/95 backdrop-blur transition-transform duration-300 ease-[cubic-bezier(0.22,1,0.36,1)] lg:translate-x-0"
            :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
            aria-label="{{ __('app.nav.dashboard') }}"
        >
            <div class="flex h-16 shrink-0 items-center gap-3 border-b border-line px-5">
                <span
                    class="flex size-9 items-center justify-center rounded-xl bg-linear-to-br from-brand-500 to-brand-700 text-sm font-bold text-white shadow-glow"
                    aria-hidden="true"
                >SM</span>
                <span class="truncate text-sm font-semibold tracking-tight text-ink">{{ config('app.name') }}</span>
                <button
                    type="button"
                    @click="sidebarOpen = false"
                    class="ml-auto rounded-lg p-1.5 text-ink-muted transition hover:rotate-90 hover:bg-surface-sunken hover:text-ink lg:hidden"
                >
                    <span class="sr-only">{{ __('app.nav.close') }}</span>
                    <svg class="size-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z" />
                    </svg>
                </button>
            </div>

            <nav class="flex-1 space-y-6 overflow-y-auto px-3 py-5">
                <div class="space-y-1">
                    <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')" style="--d: 40ms">
                        <x-slot:icon>
                            <path d="M10.707 2.293a1 1 0 0 0-1.414 0l-7 7A1 1 0 0 0 3 11h1v6a1 1 0 0 0 1 1h3a1 1 0 0 0 1-1v-3h2v3a1 1 0 0 0 1 1h3a1 1 0 0 0 1-1v-6h1a1 1 0 0 0 .707-1.707l-7-7Z" />
                        </x-slot:icon>
                        {{ __('app.nav.dashboard') }}
                    </x-nav-link>
                </div>

                <div class="space-y-1">
                    <p class="px-3 pb-1 text-[0.6875rem] font-semibold uppercase tracking-wider text-ink-subtle">
                        {{ __('app.nav.selling') }}
                    </p>

                    <x-nav-link :href="route('pos.index')" :active="request()->routeIs('pos.*')" style="--d: 50ms">
                        <x-slot:icon>
                            <path d="M3 4a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v3H3V4Zm0 5h14v7a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V9Zm3 2a1 1 0 1 0 0 2h3a1 1 0 1 0 0-2H6Z" />
                        </x-slot:icon>
                        {{ __('app.nav.pos') }}
                    </x-nav-link>

                    <x-nav-link :href="route('sales.index')" :active="request()->routeIs('sales.*')" style="--d: 60ms">
                        <x-slot:icon>
                            <path d="M5 2a1 1 0 0 0-1 1v14l2.5-1.5L9 17l2.5-1.5L14 17l2-1V3a1 1 0 0 0-1-1H5Zm2 4h6a1 1 0 1 1 0 2H7a1 1 0 1 1 0-2Zm0 4h6a1 1 0 1 1 0 2H7a1 1 0 1 1 0-2Z" />
                        </x-slot:icon>
                        {{ __('app.nav.sales') }}
                    </x-nav-link>

                    <x-nav-link :href="route('customers.index')" :active="request()->routeIs('customers.*')" style="--d: 70ms">
                        <x-slot:icon>
                            <path d="M13 6a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm-3 5a6 6 0 0 0-6 6h12a6 6 0 0 0-6-6Z" />
                        </x-slot:icon>
                        {{ __('app.nav.customers') }}
                    </x-nav-link>
                </div>

                <div class="space-y-1">
                    <p class="px-3 pb-1 text-[0.6875rem] font-semibold uppercase tracking-wider text-ink-subtle">
                        {{ __('app.nav.inventory') }}
                    </p>

                    <x-nav-link :href="route('products.index')" :active="request()->routeIs('products.*')" style="--d: 80ms">
                        <x-slot:icon>
                            <path d="M3 4a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v2a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V4Zm0 5a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v7a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V9Zm4 2a1 1 0 0 0 0 2h6a1 1 0 0 0 0-2H7Z" />
                        </x-slot:icon>
                        {{ __('app.nav.products') }}
                    </x-nav-link>

                    <x-nav-link :href="route('movements.index')" :active="request()->routeIs('movements.*')" style="--d: 120ms">
                        <x-slot:icon>
                            <path d="M10 3a1 1 0 0 1 .707.293l3 3a1 1 0 0 1-1.414 1.414L11 6.414V13a1 1 0 1 1-2 0V6.414L7.707 7.707a1 1 0 0 1-1.414-1.414l3-3A1 1 0 0 1 10 3ZM4 15a1 1 0 0 1 1-1h10a1 1 0 1 1 0 2H5a1 1 0 0 1-1-1Z" />
                        </x-slot:icon>
                        {{ __('app.nav.movements') }}
                    </x-nav-link>

                    <x-nav-link :href="route('categories.index')" :active="request()->routeIs('categories.*')" style="--d: 160ms">
                        <x-slot:icon>
                            <path d="M2 5a2 2 0 0 1 2-2h3.172a2 2 0 0 1 1.414.586l1.828 1.828A2 2 0 0 0 11.828 6H16a2 2 0 0 1 2 2v6a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5Z" />
                        </x-slot:icon>
                        {{ __('app.nav.categories') }}
                    </x-nav-link>

                    <x-nav-link :href="route('suppliers.index')" :active="request()->routeIs('suppliers.*')" style="--d: 200ms">
                        <x-slot:icon>
                            <path d="M8 3a3 3 0 1 0 0 6 3 3 0 0 0 0-6ZM2 15a6 6 0 0 1 12 0v1a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1v-1Zm14.5-9a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5Z" />
                        </x-slot:icon>
                        {{ __('app.nav.suppliers') }}
                    </x-nav-link>
                </div>

                <div class="space-y-1">
                    <p class="px-3 pb-1 text-[0.6875rem] font-semibold uppercase tracking-wider text-ink-subtle">
                        {{ __('app.nav.purchasing') }}
                    </p>

                    <x-nav-link :href="route('purchase-orders.index')" :active="request()->routeIs('purchase-orders.*')" style="--d: 240ms">
                        <x-slot:icon>
                            <path d="M3 3a1 1 0 0 0 0 2h1.22l.305 1.222a.997.997 0 0 0 .01.042l1.358 5.43-.893.892C3.74 13.846 4.632 16 6.414 16H15a1 1 0 0 0 0-2H6.414l1-1H14a1 1 0 0 0 .894-.553l3-6A1 1 0 0 0 17 5H6.28l-.31-1.243A1 1 0 0 0 5 3H3Zm4 13a1 1 0 1 1-2 0 1 1 0 0 1 2 0Zm8 0a1 1 0 1 1-2 0 1 1 0 0 1 2 0Z" />
                        </x-slot:icon>
                        {{ __('app.nav.purchase_orders') }}
                    </x-nav-link>

                    <x-nav-link :href="route('stock-takes.index')" :active="request()->routeIs('stock-takes.*')" style="--d: 280ms">
                        <x-slot:icon>
                            <path d="M5 3a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2H5Zm3.707 5.293a1 1 0 0 0-1.414 1.414l1.5 1.5a1 1 0 0 0 1.414 0l3-3a1 1 0 1 0-1.414-1.414L9.5 9.086l-.793-.793Z" />
                        </x-slot:icon>
                        {{ __('app.nav.stock_takes') }}
                    </x-nav-link>
                </div>

                <div class="space-y-1">
                    <p class="px-3 pb-1 text-[0.6875rem] font-semibold uppercase tracking-wider text-ink-subtle">
                        {{ __('app.nav.reports') }}
                    </p>

                    <x-nav-link :href="route('reports.low-stock')" :active="request()->routeIs('reports.low-stock')" style="--d: 320ms">
                        <x-slot:icon>
                            <path d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.63-1.516 2.63H3.72c-1.347 0-2.19-1.463-1.515-2.63L8.485 2.495ZM10 5a.75.75 0 0 1 .75.75v3.5a.75.75 0 0 1-1.5 0v-3.5A.75.75 0 0 1 10 5Zm0 9a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" />
                        </x-slot:icon>
                        {{ __('app.nav.low_stock') }}
                    </x-nav-link>

                    <x-nav-link :href="route('reports.valuation')" :active="request()->routeIs('reports.valuation')" style="--d: 360ms">
                        <x-slot:icon>
                            <path d="M10 2a1 1 0 0 1 1 1v1.055a3.5 3.5 0 0 1 2.28 1.36 1 1 0 0 1-1.6 1.2A1.5 1.5 0 0 0 10.5 6h-1a1.5 1.5 0 0 0 0 3h1a3.5 3.5 0 0 1 .5 6.945V17a1 1 0 1 1-2 0v-1.055a3.5 3.5 0 0 1-2.28-1.36 1 1 0 0 1 1.6-1.2A1.5 1.5 0 0 0 9.5 14h1a1.5 1.5 0 0 0 0-3h-1A3.5 3.5 0 0 1 9 4.055V3a1 1 0 0 1 1-1Z" />
                        </x-slot:icon>
                        {{ __('app.nav.valuation') }}
                    </x-nav-link>
                </div>

                @if (auth()->user()->isAdmin())
                    <div class="space-y-1">
                        <p class="px-3 pb-1 text-[0.6875rem] font-semibold uppercase tracking-wider text-ink-subtle">
                            {{ __('app.nav.administration') }}
                        </p>

                        <x-nav-link :href="route('users.index')" :active="request()->routeIs('users.*')" style="--d: 400ms">
                            <x-slot:icon>
                                <path fill-rule="evenodd" d="M10 9a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm-7 9a7 7 0 1 1 14 0H3Z" clip-rule="evenodd" />
                            </x-slot:icon>
                            {{ __('app.nav.users') }}
                        </x-nav-link>
                    </div>
                @endif
            </nav>

            <!-- Account menu -->
            <div class="shrink-0 border-t border-line p-3" x-data="{ menuOpen: false }">
                <div class="relative">
                    <div
                        x-show="menuOpen"
                        x-transition:enter="transition ease-out duration-200"
                        x-transition:enter-start="opacity-0 translate-y-2 scale-95"
                        x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                        x-transition:leave="transition ease-in duration-150"
                        x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                        x-transition:leave-end="opacity-0 translate-y-2 scale-95"
                        @click.outside="menuOpen = false"
                        @keydown.escape.window="menuOpen = false"
                        class="absolute bottom-full left-0 mb-2 w-full overflow-hidden rounded-xl border border-line bg-surface shadow-lift"
                        x-cloak
                    >
                        <div class="border-b border-line px-3 py-2.5">
                            <p class="truncate text-xs text-ink-muted">{{ __('app.common.signed_in_as') }}</p>
                            <p class="truncate text-sm font-medium text-ink">{{ auth()->user()->email }}</p>
                        </div>

                        <a
                            href="{{ route('profile.edit') }}"
                            class="group flex w-full items-center gap-2.5 border-b border-line px-3 py-2.5 text-sm font-medium text-ink transition hover:bg-surface-sunken"
                        >
                            <svg class="size-4.5 text-ink-muted transition-transform group-hover:scale-110" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M10 9a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm-7 9a7 7 0 1 1 14 0H3Z" clip-rule="evenodd" />
                            </svg>
                            {{ __('app.nav.account') }}
                        </a>

                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button
                                type="submit"
                                class="group flex w-full items-center gap-2.5 px-3 py-2.5 text-sm font-medium text-rose-600 dark:text-rose-400 transition hover:bg-rose-50 dark:hover:bg-rose-500/15"
                            >
                                <svg class="size-4.5 transition-transform group-hover:translate-x-0.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path d="M3 4a1 1 0 0 1 1-1h6a1 1 0 1 1 0 2H5v10h5a1 1 0 1 1 0 2H4a1 1 0 0 1-1-1V4Zm10.293 2.293a1 1 0 0 1 1.414 0l3 3a1 1 0 0 1 0 1.414l-3 3a1 1 0 0 1-1.414-1.414L14.586 11H9a1 1 0 1 1 0-2h5.586l-1.293-1.293a1 1 0 0 1 0-1.414Z" />
                                </svg>
                                {{ __('app.common.sign_out') }}
                            </button>
                        </form>
                    </div>

                    <button
                        type="button"
                        @click="menuOpen = !menuOpen"
                        :aria-expanded="menuOpen"
                        class="flex w-full items-center gap-3 rounded-xl px-2 py-2 text-left transition hover:bg-surface-sunken"
                    >
                        <span
                            class="flex size-9 shrink-0 items-center justify-center rounded-full bg-linear-to-br from-brand-400 to-brand-600 text-xs font-semibold text-white"
                            aria-hidden="true"
                        >{{ auth()->user()->initials() }}</span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-medium text-ink">{{ auth()->user()->name }}</span>
                            <span class="block truncate text-xs text-ink-muted">{{ auth()->user()->role->label() }}</span>
                        </span>
                        <svg
                            class="size-4 shrink-0 text-ink-subtle transition-transform duration-200"
                            :class="menuOpen && 'rotate-180'"
                            viewBox="0 0 20 20"
                            fill="currentColor"
                            aria-hidden="true"
                        >
                            <path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
                        </svg>
                    </button>
                </div>
            </div>
        </aside>

        <!-- Content -->
        <div class="lg:pl-64">
            <header class="sticky top-0 z-20 flex h-16 items-center gap-3 border-b border-line bg-surface/70 px-4 backdrop-blur-xl sm:px-6 print:hidden">
                <button
                    type="button"
                    @click="sidebarOpen = true"
                    class="rounded-lg p-2 text-ink-muted transition hover:bg-surface-sunken hover:text-ink active:scale-95 lg:hidden"
                >
                    <span class="sr-only">{{ __('app.nav.open') }}</span>
                    <svg class="size-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path d="M3 5a1 1 0 0 1 1-1h12a1 1 0 1 1 0 2H4a1 1 0 0 1-1-1Zm0 5a1 1 0 0 1 1-1h12a1 1 0 1 1 0 2H4a1 1 0 0 1-1-1Zm1 4a1 1 0 1 0 0 2h12a1 1 0 1 0 0-2H4Z" />
                    </svg>
                </button>

                <div class="min-w-0 flex-1">
                    <h1 class="animate-slide-left truncate text-base font-semibold tracking-tight text-ink">
                        {{ $header ?? $title }}
                    </h1>
                </div>

                @isset($actions)
                    <div class="animate-fade flex shrink-0 items-center gap-2">
                        {{ $actions }}
                    </div>
                @endisset

                <div class="animate-fade shrink-0">
                    <x-language-switcher />
                </div>

                <div class="animate-fade shrink-0">
                    <x-theme-toggle />
                </div>
            </header>

            <main id="main-content" class="px-4 py-6 sm:px-6 lg:px-8">
                <x-alert />

                <div class="animate-rise">
                    {{ $slot }}
                </div>
            </main>
        </div>
    </div>

    {{--
        Page specific scripts. These are classic scripts, so they run while the
        document is parsed, which is before the deferred Vite module starts
        Alpine. Anything a page registers here is therefore already defined by
        the time an x-data expression is evaluated.
    --}}
    @stack('scripts')
</body>
</html>
