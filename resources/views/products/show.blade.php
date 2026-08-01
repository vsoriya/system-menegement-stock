<x-app-layout :title="$product->name">
    <x-slot:header>
        <span class="flex items-center gap-2">
            {{ $product->name }}
            <x-badge :classes="$product->stock_status_classes">{{ $product->stock_status_label }}</x-badge>
        </span>
    </x-slot:header>

    <x-slot:actions>
        <x-button :href="route('movements.create', ['product' => $product->id, 'type' => 'in'])" variant="secondary" size="sm">
            {{ __('app.movement.stock_in') }}
        </x-button>
        <x-button :href="route('movements.create', ['product' => $product->id, 'type' => 'out'])" variant="secondary" size="sm">
            {{ __('app.movement.stock_out') }}
        </x-button>
        @if (auth()->user()->canManageCatalog())
            <x-button :href="route('products.edit', $product)" size="sm">{{ __('app.common.edit') }}</x-button>
        @endif
    </x-slot:actions>

    <div class="space-y-6">
        <!-- Key figures -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-stat-card
                :label="__('app.product.on_hand')"
                value="{{ number_format($product->quantity).' '.$product->unit }}"
                :hint="__('app.product.reorder_at', ['count' => number_format($product->reorder_level)])"
                :tone="$product->stock_status === 'out_of_stock' ? 'danger' : ($product->stock_status === 'low_stock' ? 'warning' : 'success')"
            />
            <x-stat-card
                :label="__('app.product.cost_price')"
                value="{{ config('app.currency_symbol').number_format((float) $product->cost_price, 2) }}"
                hint="per {{ $product->unit }}"
            />
            <x-stat-card
                :label="__('app.product.sale_price')"
                value="{{ config('app.currency_symbol').number_format((float) $product->sale_price, 2) }}"
                hint="per {{ $product->unit }}"
            />
            <x-stat-card
                :label="__('app.product.stock_value')"
                value="{{ config('app.currency_symbol').number_format($product->stock_value, 2) }}"
                :hint="__('app.dashboard.retail', ['amount' => config('app.currency_symbol').number_format($product->retail_value, 2)])"
            />
        </div>

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
            <!-- Details -->
            <x-card :title="__('app.common.details')" class="xl:col-span-1">
                <dl class="divide-y divide-line text-sm">
                    <div class="flex justify-between gap-4 px-4 py-3">
                        <dt class="text-ink-muted">{{ __('app.product.sku') }}</dt>
                        <dd class="font-mono text-ink">{{ $product->sku }}</dd>
                    </div>
                    <div class="flex justify-between gap-4 px-4 py-3">
                        <dt class="text-ink-muted">{{ __('app.product.category') }}</dt>
                        <dd class="text-right text-ink">{{ $product->category?->name ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-4 px-4 py-3">
                        <dt class="text-ink-muted">{{ __('app.product.supplier') }}</dt>
                        <dd class="text-right text-ink">
                            @if ($product->supplier)
                                <a href="{{ route('suppliers.show', $product->supplier) }}" class="text-brand-600 dark:text-brand-400 hover:text-brand-700 dark:hover:text-brand-300 dark:text-brand-300">
                                    {{ $product->supplier->name }}
                                </a>
                            @else
                                —
                            @endif
                        </dd>
                    </div>
                    <div class="flex justify-between gap-4 px-4 py-3">
                        <dt class="text-ink-muted">{{ __('app.product.unit') }}</dt>
                        <dd class="text-ink">{{ $product->unit }}</dd>
                    </div>
                    <div class="flex justify-between gap-4 px-4 py-3">
                        <dt class="text-ink-muted">{{ __('app.product.margin') }}</dt>
                        <dd class="tabular-nums text-ink">
                            @money((float) $product->sale_price - (float) $product->cost_price)
                        </dd>
                    </div>
                    <div class="flex justify-between gap-4 px-4 py-3">
                        <dt class="text-ink-muted">{{ __('app.common.status') }}</dt>
                        <dd>
                            @if ($product->is_active)
                                <x-badge classes="bg-emerald-50 dark:bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 ring-emerald-600/20 dark:ring-emerald-400/30">{{ __('app.common.active') }}</x-badge>
                            @else
                                <x-badge classes="bg-surface-sunken text-ink-muted ring-line">{{ __('app.common.inactive') }}</x-badge>
                            @endif
                        </dd>
                    </div>
                    <div class="flex justify-between gap-4 px-4 py-3">
                        <dt class="text-ink-muted">{{ __('app.common.created') }}</dt>
                        <dd class="text-ink">{{ $product->created_at->format('d M Y') }}</dd>
                    </div>
                </dl>

                @if (filled($product->description))
                    <div class="border-t border-line px-4 py-3">
                        <h3 class="text-xs font-semibold uppercase tracking-wider text-ink-muted">{{ __('app.common.description') }}</h3>
                        <p class="mt-1.5 text-sm whitespace-pre-line text-ink">{{ $product->description }}</p>
                    </div>
                @endif
            </x-card>

            <!-- Movement history -->
            <x-card :title="__('app.product.history')" :description="__('app.product.history_sub')" class="xl:col-span-2">
                <x-slot:cardActions>
                    <x-button :href="route('movements.create', ['product' => $product->id])" size="sm">{{ __('app.movement.record') }}</x-button>
                </x-slot:cardActions>

                @if ($movements->isEmpty())
                    <x-empty-state
                        :title="__('app.product.no_history')"
                        :description="__('app.product.no_history_sub')"
                    >
                        <x-button :href="route('movements.create', ['product' => $product->id, 'type' => 'in'])" size="sm">
                            {{ __('app.movement.stock_in') }}
                        </x-button>
                    </x-empty-state>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-line text-sm">
                            <thead class="bg-surface-sunken text-left text-xs font-semibold uppercase tracking-wider text-ink-muted">
                                <tr>
                                    <th scope="col" class="px-4 py-2.5">{{ __('app.common.when') }}</th>
                                    <th scope="col" class="px-4 py-2.5">{{ __('app.movement.type') }}</th>
                                    <th scope="col" class="px-4 py-2.5 text-right">{{ __('app.movement.change') }}</th>
                                    <th scope="col" class="px-4 py-2.5 text-right">{{ __('app.movement.balance') }}</th>
                                    <th scope="col" class="px-4 py-2.5">{{ __('app.movement.reference') }}</th>
                                    <th scope="col" class="px-4 py-2.5">{{ __('app.common.by') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-line">
                                @foreach ($movements as $index => $movement)
                                    <tr class="animate-fade stagger row-hover" style="--d: {{ $index * 35 }}ms">
                                        <td class="px-4 py-3 whitespace-nowrap text-ink-muted">
                                            <time datetime="{{ $movement->created_at->toIso8601String() }}">
                                                {{ $movement->created_at->format('d M Y H:i') }}
                                            </time>
                                        </td>
                                        <td class="px-4 py-3">
                                            <x-badge :classes="$movement->type->badgeClasses()">{{ $movement->type->label() }}</x-badge>
                                        </td>
                                        <td class="px-4 py-3 text-right font-semibold tabular-nums {{ $movement->quantity_change >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                                            {{ $movement->quantity_change >= 0 ? '+' : '' }}@qty($movement->quantity_change)
                                        </td>
                                        <td class="px-4 py-3 text-right tabular-nums text-ink-muted">
                                            @qty($movement->quantity_before) → <span class="font-medium text-ink">@qty($movement->quantity_after)</span>
                                        </td>
                                        <td class="px-4 py-3 text-ink-muted">
                                            {{ $movement->reference ?? '—' }}
                                            @if (filled($movement->note))
                                                <p class="text-xs text-ink-subtle">{{ $movement->note }}</p>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-ink-muted">{{ $movement->user?->name ?? __('app.movement.system') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @if ($movements->hasPages())
                        <div class="border-t border-line px-4 py-3">
                            {{ $movements->links() }}
                        </div>
                    @endif
                @endif
            </x-card>
        </div>
    </div>
</x-app-layout>
