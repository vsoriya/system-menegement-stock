@props(['href', 'active' => false, 'icon' => null])

<a
    href="{{ $href }}"
    @if ($active) aria-current="page" @endif
    {{ $attributes->class([
        'group animate-slide-left stagger relative flex items-center gap-3 overflow-hidden rounded-xl px-3 py-2 text-sm font-medium transition duration-200',
        'bg-brand-50 dark:bg-brand-500/20 text-brand-700 dark:text-brand-300' => $active,
        'text-ink-muted hover:bg-surface-sunken hover:text-ink' => ! $active,
    ]) }}
>
    {{-- Active page marker on the leading edge. --}}
    <span
        aria-hidden="true"
        class="absolute inset-y-1 left-0 w-1 rounded-full bg-brand-600 transition-transform duration-300 ease-[cubic-bezier(0.22,1,0.36,1)] {{ $active ? 'scale-y-100' : 'scale-y-0' }}"
    ></span>

    @if ($icon)
        <svg
            class="size-5 shrink-0 transition-transform duration-200 group-hover:scale-110 {{ $active ? 'text-brand-600 dark:text-brand-400' : 'text-ink-subtle group-hover:text-ink-muted' }}"
            viewBox="0 0 20 20"
            fill="currentColor"
            aria-hidden="true"
        >
            {{ $icon }}
        </svg>
    @endif

    <span class="truncate">{{ $slot }}</span>
</a>
