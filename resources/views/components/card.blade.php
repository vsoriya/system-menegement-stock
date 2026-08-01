@props(['title' => null, 'description' => null])

<section {{ $attributes->class('animate-rise stagger overflow-hidden rounded-2xl border border-line bg-surface/90 shadow-soft backdrop-blur-sm') }}>
    @if ($title || isset($cardActions))
        <header class="flex flex-wrap items-center justify-between gap-3 border-b border-line bg-linear-to-r from-surface-sunken/80 to-transparent px-4 py-3 sm:px-5">
            <div class="min-w-0">
                @if ($title)
                    <h2 class="truncate text-sm font-semibold tracking-tight text-ink">{{ $title }}</h2>
                @endif
                @if ($description)
                    <p class="mt-0.5 truncate text-xs text-ink-muted">{{ $description }}</p>
                @endif
            </div>

            @isset($cardActions)
                <div class="flex shrink-0 items-center gap-2">{{ $cardActions }}</div>
            @endisset
        </header>
    @endif

    {{ $slot }}
</section>
