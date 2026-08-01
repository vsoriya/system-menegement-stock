{{--
    Shared purchase order form.

    @param \App\Models\PurchaseOrder $order
    @param array<int, string> $suppliers
    @param \Illuminate\Database\Eloquent\Collection $products
    @param array<int, array<string, string>> $lineRows
--}}

@php
    // Product details the line editor needs client side: the default cost to
    // prefill, and the unit shown next to the quantity.
    $productData = $products
        ->map(fn ($product) => [
            'id' => (string) $product->id,
            'unit' => $product->unit,
            'cost' => number_format((float) $product->cost_price, 2, '.', ''),
        ])
        ->values()
        ->all();

    // Per line validation messages, handed to Alpine so a rejected row can show
    // its own error next to the field that caused it.
    $lineErrors = collect($errors->messages())
        ->filter(fn ($messages, $key): bool => str_starts_with((string) $key, 'lines.'))
        ->all();

    $currency = config('app.currency_symbol');
@endphp

<div class="grid grid-cols-1 gap-5 p-4 sm:p-5 lg:grid-cols-3">
    <x-field
        :label="__('app.po.supplier')"
        name="supplier_id"
        type="select"
        :value="$order->supplier_id"
        :placeholder="__('app.po.choose_supplier')"
        :options="$suppliers"
        required
    />

    <x-field
        :label="__('app.po.ordered_at')"
        name="ordered_at"
        type="date"
        :value="$order->ordered_at?->toDateString()"
        required
    />

    <x-field
        :label="__('app.po.expected_at')"
        name="expected_at"
        type="date"
        :value="$order->expected_at?->toDateString()"
        :hint="__('app.common.optional')"
    />

    <x-field
        :label="__('app.common.notes')"
        name="notes"
        type="textarea"
        :value="$order->notes"
        rows="2"
        class="lg:col-span-3"
    />
</div>

{{--
    Line editor. Rows live in an Alpine array so they can be added and removed
    without a round trip. Input names are rebuilt from the array index on every
    render, which keeps the submitted "lines" array contiguous after a removal.
--}}
<div
    class="border-t border-line"
    x-data="{
        products: @js($productData),
        lineErrors: @js($lineErrors),
        rows: @js($lineRows),
        nextKey: 0,
        init() {
            this.rows = this.rows.map((row) => ({ ...row, key: this.nextKey++ }));

            if (this.rows.length === 0) {
                this.add();
            }
        },
        add() {
            this.rows.push({ key: this.nextKey++, product_id: '', quantity_ordered: '1', unit_cost: '' });
        },
        remove(index) {
            this.rows.splice(index, 1);

            if (this.rows.length === 0) {
                this.add();
            }
        },
        errorsFor(index, field) {
            return this.lineErrors[`lines.${index}.${field}`] ?? [];
        },
        product(row) {
            return this.products.find((item) => item.id === String(row.product_id)) ?? null;
        },
        onProductChange(row) {
            const product = this.product(row);

            // Only prefill an untouched cost, so a typed price is never lost.
            if (product && (row.unit_cost === '' || row.unit_cost === null)) {
                row.unit_cost = product.cost;
            }
        },
        lineTotal(row) {
            const quantity = Number.parseFloat(row.quantity_ordered) || 0;
            const cost = Number.parseFloat(row.unit_cost) || 0;

            return quantity * cost;
        },
        get subtotal() {
            return this.rows.reduce((carry, row) => carry + this.lineTotal(row), 0);
        },
        money(value) {
            return @js($currency) + value.toLocaleString(undefined, {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            });
        },
    }"
