@php
    $locales = \App\Http\Middleware\SetLocale::SUPPORTED;
    $current = app()->getLocale();
@endphp

<div
    class="flex items-center gap-0.5 rounded-xl border border-line bg-surface-sunken p-0.5"
    role="group"
    aria-label="{{ __('app.common.language') }}"
>
    @foreach ($locales as $code => $label)
        <form method="POST" action="{{ route('locale.update', $code) }}">
            @csrf
            <button
                type="submit"
                @if ($code === $current) aria-current="true" @endif
                class="rounded-lg px-2 py-1.5 text-xs font-medium transition duration-200 {{ $code === $current
                    ? 'bg-surface text-brand-600 dark:text-brand-400 shadow-soft'
                    : 'text-ink-subtle hover:text-ink' }}"
            >
                {{ $label }}
            </button>
        </form>
    @endforeach
</div>
