@props([
    'variant' => 'primary',
    'href' => null,
    'type' => 'submit',
    'size' => 'md',
])

@php
    $base = implode(' ', [
        'group relative inline-flex items-center justify-center gap-1.5 rounded-xl font-medium',
        'transition duration-200 ease-out',
        'focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2',
        'active:scale-[0.97]',
        'disabled:cursor-not-allowed disabled:opacity-60 disabled:active:scale-100',
    ]);

    $sizes = [
        'sm' => 'px-2.5 py-1.5 text-xs',
        'md' => 'px-3.5 py-2 text-sm',
    ];

    $variants = [
        'primary' => 'bg-linear-to-b from-brand-500 to-brand-600 text-white shadow-soft hover:shadow-glow hover:-translate-y-0.5 focus-visible:ring-brand-500',
        'secondary' => 'border border-line bg-surface text-ink shadow-soft hover:-translate-y-0.5 hover:border-ink-subtle hover:bg-surface-sunken focus-visible:ring-brand-500',
        'danger' => 'bg-linear-to-b from-rose-500 to-rose-600 text-white shadow-soft hover:-translate-y-0.5 hover:shadow-lift focus-visible:ring-rose-500',
        'ghost' => 'text-ink-muted hover:bg-surface-sunken hover:text-ink focus-visible:ring-brand-500',
    ];

    $classes = implode(' ', [
        $base,
        $sizes[$size] ?? $sizes['md'],
        $variants[$variant] ?? $variants['primary'],
    ]);
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->class($classes) }}>{{ $slot }}</button>
@endif
