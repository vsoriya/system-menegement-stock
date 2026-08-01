{{--
    Shared product form.

    @param \App\Models\Product $product
    @param \Illuminate\Support\Collection $categories
    @param \Illuminate\Support\Collection $suppliers
    @param bool $creating
--}}

<div class="grid grid-cols-1 gap-5 p-4 sm:p-5 lg:grid-cols-2">
    <x-field
        :label="__('app.product.name')"
        name="name"
        :value="$product->name"
        placeholder="e.g. Wireless mouse"
        required
        class="lg:col-span-2"
    />

    <x-field
        :label="__('app.product.sku')"
        name="sku"
        :value="$product->sku"
        placeholder="e.g. MSE-1001"
        :hint="__('app.product.sku_hint')"
        required
    />

    {{-- The till matches a scanned code against this field exactly, so without
         it barcode scanning has nothing to find. --}}
    <x-field
        :label="__('app.product.barcode')"
        name="barcode"
        :value="$product->barcode"
        :hint="__('app.product.barcode_hint')"
        autocomplete="off"
    />

    <x-field
        :label="__('app.product.unit')"
        name="unit"
        :value="$product->unit"
        placeholder="pcs"
        :hint="__('app.product.unit_hint')"
        required
    />

    {{--
        Written out rather than using x-field, because a file input needs a
        thumbnail of what is already saved, a live preview of a newly chosen
        file, and a way to clear the existing one. This is the picture the till
        grid shows, so getting it wrong is immediately visible.
    --}}
    <div class="space-y-2 lg:col-span-2" x-data="{ preview: @js($product->image_url) }">
        <label for="image" class="block text-sm font-medium text-ink">{{ __('app.product.image') }}</label>

        <div class="flex flex-wrap items-start gap-4">
            <span class="flex size-24 shrink-0 items-center justify-center overflow-hidden rounded-xl border border-line bg-surface-sunken">
                <img x-show="preview" :src="preview" alt="" class="size-full object-cover">

                <svg x-show="! preview" class="size-8 text-ink-subtle" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909M18 10.5h.008v.008H18V10.5zm2.25 6.75V6.75A2.25 2.25 0 0018 4.5H6a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 006 19.5h12a2.25 2.25 0 002.25-2.25z" />
                </svg>
            </span>

            <div class="min-w-56 flex-1 space-y-2">
                <input
                    type="file"
                    id="image"
                    name="image"
                    accept="image/jpeg,image/png,image/webp"
                    @change="preview = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : @js($product->image_url)"
                    class="block w-full cursor-pointer rounded-xl border border-line bg-surface text-sm text-ink shadow-soft file:mr-3 file:cursor-pointer file:border-0 file:bg-surface-sunken file:px-3 file:py-2 file:text-sm file:font-medium file:text-ink hover:border-ink-subtle focus:border-brand-500 focus:ring-4 focus:ring-brand-500/15 focus:outline-none"
                >

                <p class="text-xs text-ink-muted">{{ __('app.product.image_hint') }}</p>

                @error('image')
                    <p class="text-xs font-medium text-rose-600 dark:text-rose-400">{{ $message }}</p>
                @enderror

                @if (filled($product->image_path))
                    <label class="flex items-center gap-2 text-sm text-ink">
                        <input type="hidden" name="remove_image" value="0">
                        <input
                            type="checkbox"
                            name="remove_image"
                            value="1"
                            @checked(old('remove_image'))
                            @change="if ($event.target.checked) preview = null"
                            class="size-4 rounded border-line text-brand-600 dark:text-brand-400 focus:ring-2 focus:ring-brand-500/40"
                        >
                        <span>{{ __('app.product.image_remove') }}</span>
                    </label>
                @endif
            </div>
        </div>
    </div>

    <x-field
        :label="__('app.product.category')"
        name="category_id"
        type="select"
        :value="$product->category_id"
        :placeholder="__('app.product.uncategorised')"
        :options="$categories->pluck('name', 'id')->all()"
    />

    <x-field
        :label="__('app.product.supplier')"
        name="supplier_id"
        type="select"
        :value="$product->supplier_id"
        :placeholder="__('app.product.no_supplier')"
        :options="$suppliers->pluck('name', 'id')->all()"
    />

    <x-field
        :label="__('app.product.cost_price')"
        name="cost_price"
        type="number"
        :value="$product->cost_price ?? '0.00'"
        step="0.01"
        min="0"
        :prefix="config('app.currency_symbol')"
        :hint="__('app.product.cost_hint')"
        required
    />

    <x-field
        :label="__('app.product.sale_price')"
        name="sale_price"
        type="number"
        :value="$product->sale_price ?? '0.00'"
        step="0.01"
        min="0"
        :prefix="config('app.currency_symbol')"
        :hint="__('app.product.sale_hint')"
        required
    />

    @if ($creating)
        <x-field
            :label="__('app.product.opening_qty')"
            name="quantity"
            type="number"
            :value="$product->quantity ?? 0"
            min="0"
            step="1"
            :hint="__('app.product.opening_hint')"
            required
        />
    @else
        <div class="space-y-1.5">
            <span class="block text-sm font-medium text-ink">{{ __('app.product.qty_on_hand') }}</span>
            <p class="rounded-lg border border-line bg-surface-sunken px-3 py-2 text-sm tabular-nums text-ink-muted">
                @qty($product->quantity) {{ $product->unit }}
            </p>
            {{-- Was a hardcoded English sentence with the link spliced into the
                 middle of it. These two keys already existed in both languages,
                 waiting to be used. --}}
            <p class="text-xs text-ink-muted">
                {!! __('app.product.qty_locked', [
                    'link' => '<a href="'.e(route('movements.create', ['product' => $product->id])).'" class="font-medium text-brand-600 dark:text-brand-400 hover:text-brand-700 dark:hover:text-brand-300">'
                        .e(__('app.product.qty_locked_link'))
                        .'</a>',
                ]) !!}
            </p>
        </div>
    @endif

    <x-field
        :label="__('app.product.reorder_level')"
        name="reorder_level"
        type="number"
        :value="$product->reorder_level ?? 0"
        min="0"
        step="1"
        :hint="__('app.product.reorder_hint')"
        required
    />

    <x-field
        :label="__('app.common.description')"
        name="description"
        type="textarea"
        :value="$product->description"
        rows="3"
        placeholder=""
        class="lg:col-span-2"
    />

    <x-field
        :label="__('app.common.status')"
        name="is_active"
        type="checkbox"
        :value="$product->is_active ?? true"
        :hint="__('app.category.active_hint')"
        class="lg:col-span-2"
    />
</div>
