@props([
    'label',
    'name',
    'type' => 'text',
    'value' => null,
    'options' => null,
    'placeholder' => null,
    'required' => false,
    'hint' => null,
    'rows' => 3,
    'step' => null,
    'min' => null,
    'max' => null,
    'prefix' => null,
    'disabled' => false,
])

@php
    $id = $attributes->get('id', $name);
    $current = old($name, $value);
    $hasError = $errors->has($name);
    $describedBy = collect([
        $hint ? $id.'-hint' : null,
        $hasError ? $id.'-error' : null,
    ])->filter()->implode(' ');

    $controlClasses = implode(' ', array_filter([
        'block w-full rounded-xl border bg-surface text-sm text-ink shadow-soft',
        'transition duration-200 ease-out',
        'placeholder:text-ink-subtle',
        'hover:border-ink-subtle',
        'focus:border-brand-500 focus:ring-4 focus:ring-brand-500/15 focus:outline-none',
        'disabled:cursor-not-allowed disabled:bg-surface-sunken disabled:text-ink-muted',
        $hasError ? 'border-rose-400 dark:border-rose-500 focus:border-rose-500 focus:ring-rose-500/15' : 'border-line',
        $prefix ? 'pl-7 pr-3 py-2' : 'px-3 py-2',
    ]));

    // Anything not consumed as a prop (autocomplete, autofocus, x-model, ...)
    // is forwarded to the control itself rather than silently dropped.
    $control = $attributes->except(['class', 'id']);
@endphp

<div {{ $attributes->only('class')->class('space-y-1.5') }}>
    <label for="{{ $id }}" class="block text-sm font-medium text-ink">
        {{ $label }}
        @if ($required)
            <span class="text-rose-600 dark:text-rose-400" aria-hidden="true">*</span>
            <span class="sr-only">{{ __('app.common.required_field') }}</span>
        @endif
    </label>

    <div class="relative">
        @if ($prefix)
            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-sm text-ink-muted" aria-hidden="true">
                {{ $prefix }}
            </span>
        @endif

        @if ($type === 'textarea')
            <textarea
                id="{{ $id }}"
                name="{{ $name }}"
                rows="{{ $rows }}"
                @if ($placeholder) placeholder="{{ $placeholder }}" @endif
                @if ($required) required @endif
                @if ($disabled) disabled @endif
                @if ($hasError) aria-invalid="true" @endif
                @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
                {{ $control->class($controlClasses) }}
            >{{ $current }}</textarea>

        @elseif ($type === 'select')
            <select
                id="{{ $id }}"
                name="{{ $name }}"
                @if ($required) required @endif
                @if ($disabled) disabled @endif
                @if ($hasError) aria-invalid="true" @endif
                @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
                {{ $control->class($controlClasses) }}
            >
                @if ($placeholder)
                    <option value="">{{ $placeholder }}</option>
                @endif

                @foreach ($options ?? [] as $optionValue => $optionLabel)
                    <option value="{{ $optionValue }}" @selected((string) $current === (string) $optionValue)>
                        {{ $optionLabel }}
                    </option>
                @endforeach
            </select>

        @elseif ($type === 'checkbox')
            <label for="{{ $id }}" class="flex items-center gap-2 text-sm text-ink">
                <input type="hidden" name="{{ $name }}" value="0">
                <input
                    type="checkbox"
                    id="{{ $id }}"
                    name="{{ $name }}"
                    value="1"
                    @checked((bool) $current)
                    @if ($disabled) disabled @endif
                    @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
                    {{ $control->class('size-4 rounded border-line text-brand-600 dark:text-brand-400 focus:ring-2 focus:ring-brand-500/40') }}
                >
                <span>{{ $hint ?? 'Enabled' }}</span>
            </label>

        @else
            <input
                type="{{ $type }}"
                id="{{ $id }}"
                name="{{ $name }}"
                value="{{ $current }}"
                @if ($placeholder) placeholder="{{ $placeholder }}" @endif
                @if ($required) required @endif
                @if ($disabled) disabled @endif
                @if ($step !== null) step="{{ $step }}" @endif
                @if ($min !== null) min="{{ $min }}" @endif
                @if ($max !== null) max="{{ $max }}" @endif
                @if ($hasError) aria-invalid="true" @endif
                @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
                {{ $control->class($controlClasses) }}
            >
        @endif
    </div>

    @if ($hint && $type !== 'checkbox')
        <p id="{{ $id }}-hint" class="text-xs text-ink-muted">{{ $hint }}</p>
    @endif

    @error($name)
        <p id="{{ $id }}-error" class="animate-slide-left flex items-center gap-1 text-xs font-medium text-rose-600 dark:text-rose-400">
            <svg class="size-3.5 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M18 10a8 8 0 1 1-16 0 8 8 0 0 1 16 0Zm-8-4a.75.75 0 0 1 .75.75v3.5a.75.75 0 0 1-1.5 0v-3.5A.75.75 0 0 1 10 6Zm0 8a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd" />
            </svg>
            {{ $message }}
        </p>
    @enderror
</div>
