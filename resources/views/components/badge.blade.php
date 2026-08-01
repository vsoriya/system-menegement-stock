@props([
    'classes' => 'bg-surface-sunken text-ink-muted ring-line',
    // Show a leading dot. Use 'pulse' to draw attention to urgent states.
    'dot' => false,
])

<span {{ $attributes->class([
    'inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset whitespace-nowrap transition',
    $classes,
]) }}>
    @if ($dot)
        <span class="relative flex size-1.5 shrink-0" aria-hidden="true">
            <span class="absolute inline-flex size-full rounded-full bg-current {{ $dot === 'pulse' ? 'animate-halo' : '' }}"></span>
            <span class="relative inline-flex size-1.5 rounded-full bg-current"></span>
        </span>
    @endif

    {{ $slot }}
</span>
