<x-app-layout :title="$sale->number">
    <x-slot:actions>
        <x-button :href="route('pos.index')" variant="secondary" size="sm">{{ __('app.pos.open_till') }}</x-button>

        {{-- Two separate printouts rather than printing this page: an A5 sheet
             to hand over or file, and an 80mm roll for a counter printer. --}}
        <x-button :href="route('sales.print', [$sale, 'receipt'])" variant="secondary" size="sm">
            {{ __('app.sale.print_receipt') }}
        </x-button>
        <x-button :href="route('sales.print', [$sale, 'a5'])" size="sm">
            {{ __('app.sale.print_invoice') }}
        </x-button>
    </x-slot:actions>

    <div class="mx-auto max-w-3xl space-y-5">
        @if ($sale->is_voided)
            <div class="animate-fade rounded-2xl border border-rose-300 bg-rose-50 px-4 py-3 text-sm text-rose-800 dark:border-rose-500/40 dark:bg-rose-500/15 dark:text-rose-200">
                <p class="font-semibold">{{ __('app.sale.voided_banner') }}</p>
                <p class="mt-0.5 text-xs">
                    {{ __('app.sale.voided_at', ['when' => $sale->voided_at?->format('d/m/Y H:i')]) }}
                    @if (filled($sale->note))
                        &middot; {{ $sale->note }}
                    @endif
                </p>
            </div>
        @endif

        <x-card>
            <div class="flex flex-wrap items-start justify-between gap-4 p-4 sm:p-5">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-ink-muted">{{ __('app.sale.invoice') }}</p>
                    <p class="font-mono text-xl font-semibold tracking-tight text-ink">{{ $sale->number }}</p>
                    <p class="mt-1 text-sm text-ink-muted">{{ $sale->sold_at->format('d/m/Y H:i') }}</p>
                </div>

                <div class="text-right">
                    <x-badge :classes="$sale->status->badgeClasses()">{{ $sale->status->label() }}</x-badge>
                    <p class="mt-2 text-xs text-ink-muted">{{ __('app.sale.cashier') }}</p>
                    <p class="text-sm font-medium text-ink">{{ $sale->cashier?->name ?? '—' }}</p>
                </div>
            </div>

            <dl class="grid grid-cols-1 gap-x-6 gap-y-2 border-t border-line px-4 py-3 text-sm sm:grid-cols-2 sm:px-5">
                <div class="flex justify-between gap-4">
                    <dt class="text-ink-muted">{{ __('app.customer.one') }}</dt>
                    <dd class="text-right text-ink">
                        @if ($sale->customer)
                            <a href="{{ route('customers.show', $sale->customer) }}" class="text-brand-600 dark:text-brand-400 hover:text-brand-700 dark:hover:text-brand-300">
                                {{ $sale->customer->name }}
                            </a>
                        @else
                            {{ __('app.customer.walk_in') }}
                        @endif
                    </dd>
                </div>

                <div class="flex justify-between gap-4">
                    <dt class="text-ink-muted">{{ __('app.sale.payment_method') }}</dt>
                    <dd class="text-right text-ink">{{ $sale->payment_method->label() }}</dd>
                </div>

                @if (filled($sale->customer?->phone))
                    <div class="flex justify-between gap-4">
                        <dt class="text-ink-muted">{{ __('app.customer.phone') }}</dt>
                        <dd class="text-right text-ink">{{ $sale->customer->phone }}</dd>
                    </div>
                @endif

                <div class="flex justify-between gap-4">
                    <dt class="text-ink-muted">{{ __('app.sale.item_count') }}</dt>
                    <dd class="text-right tabular-nums text-ink">@qty($sale->item_count)</dd>
                </div>
            </dl>
        </x-card>

        <x-card :title="__('app.sale.items')">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-line text-sm">
                    <thead class="bg-surface-sunken text-left text-xs font-semibold uppercase tracking-wider text-ink-muted">
                        <tr>
                            <th scope="col" class="px-4 py-2.5">{{ __('app.product.one') }}</th>
                            <th scope="col" class="px-4 py-2.5 text-right">{{ __('app.sale.unit_price') }}</th>
                            <th scope="col" class="px-4 py-2.5 text-right">{{ __('app.movement.quantity') }}</th>
                            <th scope="col" class="px-4 py-2.5 text-right">{{ __('app.sale.line_total') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($sale->lines as $index => $line)
                            <tr class="animate-fade stagger" style="--d: {{ $index * 35 }}ms">
                                <td class="px-4 py-3">
                                    @if ($line->product && ! $line->product->trashed())
                                        <a href="{{ route('products.show', $line->product) }}" class="font-medium text-ink hover:text-brand-600 dark:hover:text-brand-400">
                                            {{ $line->product->name }}
                                        </a>
                                    @else
                                        {{-- Kept readable even if the product was removed afterwards. --}}
                                        <span class="font-medium text-ink">{{ $line->product?->name ?? '—' }}</span>
                                    @endif
                                    <p class="font-mono text-xs text-ink-muted">{{ $line->product?->sku }}</p>
                                </td>
                                <td class="px-4 py-3 text-right tabular-nums text-ink-muted">@money($line->unit_price)</td>
                                <td class="px-4 py-3 text-right tabular-nums text-ink">
                                    @qty($line->quantity) {{ $line->product?->unit }}
                                </td>
                                <td class="px-4 py-3 text-right font-medium tabular-nums text-ink">@money($line->line_total)</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <dl class="space-y-1.5 border-t border-line bg-surface-sunken px-4 py-3 text-sm sm:px-5">
                <div class="flex justify-between gap-4">
                    <dt class="text-ink-muted">{{ __('app.sale.subtotal') }}</dt>
                    <dd class="font-medium tabular-nums text-ink">@money($sale->subtotal)</dd>
                </div>

                @if ((float) $sale->discount > 0)
                    <div class="flex justify-between gap-4">
                        <dt class="text-ink-muted">{{ __('app.sale.discount') }}</dt>
                        <dd class="font-medium tabular-nums text-rose-600 dark:text-rose-400">-@money($sale->discount)</dd>
                    </div>
                @endif

                <div class="flex justify-between gap-4 border-t border-line pt-1.5">
                    <dt class="font-semibold text-ink">{{ __('app.sale.total') }}</dt>
                    <dd class="text-lg font-semibold tabular-nums text-ink">@money($sale->total)</dd>
                </div>

                <div class="flex justify-between gap-4">
                    <dt class="text-ink-muted">{{ __('app.sale.paid') }}</dt>
                    <dd class="tabular-nums text-ink">@money($sale->paid)</dd>
                </div>

                @if ((float) $sale->change_due > 0)
                    <div class="flex justify-between gap-4">
                        <dt class="text-ink-muted">{{ __('app.sale.change_due') }}</dt>
                        <dd class="font-medium tabular-nums text-emerald-600 dark:text-emerald-400">@money($sale->change_due)</dd>
                    </div>
                @endif

                @if (auth()->user()->canManageCatalog())
                    <div class="flex justify-between gap-4 border-t border-line pt-1.5 print:hidden">
                        <dt class="text-ink-muted">{{ __('app.sale.profit') }}</dt>
                        <dd class="font-medium tabular-nums text-ink">@money($sale->profit)</dd>
                    </div>
                @endif
            </dl>
        </x-card>

        @if (! $sale->is_voided && filled($sale->note))
            <x-card :title="__('app.sale.note')">
                <p class="px-4 py-3 text-sm whitespace-pre-line text-ink sm:px-5">{{ $sale->note }}</p>
            </x-card>
        @endif

        @if (auth()->user()->canManageCatalog() && $sale->status->isVoidable())
            <x-card :title="__('app.sale.void_title')" :description="__('app.sale.void_sub')" class="print:hidden">
                <form method="POST" action="{{ route('sales.void', $sale) }}" class="space-y-3 p-4 sm:p-5">
                    @csrf

                    <x-field
                        :label="__('app.sale.void_reason')"
                        name="reason"
                        :placeholder="__('app.sale.void_reason_placeholder')"
                    />

                    <div class="flex justify-end">
                        <x-button
                            type="submit"
                            variant="danger"
                            onclick="return confirm(@js(__('app.sale.void_confirm', ['number' => $sale->number])));"
                        >{{ __('app.sale.void_action') }}</x-button>
                    </div>
                </form>
            </x-card>
        @endif
    </div>
</x-app-layout>
