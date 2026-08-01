<x-app-layout :title="$supplier->name">
    <x-slot:actions>
        @if (auth()->user()->canManageCatalog())
            <x-button :href="route('suppliers.edit', $supplier)" size="sm">{{ __('app.common.edit') }}</x-button>
        @endif
    </x-slot:actions>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <x-card :title="__('app.supplier.details')" class="xl:col-span-1">
            <dl class="divide-y divide-line text-sm">
                <div class="flex justify-between gap-4 px-4 py-3">
                    <dt class="text-ink-muted">{{ __('app.supplier.contact') }}</dt>
                    <dd class="text-right text-ink">{{ $supplier->contact_person ?? '—' }}</dd>
                </div>
                <div class="flex justify-between gap-4 px-4 py-3">
                    <dt class="text-ink-muted">{{ __('app.supplier.phone') }}</dt>
                    <dd class="text-right text-ink">{{ $supplier->phone ?? '—' }}</dd>
                </div>
                <div class="flex justify-between gap-4 px-4 py-3">
                    <dt class="text-ink-muted">{{ __('app.supplier.email') }}</dt>
                    <dd class="text-right">
                        @if (filled($supplier->email))
                            <a href="mailto:{{ $supplier->email }}" class="text-brand-600 dark:text-brand-400 hover:text-brand-700 dark:hover:text-brand-300 dark:text-brand-300">{{ $supplier->email }}</a>
                        @else
                            <span class="text-ink">—</span>
                        @endif
                    </dd>
                </div>
                <div class="flex justify-between gap-4 px-4 py-3">
                    <dt class="text-ink-muted">{{ __('app.supplier.address') }}</dt>
                    <dd class="text-right text-ink">{{ $supplier->address ?? '—' }}</dd>
                </div>
                <div class="flex justify-between gap-4 px-4 py-3">
                    <dt class="text-ink-muted">{{ __('app.common.status') }}</dt>
                    <dd>
                        @if ($supplier->is_active)
                            <x-badge classes="bg-emerald-50 dark:bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 ring-emerald-600/20 dark:ring-emerald-400/30">{{ __('app.common.active') }}</x-badge>
                        @else
                            <x-badge classes="bg-surface-sunken text-ink-muted ring-line">{{ __('app.common.inactive') }}</x-badge>
                        @endif
                    </dd>
                </div>
            </dl>

            @if (filled($supplier->notes))
                <div class="border-t border-line px-4 py-3">
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-ink-muted">{{ __('app.common.notes') }}</h3>
                    <p class="mt-1.5 text-sm whitespace-pre-line text-ink">{{ $supplier->notes }}</p>
                </div>
            @endif
        </x-card>

        <x-card :title="__('app.supplier.products_supplied')" :description="__('app.common.found', ['count' => number_format($products->total())])" class="xl:col-span-2">
            @if ($products->isEmpty())
                <x-empty-state
                    :title="__('app.supplier.no_products')"
                    :description="__('app.supplier.no_products_sub')"
                />
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-line text-sm">
                        <thead class="bg-surface-sunken text-left text-xs font-semibold uppercase tracking-wider text-ink-muted">
                            <tr>
                                <th scope="col" class="px-4 py-2.5">{{ __('app.product.one') }}</th>
                                <th scope="col" class="px-4 py-2.5">{{ __('app.product.category') }}</th>
                                <th scope="col" class="px-4 py-2.5 text-right">{{ __('app.product.on_hand') }}</th>
                                <th scope="col" class="px-4 py-2.5 text-right">{{ __('app.product.cost') }}</th>
                                <th scope="col" class="px-4 py-2.5">{{ __('app.common.status') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($products as $index => $product)
                                <tr class="animate-fade stagger row-hover" style="--d: {{ $index * 35 }}ms">
                                    <td class="px-4 py-3">
                                        <a href="{{ route('products.show', $product) }}" class="font-medium text-ink hover:text-brand-600 dark:hover:text-brand-400 dark:text-brand-400">
                                            {{ $product->name }}
                                        </a>
                                        <p class="font-mono text-xs text-ink-muted">{{ $product->sku }}</p>
                                    </td>
                                    <td class="px-4 py-3 text-ink-muted">{{ $product->category?->name ?? '—' }}</td>
                                    <td class="px-4 py-3 text-right tabular-nums">@qty($product->quantity) {{ $product->unit }}</td>
                                    <td class="px-4 py-3 text-right tabular-nums">@money($product->cost_price)</td>
                                    <td class="px-4 py-3">
                                        <x-badge :classes="$product->stock_status_classes">{{ $product->stock_status_label }}</x-badge>
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
