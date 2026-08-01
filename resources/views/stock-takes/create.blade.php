<x-app-layout :title="__('app.stocktake.add')">
    <div class="mx-auto max-w-2xl">
        <x-card :title="__('app.stocktake.add')" :description="__('app.stocktake.new_sub')">
            <form method="POST" action="{{ route('stock-takes.store') }}">
                @csrf

                <div
                    class="grid grid-cols-1 gap-5 p-4 sm:p-5"
                    x-data="{ scope: @js(old('scope', 'all')) }"
                >
                    <x-field
                        :label="__('app.stocktake.reference')"
                        name="reference"
                        :value="$take->reference"
                        :placeholder="__('app.stocktake.reference_placeholder')"
                        required
                    />

                    <x-field
                        :label="__('app.stocktake.counted_at')"
                        name="counted_at"
                        type="date"
                        :value="$take->counted_at?->toDateString()"
                        required
                    />

                    <fieldset>
                        <legend class="block text-sm font-medium text-ink">{{ __('app.stocktake.scope') }}</legend>

                        <div class="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-2">
                            @foreach (['all' => __('app.stocktake.scope_all'), 'category' => __('app.stocktake.scope_category')] as $value => $label)
                                <label
                                    class="flex cursor-pointer items-center gap-2 rounded-lg border px-3 py-2 text-sm transition"
                                    :class="scope === '{{ $value }}'
                                        ? 'border-brand-500 bg-brand-50 text-brand-700 dark:bg-brand-500/20 dark:text-brand-300'
                                        : 'border-line bg-surface text-ink hover:bg-surface-sunken'"
                                >
                                    <input
                                        type="radio"
                                        name="scope"
                                        value="{{ $value }}"
                                        x-model="scope"
                                        class="size-4 border-line text-brand-600 focus:ring-2 focus:ring-brand-500/40 dark:text-brand-400"
                                        required
                                    >
                                    <span>{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>

                        @error('scope')
                            <p class="mt-1 text-xs font-medium text-rose-600 dark:text-rose-400">{{ $message }}</p>
                        @enderror
                    </fieldset>

                    <div x-show="scope === 'category'" x-cloak>
                        <x-field
                            :label="__('app.product.category')"
                            name="category_id"
                            type="select"
                            :value="$take->category_id"
                            :placeholder="__('app.product.all_categories')"
                            :options="$categories"
                        />
                    </div>

                    <x-field
                        :label="__('app.common.notes')"
                        name="notes"
                        type="textarea"
                        :value="$take->notes"
                        rows="2"
                    />
                </div>

                <div class="flex items-center justify-end gap-2 border-t border-line bg-surface-sunken px-4 py-3 sm:px-5">
                    <x-button :href="route('stock-takes.index')" variant="secondary">{{ __('app.common.cancel') }}</x-button>
                    <x-button type="submit">{{ __('app.stocktake.add') }}</x-button>
                </div>
            </form>
        </x-card>
    </div>
</x-app-layout>
