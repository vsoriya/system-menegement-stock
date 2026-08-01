<x-app-layout :title="__('app.report.valuation')">
    <div class="space-y-5">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-stat-card
                :label="__('app.dashboard.products')"
                :value="number_format($summary['products_count'])"
            />
            <x-stat-card
                :label="__('app.category.units')"
                :value="number_format($summary['units_on_hand'])"
            />
            <x-stat-card
                :label="__('app.report.value_cost')"
                value="{{ config('app.currency_symbol').number_format($summary['stock_value'], 2) }}"
                :hint="__('app.report.cost_meaning')"
            />
            <x-stat-card
                :label="__('app.report.value_retail')"
                value="{{ config('app.currency_symbol').number_format($summary['retail_value'], 2) }}"
                :hint="__('app.report.potential_margin', ['amount' => config('app.currency_symbol').number_format($summary['retail_value'] - $summary['stock_value'], 2)])"
                tone="success"
            />
        </div>

        <x-card :title="__('app.report.by_category')" :description="__('app.report.by_category_sub')">
            @if ($byCategory->isEmpty())
                <x-empty-state :title="__('app.report.no_products')" :description="__('app.report.no_products_sub')" />
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-line text-sm">
                        <thead class="bg-surface-sunken text-left text-xs font-semibold uppercase tracking-wider text-ink-muted">
                            <tr>
                                <th scope="col" class="px-4 py-2.5">{{ __('app.product.category') }}</th>
                                <th scope="col" class="px-4 py-2.5 text-right">{{ __('app.category.products_count') }}</th>
                                <th scope="col" class="px-4 py-2.5 text-right">{{ __('app.report.units') }}</th>
                                <th scope="col" class="px-4 py-2.5 text-right">{{ __('app.report.value_cost') }}</th>
                                <th scope="col" class="px-4 py-2.5 text-right">{{ __('app.report.value_retail') }}</th>
                                <th scope="col" class="px-4 py-2.5 text-right">{{ __('app.report.share') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($byCategory as $index => $row)
                                @php
                                    $share = $summary['stock_value'] > 0
                                        ? ((float) $row->stock_value / $summary['stock_value']) * 100
                                        : 0;
                                @endphp
                                <tr class="animate-fade stagger row-hover" style="--d: {{ $index * 45 }}ms">
                                    <td class="px-4 py-3 font-medium text-ink">
                                        {{ $row->category_name ?? __('app.product.uncategorised') }}
                                    </td>
                                    <td class="px-4 py-3 text-right tabular-nums text-ink-muted">@qty($row->products_count)</td>
                                    <td class="px-4 py-3 text-right tabular-nums text-ink-muted">@qty($row->units_on_hand)</td>
                                    <td class="px-4 py-3 text-right font-semibold tabular-nums">@money($row->stock_value)</td>
                                    <td class="px-4 py-3 text-right tabular-nums text-ink-muted">@money($row->retail_value)</td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center justify-end gap-2">
                                            <span class="tabular-nums text-xs text-ink-muted">{{ number_format($share, 1) }}%</span>
                                            <span
                                                class="h-1.5 w-20 overflow-hidden rounded-full bg-line"
                                                role="img"
                                                aria-label="{{ __('app.report.share_label', ['percent' => number_format($share, 1)]) }}"
                                            >
                                                <span
                                                    class="animate-grow-line stagger block h-full origin-left rounded-full bg-linear-to-r from-brand-400 to-brand-600"
                                                    style="width: {{ min(100, round($share, 1)) }}%; --d: {{ 200 + $index * 60 }}ms"
                                                ></span>
                                            </span>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="border-t-2 border-line bg-surface-sunken font-semibold">
                            <tr>
                                <td class="px-4 py-3">{{ __('app.common.total') }}</td>
                                <td class="px-4 py-3 text-right tabular-nums">@qty($summary['products_count'])</td>
                                <td class="px-4 py-3 text-right tabular-nums">@qty($summary['units_on_hand'])</td>
                                <td class="px-4 py-3 text-right tabular-nums">@money($summary['stock_value'])</td>
                                <td class="px-4 py-3 text-right tabular-nums">@money($summary['retail_value'])</td>
                                <td class="px-4 py-3 text-right tabular-nums">100%</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @endif
        </x-card>
    </div>
</x-app-layout>