>
    <header class="flex flex-wrap items-center justify-between gap-3 bg-surface-sunken/60 px-4 py-3 sm:px-5">
        <div class="min-w-0">
            <h2 class="text-sm font-semibold tracking-tight text-ink">{{ __('app.po.lines') }}</h2>
            <p class="mt-0.5 text-xs text-ink-muted">{{ __('app.po.lines_sub') }}</p>
        </div>

        <x-button type="button" variant="secondary" size="sm" x-on:click="add()">
            {{ __('app.po.add_line') }}
        </x-button>
    </header>

    @error('lines')
        <p class="px-4 pt-3 text-xs font-medium text-rose-600 dark:text-rose-400 sm:px-5">{{ $message }}</p>
    @enderror

    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-line text-sm">
            <thead class="bg-surface-sunken text-left text-xs font-semibold uppercase tracking-wider text-ink-muted">
                <tr>
                    <th scope="col" class="px-4 py-2.5">{{ __('app.po.product') }}</th>
                    <th scope="col" class="w-32 px-4 py-2.5 text-right">{{ __('app.po.quantity') }}</th>
                    <th scope="col" class="w-36 px-4 py-2.5 text-right">{{ __('app.po.unit_cost') }}</th>
                    <th scope="col" class="w-32 px-4 py-2.5 text-right">{{ __('app.po.line_total') }}</th>
                    <th scope="col" class="w-16 px-4 py-2.5">
                        <span class="sr-only">{{ __('app.common.actions') }}</span>
                    </th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                <template x-for="(row, index) in rows" :key="row.key">
                    <tr class="align-top">
                        <td class="px-4 py-3">
                            <label class="sr-only" :for="`line-product-${row.key}`">{{ __('app.po.product') }}</label>

                            {{--
                                Options are rendered server side. Alpine would
                                otherwise bind x-model before its own x-for had
                                added the options, wiping the selected value.
                            --}}
                            <select
                                :id="`line-product-${row.key}`"
                                :name="`lines[${index}][product_id]`"
                                x-model="row.product_id"
                                x-on:change="onProductChange(row)"
                                required
                                class="block w-full rounded-xl border border-line bg-surface px-3 py-2 text-sm text-ink shadow-soft transition hover:border-ink-subtle focus:border-brand-500 focus:ring-4 focus:ring-brand-500/15 focus:outline-none"
                            >
                                <option value="">{{ __('app.movement.choose_product') }}</option>
                                @foreach ($products as $product)
                                    <option value="{{ $product->id }}">{{ $product->name }} ({{ $product->sku }})</option>
                                @endforeach
                            </select>

                            <template x-for="message in errorsFor(index, 'product_id')" :key="message">
                                <p class="mt-1 text-xs font-medium text-rose-600 dark:text-rose-400" x-text="message"></p>
                            </template>
                        </td>

                        <td class="px-4 py-3">
                            <label class="sr-only" :for="`line-quantity-${row.key}`">{{ __('app.po.quantity') }}</label>
                            <input
                                type="number"
                                :id="`line-quantity-${row.key}`"
                                :name="`lines[${index}][quantity_ordered]`"
                                x-model="row.quantity_ordered"
                                min="1"
                                step="1"
                                required
                                class="block w-full rounded-xl border border-line bg-surface px-3 py-2 text-right text-sm tabular-nums text-ink shadow-soft transition hover:border-ink-subtle focus:border-brand-500 focus:ring-4 focus:ring-brand-500/15 focus:outline-none"
                            >
                            <p class="mt-1 text-right text-xs text-ink-muted" x-text="product(row)?.unit ?? ''"></p>

                            <template x-for="message in errorsFor(index, 'quantity_ordered')" :key="message">
                                <p class="mt-1 text-xs font-medium text-rose-600 dark:text-rose-400" x-text="message"></p>
                            </template>
                        </td>

                        <td class="px-4 py-3">
                            <label class="sr-only" :for="`line-cost-${row.key}`">{{ __('app.po.unit_cost') }}</label>
                            <div class="relative">
                                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-sm text-ink-muted" aria-hidden="true">
                                    {{ $currency }}
                                </span>
                                <input
                                    type="number"
                                    :id="`line-cost-${row.key}`"
                                    :name="`lines[${index}][unit_cost]`"
                                    x-model="row.unit_cost"
                                    min="0"
                                    step="0.01"
                                    class="block w-full rounded-xl border border-line bg-surface py-2 pl-7 pr-3 text-right text-sm tabular-nums text-ink shadow-soft transition hover:border-ink-subtle focus:border-brand-500 focus:ring-4 focus:ring-brand-500/15 focus:outline-none"
                                >
                            </div>

                            <template x-for="message in errorsFor(index, 'unit_cost')" :key="message">
                                <p class="mt-1 text-xs font-medium text-rose-600 dark:text-rose-400" x-text="message"></p>
                            </template>
                        </td>

                        <td class="px-4 py-3 text-right text-sm tabular-nums text-ink" x-text="money(lineTotal(row))"></td>

                        <td class="px-4 py-3 text-right">
                            <button
                                type="button"
                                x-on:click="remove(index)"
                                class="rounded-lg p-1.5 text-ink-muted transition hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-500/15 dark:hover:text-rose-400"
                            >
                                <span class="sr-only">{{ __('app.po.remove_line') }}</span>
                                <svg class="size-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M8.75 1a.75.75 0 0 0-.75.75V3H4.75a.75.75 0 0 0 0 1.5h10.5a.75.75 0 0 0 0-1.5H12V1.75a.75.75 0 0 0-.75-.75h-2.5ZM5.5 6a.75.75 0 0 1 .75.75v8.5a.75.75 0 0 0 .75.75h6a.75.75 0 0 0 .75-.75V6.75a.75.75 0 0 1 1.5 0v8.5A2.25 2.25 0 0 1 13 17.5H7a2.25 2.25 0 0 1-2.25-2.25v-8.5A.75.75 0 0 1 5.5 6Z" clip-rule="evenodd" />
                                </svg>
                            </button>
                        </td>
                    </tr>
                </template>
            </tbody>
            <tfoot class="border-t border-line bg-surface-sunken/60">
                <tr>
                    <th scope="row" colspan="3" class="px-4 py-3 text-right text-sm font-semibold text-ink">
                        {{ __('app.po.subtotal') }}
                    </th>
                    <td class="px-4 py-3 text-right text-sm font-semibold tabular-nums text-ink" x-text="money(subtotal)"></td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
