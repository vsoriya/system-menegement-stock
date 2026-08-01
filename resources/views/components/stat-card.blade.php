@props([
    'label',
    'value' => null,
    'hint' => null,
    'tone' => 'default',
    'href' => null,
    // Pass a raw number to animate the figure counting up from zero.
    'count' => null,
    'prefix' => '',
    'suffix' => '',
    'decimals' => 0,
])

@php
    $tones = [
        'danger' => ['text' => 'text-rose-600 dark:text-rose-400', 'chip' => 'bg-rose-50 dark:bg-rose-500/15 text-rose-600 dark:text-rose-400', 'bar' => 'from-rose-400 to-rose-600'],
        'warning' => ['text' => 'text-amber-600 dark:text-amber-400', 'chip' => 'bg-amber-50 dark:bg-amber-500/15 text-amber-600 dark:text-amber-400', 'bar' => 'from-amber-400 to-amber-600'],
        'success' => ['text' => 'text-emerald-600 dark:text-emerald-400', 'chip' => 'bg-emerald-50 dark:bg-emerald-500/15 text-emerald-600 dark:text-emerald-400', 'bar' => 'from-emerald-400 to-emerald-600'],
        'default' => ['text' => 'text-ink', 'chip' => 'bg-brand-50 dark:bg-brand-500/20 text-brand-600 dark:text-brand-400', 'bar' => 'from-brand-400 to-brand-600'],
    ];

    $t = $tones[$tone] ?? $tones['default'];
    $tag = $href ? 'a' : 'div';
@endphp

<{{ $tag }}
    @if ($href) href="{{ $href }}" @endif
    {{ $attributes->class([
        'animate-pop stagger group relative block overflow-hidden rounded-2xl border border-line bg-surface/90 p-4 shadow-soft backdrop-blur-sm',
        'lift hover:border-brand-300 dark:hover:border-brand-500' => (bool) $href,
    ]) }}
>
    {{-- Accent bar that sweeps in along the top edge. --}}
    <span
        aria-hidden="true"
        class="animate-grow-line absolute inset-x-0 top-0 h-0.5 origin-left bg-linear-to-r {{ $t['bar'] }}"
    ></span>

    <div class="flex items-start justify-between gap-3">
        <p class="text-[0.6875rem] font-semibold uppercase tracking-wider text-ink-muted">{{ $label }}</p>

        @isset($icon)
            <span class="flex size-8 shrink-0 items-center justify-center rounded-xl {{ $t['chip'] }} transition-transform duration-300 group-hover:scale-110 group-hover:rotate-6">
                <svg class="size-4.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">{{ $icon }}</svg>
            </span>
        @endisset
    </div>

    @if ($count !== null)
        {{-- Eased count-up so the figure animates in rather than snapping. --}}
        <p
            x-data="{
                shown: '{{ $prefix }}0{{ $suffix }}',
                start() {
                    const target = {{ (float) $count }};
                    const decimals = {{ (int) $decimals }};
                    const format = (n) => '{{ $prefix }}' + n.toLocaleString(undefined, {
                        minimumFractionDigits: decimals,
                        maximumFractionDigits: decimals,
                    }) + '{{ $suffix }}';

                    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                        this.shown = format(target);
                        return;
                    }

                    const duration = 900;
                    const startedAt = performance.now();
                    const tick = (now) => {
                        const progress = Math.min((now - startedAt) / duration, 1);
                        const eased = 1 - Math.pow(1 - progress, 3);
                        this.shown = format(target * eased);
                        if (progress < 1) requestAnimationFrame(tick);
                    };
                    requestAnimationFrame(tick);
                },
            }"
            x-init="start()"
            x-text="shown"
            class="mt-2 text-2xl font-semibold tabular-nums tracking-tight {{ $t['text'] }}"
        >{{ $prefix }}{{ number_format((float) $count, (int) $decimals) }}{{ $suffix }}</p>
    @else
        <p class="mt-2 text-2xl font-semibold tabular-nums tracking-tight {{ $t['text'] }}">{{ $value }}</p>
    @endif

    @if ($hint)
        <p class="mt-1 text-xs text-ink-muted">{{ $hint }}</p>
    @endif

    @if ($href)
        <span class="mt-2 inline-flex items-center gap-1 text-xs font-medium text-brand-600 dark:text-brand-400 opacity-0 transition-opacity duration-200 group-hover:opacity-100">
            View
            <svg class="size-3.5 transition-transform duration-200 group-hover:translate-x-0.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M7.22 4.22a.75.75 0 0 1 1.06 0l5.25 5.25a.75.75 0 0 1 0 1.06l-5.25 5.25a.75.75 0 1 1-1.06-1.06L11.94 10 7.22 5.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
            </svg>
        </span>
    @endif
</{{ $tag }}>
