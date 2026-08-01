@php
    $productOptions = $products->mapWithKeys(fn ($item) => [
        $item->id => $item->name.' ('.$item->sku.') — '.__('app.movement.on_hand_suffix', [
            'count' => number_format($item->quantity),
            'unit' => $item->unit,
        ]),
    ])->all();

    $selectedId = old('product_id', $selectedProduct?->id);
@endphp

<x-app-layout :title="__('app.movement.record')">
    <div class="mx-auto max-w-2xl">
        <x-card :title="__('app.movement.record')" :description="__('app.movement.record_sub')">
            @if ($products->isEmpty())
                <x-empty-state
                    :title="__('app.movement.no_active_products')"
                    :description="__('app.movement.no_active_products_sub')"
                >
                    @if (auth()->user()->canManageCatalog())
                        <x-button :href="route('products.create')" size="sm">{{ __('app.product.add') }}</x-button>
                    @endif
                </x-empty-state>
            @else
                <form method="POST" action="{{ route('movements.store') }}" x-data="{ type: '{{ old('type', $type) }}' }">
                    @csrf

                    <div class="grid grid-cols-1 gap-5 p-4 sm:p-5">
                        <x-field
                            :label="__('app.product.one')"
                            name="product_id"
                            type="select"
                            :value="$selectedId"
                            :placeholder="__('app.movement.choose_product')"
                            :options="$productOptions"
                            required
                        />

                        <fieldset>
                            <legend class="block text-sm font-medium text-ink">{{ __('app.movement.type') }}</legend>
                            {{-- Two columns when Adjustment is not on offer, so the
                                 row does not sit with an empty third slot. Both class
                                 strings are written out literally so Tailwind's
                                 scanner keeps them in the compiled stylesheet. --}}
                            <div class="mt-2 grid grid-cols-1 gap-2 {{ count($types) > 2 ? 'sm:grid-cols-3' : 'sm:grid-cols-2' }}">
                                @foreach ($types as $value => $label)
                                    <label
                                        class="flex cursor-pointer items-center gap-2 rounded-lg border px-3 py-2 text-sm transition"
                                        :class="type === '{{ $value }}' ? 'border-brand-500 bg-brand-50 dark:bg-brand-500/20 text-brand-700 dark:text-brand-300' : 'border-line bg-surface text-ink hover:bg-surface-sunken'"
                                    >
                                        <input
                                            type="radio"
                                            name="type"
                                            value="{{ $value }}"
                                            x-model="type"
                                            class="size-4 border-line text-brand-600 dark:text-brand-400 focus:ring-2 focus:ring-brand-500/40"
                                            required
                                        >
                                        <span>{{ $label }}</span>
                                    </label>
                                @endforeach
                            </div>
                            @error('type')
                                <p class="mt-1 text-xs font-medium text-rose-600 dark:text-rose-400">{{ $message }}</p>
                            @enderror
                        </fieldset>

                        {{--
                            One input only, so the form never submits two values for
                            "quantity". The label and hint switch with the movement type.
                        --}}
                        <div class="space-y-1.5">
                            <label for="quantity" class="block text-sm font-medium text-ink">
                                <span x-show="type !== 'adjustment'">{{ __('app.movement.quantity') }}</span>
                                <span x-show="type === 'adjustment'" x-cloak>{{ __('app.movement.counted_quantity') }}</span>
                                <span class="text-rose-600 dark:text-rose-400" aria-hidden="true">*</span>
                                <span class="sr-only">(required)</span>
                            </label>

                            <input
                                type="number"
                                id="quantity"
                                name="quantity"
                                value="{{ old('quantity', 1) }}"
                                step="1"
                                :min="type === 'adjustment' ? 0 : 1"
                                required
                                aria-describedby="quantity-hint @error('quantity') quantity-error @enderror"
                                @error('quantity') aria-invalid="true" @enderror
                                class="block w-full rounded-lg border bg-surface px-3 py-2 text-sm text-ink shadow-sm transition placeholder:text-ink-subtle focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30 focus:outline-none @error('quantity') border-rose-400 dark:border-rose-500 @else border-line @enderror"
                            >

                            <p id="quantity-hint" class="text-xs text-ink-muted">
                                <span x-show="type === 'in'">{{ __('app.movement.qty_in_hint') }}</span>
                                <span x-show="type === 'out'" x-cloak>{{ __('app.movement.qty_out_hint') }}</span>
                                <span x-show="type === 'adjustment'" x-cloak>
                                    {{ __('app.movement.qty_adj_hint') }}
                                </span>
                            </p>

                            @error('quantity')
                                <p id="quantity-error" class="text-xs font-medium text-rose-600 dark:text-rose-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <x-field
                            :label="__('app.movement.unit_cost')"
                            name="unit_cost"
                            type="number"
                            :value="old('unit_cost')"
                            step="0.01"
                            min="0"
                            :prefix="config('app.currency_symbol')"
                            :hint="__('app.movement.unit_cost_hint')"
                        />

                        <x-field
                            :label="__('app.movement.reference')"
                            name="reference"
                            :value="old('reference')"
                            :placeholder="__('app.movement.reference_placeholder')"
                            :hint="__('app.movement.reference_hint')"
                        />

                        <x-field
                            :label="__('app.common.notes')"
                            name="note"
                            type="textarea"
                            :value="old('note')"
                            rows="2"
                            placeholder=""
                        />
                    </div>

                    <div class="flex items-center justify-end gap-2 border-t border-line bg-surface-sunken px-4 py-3 sm:px-5">
                        <x-button :href="url()->previous(route('movements.index'))" variant="secondary">{{ __('app.common.cancel') }}</x-button>
                        <x-button type="submit">{{ __('app.movement.record') }}</x-button>
                    </div>
                </form>
            @endif
        </x-card>
    </div>
</x-app-layout>
