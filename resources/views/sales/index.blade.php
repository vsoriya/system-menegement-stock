<x-app-layout :title="__('app.sale.title')">
    <x-slot:actions>
        <x-button :href="route('pos.index')" size="sm">{{ __('app.pos.open_till') }}</x-button>
    </x-slot:actions>

    <div class="space-y-5">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-stat-card :label="__('app.pos.today_sales')" :count="$today['sales_count']" />
            <x-stat-card
                :label="__('app.pos.today_revenue')"
                :count="$today['revenue']"
                :prefix="config('app.currency_symbol')"
                :decimals="2"
                tone="success"
            />
            <x-stat-card
                :label="__('app.pos.today_profit')"
                :count="$today['profit']"
                :prefix="config('app.currency_symbol')"
                :decimals="2"
            />
            <x-stat-card
                :label="__('app.pos.filtered_revenue')"
                :count="$revenue"
                :prefix="config('app.currency_symbol')"
                :decimals="2"
                :hint="__('app.common.found', ['count' => number_format($salesCount)])"
            />
        </div>

        <x-card>
            <form method="GET" action="{{ route('sales.index') }}" class="flex flex-wrap items-end gap-3 p-4">
                <x-field
                    :label="__('app.common.search')"
                    name="search"
                    :value="$filters['search'] ?? null"
                    :placeholder="__('app.sale.search_placeholder')"
                    class="min-w-56 flex-1"
                />

                <x-field
                    :label="__('app.sale.status')"
                    name="status"
                    type="select"
                    :value="$filters['status'] ?? null"
                    :placeholder="__('app.sale.all_statuses')"
                    :options="$statuses"
                    class="min-w-40"
                />

                <x-field
                    :label="__('app.sale.from')"
                    name="from"
                    type="date"
                    :value="$filters['from'] ?? null"
                    class="min-w-40"
                />

                <x-field
                    :label="__('app.sale.to')"
                    name="to"
                    type="date"
                    :value="$filters['to'] ?? null"
                    class="min-w-40"
                />

                <x-button type="submit" size="sm">{{ __('app.common.search') }}</x-button>
                <x-button :href="route('sales.index')" variant="secondary" size="sm">{{ __('app.common.reset') }}</x-button>
            </form>
        </x-card>

        <x-card :description="__('app.common.found', ['count' => number_format($sales->total())])">
            @if ($sales->isEmpty())
                <x-empty-state
                    :title="collect($filters)->filter()->isNotEmpty() ? __('app.sale.none_match') : __('app.sale.none_yet')"
                    :description="collect($filters)->filter()->isNotEmpty() ? __('app.sale.none_match_sub') : __('app.sale.none_yet_sub')"
                >
                    <x-button :href="route('pos.index')" size="sm">{{ __('app.pos.open_till') }}</x-button>
                </x-empty-state>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-line text-sm">
                        <thead class="bg-surface-sunken text-left text-xs font-semibold uppercase tracking-wider text-ink-muted">
                            <tr>
                                <th scope="col" class="px-4 py-2.5">{{ __('app.sale.number') }}</th>
                                <th scope="col" class="px-4 py-2.5">{{ __('app.customer.one') }}</th>
                                <th scope="col" class="px-4 py-2.5">{{ __('app.sale.cashier') }}</th>
                                <th scope="col" class="px-4 py-2.5 text-right">{{ __('app.sale.item_count') }}</th>
                                <th scope="col" class="px-4 py-2.5">{{ __('app.sale.payment_method') }}</th>
                                <th scope="col" class="px-4 py-2.5 text-right">{{ __('app.sale.total') }}</th>
                                <th scope="col" class="px-4 py-2.5">{{ __('app.sale.status') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($sales as $index => $sale)
                                <tr class="animate-fade stagger row-hover" style="--d: {{ $index * 35 }}ms">
                                    <td class="px-4 py-3">
                                        <a href="{{ route('sales.show', $sale) }}" class="font-mono font-medium text-ink hover:text-brand-600 dark:hover:text-brand-400">
                                            {{ $sale->number }}
                                        </a>
                                        <p class="text-xs text-ink-muted">{{ $sale->sold_at->format('d/m/Y H:i') }}</p>
                                    </td>
                                    <td class="px-4 py-3 text-ink-muted">
                                        @if ($sale->customer)
                                            <a href="{{ route('customers.show', $sale->customer) }}" class="hover:text-brand-600 dark:hover:text-brand-400">
                                                {{ $sale->customer->name }}
                                            </a>
                                        @else
                                            {{ __('app.customer.walk_in') }}
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-ink-muted">{{ $sale->cashier?->name ?? '—' }}</td>
                                    <td class="px-4 py-3 text-right tabular-nums text-ink-muted">@qty($sale->units_sold ?? 0)</td>
                                    <td class="px-4 py-3 text-ink-muted">{{ $sale->payment_method->label() }}</td>
                                    <td class="px-4 py-3 text-right font-medium tabular-nums text-ink">@money($sale->total)</td>
                                    <td class="px-4 py-3">
                                        <x-badge :classes="$sale->status->badgeClasses()">{{ $sale->status->label() }}</x-badge>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($sales->hasPages())
                    <div class="border-t border-line px-4 py-3">
                        {{ $sales->links() }}
                    </div>
                @endif
            @endif
        </x-card>
    </div>
</x-app-layout>
