<x-app-layout :title="$customer->name">
    <x-slot:actions>
        <x-button :href="route('customers.edit', $customer)" size="sm">{{ __('app.common.edit') }}</x-button>
    </x-slot:actions>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <x-card :title="__('app.customer.details')" class="xl:col-span-1">
            <dl class="divide-y divide-line text-sm">
                <div class="flex justify-between gap-4 px-4 py-3">
                    <dt class="text-ink-muted">{{ __('app.customer.phone') }}</dt>
                    <dd class="text-right text-ink">{{ $customer->phone ?? '—' }}</dd>
                </div>
                <div class="flex justify-between gap-4 px-4 py-3">
                    <dt class="text-ink-muted">{{ __('app.customer.email') }}</dt>
                    <dd class="text-right">
                        @if (filled($customer->email))
                            <a href="mailto:{{ $customer->email }}" class="text-brand-600 dark:text-brand-400 hover:text-brand-700 dark:hover:text-brand-300">{{ $customer->email }}</a>
                        @else
                            <span class="text-ink">—</span>
                        @endif
                    </dd>
                </div>
                <div class="flex justify-between gap-4 px-4 py-3">
                    <dt class="text-ink-muted">{{ __('app.customer.address') }}</dt>
                    <dd class="text-right text-ink">{{ $customer->address ?? '—' }}</dd>
                </div>
                <div class="flex justify-between gap-4 px-4 py-3">
                    <dt class="text-ink-muted">{{ __('app.customer.total_spent') }}</dt>
                    <dd class="text-right font-semibold text-ink">@money($customer->total_spent)</dd>
                </div>
                <div class="flex justify-between gap-4 px-4 py-3">
                    <dt class="text-ink-muted">{{ __('app.common.status') }}</dt>
                    <dd>
                        @if ($customer->is_active)
                            <x-badge classes="bg-emerald-50 dark:bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 ring-emerald-600/20 dark:ring-emerald-400/30">{{ __('app.common.active') }}</x-badge>
                        @else
                            <x-badge classes="bg-surface-sunken text-ink-muted ring-line">{{ __('app.common.inactive') }}</x-badge>
                        @endif
                    </dd>
                </div>
            </dl>

            @if (filled($customer->notes))
                <div class="border-t border-line px-4 py-3">
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-ink-muted">{{ __('app.common.notes') }}</h3>
                    <p class="mt-1.5 text-sm whitespace-pre-line text-ink">{{ $customer->notes }}</p>
                </div>
            @endif
        </x-card>

        <x-card
            :title="__('app.customer.purchases')"
            :description="__('app.common.found', ['count' => number_format($sales->total())])"
            class="xl:col-span-2"
        >
            @if ($sales->isEmpty())
                <x-empty-state
                    :title="__('app.customer.no_purchases')"
                    :description="__('app.customer.no_purchases_sub')"
                />
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-line text-sm">
                        <thead class="bg-surface-sunken text-left text-xs font-semibold uppercase tracking-wider text-ink-muted">
                            <tr>
                                <th scope="col" class="px-4 py-2.5">{{ __('app.sale.number') }}</th>
                                <th scope="col" class="px-4 py-2.5">{{ __('app.sale.sold_at') }}</th>
                                <th scope="col" class="px-4 py-2.5">{{ __('app.sale.cashier') }}</th>
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
                                    </td>
                                    <td class="px-4 py-3 text-ink-muted">{{ $sale->sold_at->format('d/m/Y H:i') }}</td>
                                    <td class="px-4 py-3 text-ink-muted">{{ $sale->cashier?->name ?? '—' }}</td>
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
