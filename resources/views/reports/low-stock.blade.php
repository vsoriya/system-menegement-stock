<x-app-layout :title="__('app.report.low_stock')">
    <div class="space-y-5">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <x-stat-card
                :label="__('app.dashboard.out_of_stock')"
                :value="number_format($outOfStockCount)"
                :hint="__('app.report.zero_on_hand')"
                tone="danger"
            />
            <x-stat-card
                :label="__('app.dashboard.low_stock')"
                :value="number_format($lowStockCount)"
                :hint="__('app.dashboard.at_or_below')"
                tone="warning"
            />
            <x-stat-card
                :label="__('app.report.needs_attention')"
                :value="number_format($products->total())"
                :hint="__('app.report.total_lines')"
            />
        </div>

        <x-card
            :title="__('app.report.low_stock_list')"
            :description="__('app.report.low_stock_sub')"
        >
            <x-slot:cardActions>
                <form method="GET" action="{{ route('reports.low-stock') }}" class="flex items-end gap-2">
                    <x-field
                        :label="__('app.product.category')"
                        name="category"
                        type="select"
                        :value="$filters['category'] ?? null"
                        :placeholder="__('app.product.all_categories')"
                        :options="$categories->pluck('name', 'id')->all()"
                        class="w-48"
                    />
                    <x-button type="submit" size="sm">{{ __('app.common.filter') }}</x-button>
                </form>
            </x-slot:cardActions>

            @if ($products->isEmpty())
                <x-empty-state
                    :title="__('app.report.nothing_low')"
                    :description="__('app.report.nothing_low_sub')"
                >
                    <x-button :href="route('products.index')" variant="secondary" size="sm">{{ __('app.report.view_products') }}</x-button>
                </x-empty-state>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-line text-sm">
                        <thead class="bg-surface-sunken text-left text-xs font-semibold uppercase tracking-wider text-ink-muted">
                            <tr>
                                <th scope="col" class="px-4 py-2.5">{{ __('app.product.one') }}</th>
                                <th scope="col" class="px-4 py-2.5">{{ __('app.product.supplier') }}</th>
                                <th scope="col" class="px-4 py-2.5 text-right">{{ __('app.product.on_hand') }}</th>
                                <th scope="col" class="px-4 py-2.5 text-right">{{ __('app.product.reorder_level') }}</th>
                                <th scope="col" class="px-4 py-2.5 text-right">{{ __('app.report.shortfall') }}</th>
                                <th scope="col" class="px-4 py-2.5">{{ __('app.common.status') }}</th>
                                <th scope="col" class="px-4 py-2.5 text-right"><span class="sr-only">{{ __('app.common.actions') }}</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($products as $index => $product)
                                @php $shortfall = max(0, $product->reorder_level - $product->quantity); @endphp
                                <tr class="animate-fade stagger row-hover" style="--d: {{ $index * 35 }}ms">
                                    <td class="px-4 py-3">
                                        <a href="{{ route('products.show', $product) }}" class="font-medium text-ink hover:text-brand-600 dark:hover:text-brand-400 dark:text-brand-400">
                                            {{ $product->name }}
                                        </a>
                                        <p class="font-mono text-xs text-ink-muted">
                                            {{ $product->sku }}
                                            @if ($product->category)
                                                <span class="font-sans">· {{ $product->category->name }}</span>
                                            @endif
                                        </p>
                                    </td>
                                    <td class="px-4 py-3 text-ink-muted">
                                        @if ($product->supplier)
                                            <a href="{{ route('suppliers.show', $product->supplier) }}" class="hover:text-brand-600 dark:hover:text-brand-400 dark:text-brand-400">
                                                {{ $product->supplier->name }}
                                            </a>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right font-semibold tabular-nums">
                                        @qty($product->quantity) <span class="text-xs font-normal text-ink-muted">{{ $product->unit }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-right tabular-nums text-ink-muted">@qty($product->reorder_level)</td>
                                    <td class="px-4 py-3 text-right font-semibold tabular-nums text-rose-600 dark:text-rose-400">
                                        {{ $shortfall > 0 ? '+'.number_format($shortfall) : '—' }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <x-badge :classes="$product->stock_status_classes">{{ $product->stock_status_label }}</x-badge>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <x-button
                                            :href="route('movements.create', ['product' => $product->id, 'type' => 'in'])"
                                            variant="secondary"
                                            size="sm"
                                        >
                                            {{ __('app.dashboard.restock') }}
                                        </x-button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($products->hasPages())
                    <div class="border-t border-line px-4 py-3">
                        {{ $products->links() }}
                    </div>
                @endif
            @endif
        </x-card>
    </div>
</x-app-layout>
