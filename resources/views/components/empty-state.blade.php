@props(['title' => null, 'description' => null])

<div {{ $attributes->class('animate-fade px-4 py-14 text-center') }}>
    <div class="animate-float mx-auto flex size-16 items-center justify-center rounded-2xl bg-linear-to-br from-surface to-surface-sunken ring-1 ring-line ring-inset">
        <svg class="size-8 text-ink-subtle" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5 12 3.75 3.75 7.5 12 11.25l8.25-3.75Zm0 0v9L12 20.25 3.75 16.5v-9m16.5 0L12 11.25m0 0v9" />
        </svg>
    </div>

    <h3 class="mt-4 text-sm font-semibold text-ink">{{ $title ?? __('app.common.nothing_here') }}</h3>

    @if ($description)
        <p class="mx-auto mt-1 max-w-sm text-sm text-ink-muted">{{ $description }}</p>
    @endif

    @if (trim($slot) !== '')
        <div class="mt-5 flex flex-wrap justify-center gap-2">{{ $slot }}</div>
    @endif
</div>
