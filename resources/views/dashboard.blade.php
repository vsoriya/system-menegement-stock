<x-app-layout :title="__('app.dashboard.title')">
    <x-slot:actions>
        <x-button :href="route('movements.create')" size="sm">
            <svg class="size-4 transition-transform duration-200 group-hover:rotate-90" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path d="M10 5a1 1 0 0 1 1 1v3h3a1 1 0 1 1 0 2h-3v3a1 1 0 1 1-2 0v-3H6a1 1 0 1 1 0-2h3V6a1 1 0 0 1 1-1Z" />
            </svg>
            {{ __('app.movement.record') }}
        </x-button>
    </x-slot:actions>

    <div class="space-y-6">
        <!-- Headline numbers -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-stat-card
                :label="__('app.dashboard.products')"
                :count="$summary['products_count']"
                :hint="__('app.dashboard.units_on_hand', ['count' => number_format($summary['units_on_hand'])])"
                :href="route('products.index')"
                style="--d: 0ms"
            >
                <x-slot:icon>
                    <path d="M3 4a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v2a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V4Zm0 5a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v7a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V9Zm4 2a1 1 0 0 0 0 2h6a1 1 0 0 0 0-2H7Z" />
                </x-slot:icon>
            </x-stat-card>

            <x-stat-card
                :label="__('app.dashboard.stock_value')"
                :count="$summary['stock_value']"
                :prefix="config('app.currency_symbol')"
                :decimals="2"
                :hint="__('app.dashboard.retail', ['amount' => config('app.currency_symbol').number_format($summary['retail_value'], 2)])"
                :href="route('reports.valuation')"
                style="--d: 80ms"
            >
                <x-slot:icon>
                    <path d="M10 2a1 1 0 0 1 1 1v1.055a3.5 3.5 0 0 1 2.28 1.36 1 1 0 0 1-1.6 1.2A1.5 1.5 0 0 0 10.5 6h-1a1.5 1.5 0 0 0 0 3h1a3.5 3.5 0 0 1 .5 6.945V17a1 1 0 1 1-2 0v-1.055a3.5 3.5 0 0 1-2.28-1.36 1 1 0 0 1 1.6-1.2A1.5 1.5 0 0 0 9.5 14h1a1.5 1.5 0 0 0 0-3h-1A3.5 3.5 0 0 1 9 4.055V3a1 1 0 0 1 1-1Z" />
                </x-slot:icon>
            </x-stat-card>

            <x-stat-card
                :label="__('app.dashboard.low_stock')"
                :count="$summary['low_stock_count']"
                :hint="__('app.dashboard.at_or_below')"
                tone="warning"
                :href="route('reports.low-stock')"
                style="--d: 160ms"
            >
                <x-slot:icon>
                    <path d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.63-1.516 2.63H3.72c-1.347 0-2.19-1.463-1.515-2.63L8.485 2.495ZM10 5a.75.75 0 0 1 .75.75v3.5a.75.75 0 0 1-1.5 0v-3.5A.75.75 0 0 1 10 5Zm0 9a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" />
                </x-slot:icon>
            </x-stat-card>

            <x-stat-card
                :label="__('app.dashboard.out_of_stock')"
                :count="$summary['out_of_stock_count']"
                :hint="__('app.dashboard.needs_restocking')"
                tone="danger"
                :href="route('products.index', ['status' => 'out_of_stock'])"
                style="--d: 240ms"
            >
                <x-slot:icon>
                    <path fill-rule="evenodd" d="M18 10a8 8 0 1 1-16 0 8 8 0 0 1 16 0ZM6.28 5.22a.75.75 0 0 0-1.06 1.06l7.5 7.5a.75.75 0 1 0 1.06-1.06l-7.5-7.5Z" clip-rule="evenodd" />
                </x-slot:icon>
            </x-stat-card>
        </div>

        <!-- Activity chart -->
        <x-card
            :title="__('app.dashboard.activity')"
            :description="__('app.dashboard.activity_sub')"
            style="--d: 300ms"
        >
            <x-slot:cardActions>
                <div class="flex items-center gap-3 text-xs">
                    <span class="rounded-lg bg-emerald-50 dark:bg-emerald-500/15 px-2 py-1 font-semibold text-emerald-700 dark:text-emerald-300 tabular-nums">
                        +@qty($movementTotals['in']) {{ __('app.dashboard.received') }}
                    </span>
                    <span class="rounded-lg bg-rose-50 dark:bg-rose-500/15 px-2 py-1 font-semibold text-rose-700 dark:text-rose-300 tabular-nums">
                        -@qty($movementTotals['out']) {{ __('app.dashboard.issued') }}
                    </span>
                    <span class="hidden rounded-lg bg-amber-50 dark:bg-amber-500/15 px-2 py-1 font-semibold text-amber-700 dark:text-amber-300 tabular-nums sm:inline">
                        @qty($movementTotals['adjustments']) {{ __('app.dashboard.adjustments') }}
                    </span>
                    <span class="hidden text-ink-subtle sm:inline">{{ __('app.dashboard.last_30') }}</span>
                </div>
            </x-slot:cardActions>

            <x-activity-chart :data="$dailyActivity" />
        </x-card>

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
            <!-- Reorder list -->
            <x-card :title="__('app.dashboard.needs_reorder')" :description="__('app.dashboard.lowest_first')" style="--d: 360ms">
                <x-slot:cardActions>
                    <x-button :href="route('reports.low-stock')" variant="ghost" size="sm">{{ __('app.dashboard.view_report') }}</x-button>
                </x-slot:cardActions>

                @if ($lowStockProducts->isEmpty())
                    <x-empty-state
                        :title="__('app.dashboard.all_stocked')"
                        :description="__('app.dashboard.all_stocked_sub')"
                    />
                @else
                    <ul class="divide-y divide-line">
                        @foreach ($lowStockProducts as $index => $product)
                            <li
                                class="animate-slide-left stagger row-hover flex items-center gap-3 px-4 py-3"
                                style="--d: {{ 380 + $index * 50 }}ms"
                            >
                                <div class="min-w-0 flex-1">
                                    <a href="{{ route('products.show', $product) }}" class="truncate text-sm font-medium text-ink transition hover:text-brand-600 dark:hover:text-brand-400 dark:text-brand-400">
                                        {{ $product->name }}
                                    </a>
                                    <p class="truncate text-xs text-ink-muted">
                                        {{ $product->sku }}
                                        @if ($product->category)
                                            · {{ $product->category->name }}
                                        @endif
                                    </p>
                                </div>
                                <div class="shrink-0 text-right">
                                    <p class="text-sm font-semibold tabular-nums text-ink">
                                        @qty($product->quantity) <span class="text-xs font-normal text-ink-muted">/ @qty($product->reorder_level)</span>
                                    </p>
                                    <x-badge
                                        :classes="$product->stock_status_classes"
                                        :dot="$product->stock_status === 'out_of_stock' ? 'pulse' : true"
                                    >{{ $product->stock_status_label }}</x-badge>
                                </div>
                                <x-button
                                    :href="route('movements.create', ['product' => $product->id, 'type' => 'in'])"
                                    variant="secondary"
                                    size="sm"
                                    class="shrink-0"
                                >
                                    {{ __('app.dashboard.restock') }}
                                </x-button>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>

            <!-- Recent movements -->
            <x-card :title="__('app.dashboard.recent')" :description="__('app.dashboard.recent_sub')" style="--d: 420ms">
                <x-slot:cardActions>
                    <x-button :href="route('movements.index')" variant="ghost" size="sm">{{ __('app.common.view_all') }}</x-button>
                </x-slot:cardActions>

                @if ($recentMovements->isEmpty())
                    <x-empty-state
                        :title="__('app.dashboard.no_movements')"
                        :description="__('app.dashboard.no_movements_sub')"
                    >
                        <x-button :href="route('movements.create')" size="sm">{{ __('app.movement.record') }}</x-button>
                    </x-empty-state>
                @else
                    <ul class="divide-y divide-line">
                        @foreach ($recentMovements as $index => $movement)
                            <li
                                class="animate-slide-left stagger row-hover flex items-center gap-3 px-4 py-3"
                                style="--d: {{ 440 + $index * 45 }}ms"
                            >
                                <x-badge :classes="$movement->type->badgeClasses()" class="shrink-0">
                                    {{ $movement->type->label() }}
                                </x-badge>

                                <div class="min-w-0 flex-1">
                                    <a href="{{ route('products.show', $movement->product) }}" class="block truncate text-sm font-medium text-ink transition hover:text-brand-600 dark:hover:text-brand-400 dark:text-brand-400">
                                        {{ $movement->product->name }}
                                    </a>
                                    <p class="truncate text-xs text-ink-muted">
                                        {{ $movement->created_at->diffForHumans() }}
                                        @if ($movement->user)
                                            · {{ $movement->user->name }}
                                        @endif
                                    </p>
                                </div>

                                <p class="shrink-0 text-sm font-semibold tabular-nums {{ $movement->quantity_change >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                                    {{ $movement->quantity_change >= 0 ? '+' : '' }}@qty($movement->quantity_change)
                                </p>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>
        </div>

        <!-- Highest value stock -->
        <x-card :title="__('app.dashboard.top_value')" :description="__('app.dashboard.top_value_sub')" style="--d: 480ms">
            @if ($topValueProducts->isEmpty())
                <x-empty-state :title="__('app.product.none_yet')" :description="__('app.product.none_yet_sub')">
                    @if (auth()->user()->canManageCatalog())
                        <x-button :href="route('products.create')" size="sm">{{ __('app.product.add') }}</x-button>
                    @endif
                </x-empty-state>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-line text-sm">
                        <thead class="bg-surface-sunken/80 text-left text-[0.6875rem] font-semibold uppercase tracking-wider text-ink-muted">
                            <tr>
                                <th scope="col" class="px-4 py-2.5">{{ __('app.product.one') }}</th>
                                <th scope="col" class="px-4 py-2.5">{{ __('app.product.sku') }}</th>
                                <th scope="col" class="px-4 py-2.5 text-right">{{ __('app.product.on_hand') }}</th>
                                <th scope="col" class="px-4 py-2.5 text-right">{{ __('app.product.cost_price') }}</th>
                                <th scope="col" class="px-4 py-2.5 text-right">{{ __('app.product.stock_value') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($topValueProducts as $index => $product)
                                <tr class="animate-fade stagger row-hover" style="--d: {{ 500 + $index * 60 }}ms">
                                    <td class="px-4 py-3">
                                        <a href="{{ route('products.show', $product) }}" class="font-medium text-ink transition hover:text-brand-600 dark:hover:text-brand-400 dark:text-brand-400">
                                            {{ $product->name }}
                                        </a>
                                    </td>
                                    <td class="px-4 py-3 font-mono text-xs text-ink-muted">{{ $product->sku }}</td>
                                    <td class="px-4 py-3 text-right tabular-nums">@qty($product->quantity) {{ $product->unit }}</td>
                                    <td class="px-4 py-3 text-right tabular-nums">@money($product->cost_price)</td>
                                    <td class="px-4 py-3 text-right font-semibold tabular-nums">@money($product->stock_value)</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-card>
    </div>
</x-app-layout>
