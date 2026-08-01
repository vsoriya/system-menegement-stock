@php
    $canManage = auth()->user()->canManageCatalog();
    $canDelete = auth()->user()->canDelete();
    $outstanding = $order->lines->sum(fn ($line) => $line->outstanding);
@endphp

<x-app-layout :title="$order->number">
    <x-slot:actions>
        @if ($canManage && $order->status->isEditable())
            <x-button :href="route('purchase-orders.edit', $order)" variant="secondary" size="sm">
                {{ __('app.common.edit') }}
            </x-button>

            <form
                method="POST"
                action="{{ route('purchase-orders.approve', $order) }}"
                onsubmit="return confirm(@js(__('app.po.confirm_approve', ['number' => $order->number])));"
            >
                @csrf
                <x-button type="submit" size="sm">{{ __('app.po.approve') }}</x-button>
            </form>
        @endif

        @if ($canManage && $order->status->isReceivable() && $outstanding > 0)
            <x-button :href="route('purchase-orders.receive', $order)" size="sm">{{ __('app.po.receive') }}</x-button>
        @endif
    </x-slot:actions>

    <div class="space-y-5">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-stat-card :label="__('app.common.status')" :value="$order->status->label()" :hint="$order->number" />

            <x-stat-card
                :label="__('app.po.total_value')"
                :value="config('app.currency_symbol').number_format($order->subtotal, 2)"
                :hint="__('app.po.subtotal')"
            />

            <x-stat-card
                :label="__('app.po.received_qty')"
                :count="$order->lines->sum('quantity_received')"
                :hint="$order->is_fully_received ? __('app.po.fully_received') : __('app.po.partially_received')"
                :tone="$order->is_fully_received ? 'success' : 'default'"
            />

            <x-stat-card
                :label="__('app.po.outstanding')"
                :count="$outstanding"
                :hint="__('app.po.quantity')"
                :tone="$outstanding > 0 ? 'warning' : 'success'"
            />
        </div>

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
            <x-card :title="__('app.common.details')" class="xl:col-span-1">
                <dl class="divide-y divide-line text-sm">
                    <div class="flex justify-between gap-4 px-4 py-3">
                        <dt class="text-ink-muted">{{ __('app.po.number') }}</dt>
                        <dd class="text-right font-mono text-ink">{{ $order->number }}</dd>
                    </div>

                    <div class="flex justify-between gap-4 px-4 py-3">
                        <dt class="text-ink-muted">{{ __('app.po.supplier') }}</dt>
                        <dd class="text-right">
                            <a
                                href="{{ route('suppliers.show', $order->supplier) }}"
                                class="text-brand-600 hover:text-brand-700 dark:text-brand-400 dark:hover:text-brand-300"
                            >
                                {{ $order->supplier->name }}
                            </a>
                        </dd>
                    </div>

                    <div class="flex justify-between gap-4 px-4 py-3">
                        <dt class="text-ink-muted">{{ __('app.po.ordered_at') }}</dt>
                        <dd class="text-right text-ink">
                            <time datetime="{{ $order->ordered_at->toDateString() }}">{{ $order->ordered_at->format('d M Y') }}</time>
                        </dd>
                    </div>

                    <div class="flex justify-between gap-4 px-4 py-3">
                        <dt class="text-ink-muted">{{ __('app.po.expected_at') }}</dt>
                        <dd class="text-right text-ink">
                            @if ($order->expected_at)
                                <time datetime="{{ $order->expected_at->toDateString() }}">{{ $order->expected_at->format('d M Y') }}</time>
                            @else
                                —
                            @endif
                        </dd>
                    </div>

                    <div class="flex justify-between gap-4 px-4 py-3">
                        <dt class="text-ink-muted">{{ __('app.common.created') }}</dt>
                        <dd class="text-right text-ink">
                            {{ $order->creator?->name ?? __('app.movement.system') }}
                            <p class="text-xs text-ink-muted">{{ $order->created_at->format('d M Y H:i') }}</p>
                        </dd>
                    </div>

                    @if ($order->approved_at)
                        <div class="flex justify-between gap-4 px-4 py-3">
                            <dt class="text-ink-muted">{{ __('app.po.approved') }}</dt>
                            <dd class="text-right text-ink">
                                {{ $order->approver?->name ?? __('app.movement.system') }}
                                <p class="text-xs text-ink-muted">{{ $order->approved_at->format('d M Y H:i') }}</p>
                            </dd>
                        </div>
                    @endif

                    @if ($order->received_at)
                        <div class="flex justify-between gap-4 px-4 py-3">
                            <dt class="text-ink-muted">{{ __('app.po.received') }}</dt>
                            <dd class="text-right text-ink">
                                <time datetime="{{ $order->received_at->toIso8601String() }}">
                                    {{ $order->received_at->format('d M Y H:i') }}
                                </time>
                            </dd>
                        </div>
                    @endif
                </dl>

                @if (filled($order->notes))
                    <div class="border-t border-line px-4 py-3">
                        <h3 class="text-xs font-semibold uppercase tracking-wider text-ink-muted">{{ __('app.common.notes') }}</h3>
                        <p class="mt-1.5 text-sm whitespace-pre-line text-ink">{{ $order->notes }}</p>
                    </div>
                @endif

                @if ($canManage && ($order->status->isCancellable() || ($canDelete && ! $order->has_receipts)))
                    <div class="border-t border-line px-4 py-3">
                        <h3 class="text-xs font-semibold uppercase tracking-wider text-ink-muted">
                            {{ __('app.common.danger_zone') }}
                        </h3>

                        <div class="mt-2 flex flex-wrap gap-2">
                            @if ($order->status->isCancellable())
                                <form
                                    method="POST"
                                    action="{{ route('purchase-orders.cancel', $order) }}"
                                    onsubmit="return confirm(@js(__('app.po.confirm_cancel', ['number' => $order->number])));"
                                >
                                    @csrf
                                    <x-button type="submit" variant="secondary" size="sm">
                                        {{ __('app.po.cancel_order') }}
                                    </x-button>
                                </form>
                            @endif

                            @if ($canDelete && ! $order->has_receipts)
                                <form
                                    method="POST"
                                    action="{{ route('purchase-orders.destroy', $order) }}"
                                    onsubmit="return confirm(@js(__('app.common.confirm_delete', ['name' => $order->number])));"
                                >
                                    @csrf
                                    @method('DELETE')
                                    <x-button type="submit" variant="danger" size="sm">
                                        {{ __('app.common.delete') }}
                                    </x-button>
                                </form>
                            @endif
                        </div>
                    </div>
                @endif
            </x-card>

            <x-card :title="__('app.po.lines')" :description="__('app.po.lines_sub')" class="xl:col-span-2">
                @if ($order->lines->isEmpty())
                    <x-empty-state :title="__('app.po.no_lines')" :description="__('app.po.no_lines_sub')">
                        @if ($canManage && $order->status->isEditable())
                            <x-button :href="route('purchase-orders.edit', $order)" size="sm">{{ __('app.common.edit') }}</x-button>
                        @endif
                    </x-empty-state>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-line text-sm">
                            <thead class="bg-surface-sunken text-left text-xs font-semibold uppercase tracking-wider text-ink-muted">
                                <tr>
                                    <th scope="col" class="px-4 py-2.5">{{ __('app.po.product') }}</th>
                                    <th scope="col" class="px-4 py-2.5 text-right">{{ __('app.po.quantity') }}</th>
                                    <th scope="col" class="px-4 py-2.5 text-right">{{ __('app.po.received_qty') }}</th>
                                    <th scope="col" class="px-4 py-2.5 text-right">{{ __('app.po.outstanding') }}</th>
                                    <th scope="col" class="px-4 py-2.5 text-right">{{ __('app.po.unit_cost') }}</th>
                                    <th scope="col" class="px-4 py-2.5 text-right">{{ __('app.po.line_total') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-line">
                                @foreach ($order->lines as $index => $line)
                                    <tr class="animate-fade stagger row-hover" style="--d: {{ $index * 35 }}ms">
                                        <td class="px-4 py-3">
                                            @if ($line->product && ! $line->product->trashed())
                                                <a
                                                    href="{{ route('products.show', $line->product) }}"
                                                    class="font-medium text-ink hover:text-brand-600 dark:hover:text-brand-400"
                                                >
                                                    {{ $line->product->name }}
                                                </a>
                                            @else
                                                <span class="font-medium text-ink-muted line-through">
                                                    {{ $line->product?->name ?? '—' }}
                                                </span>
                                            @endif
                                            <p class="font-mono text-xs text-ink-muted">{{ $line->product?->sku }}</p>
                                        </td>
                                        <td class="px-4 py-3 text-right tabular-nums text-ink">
                                            @qty($line->quantity_ordered)
                                            <span class="text-xs text-ink-muted">{{ $line->product?->unit }}</span>
                                        </td>
                                        <td class="px-4 py-3 text-right tabular-nums text-ink-muted">
                                            @qty($line->quantity_received)
                                        </td>
                                        <td class="px-4 py-3 text-right tabular-nums">
                                            @if ($line->outstanding > 0)
                                                <span class="font-medium text-amber-600 dark:text-amber-400">@qty($line->outstanding)</span>
                                            @else
                                                <x-badge classes="bg-emerald-50 dark:bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 ring-emerald-600/20 dark:ring-emerald-400/30">
                                                    {{ __('app.po.fully_received') }}
                                                </x-badge>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-right tabular-nums text-ink-muted">@money($line->unit_cost)</td>
                                        <td class="px-4 py-3 text-right tabular-nums text-ink">@money($line->line_total)</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="border-t border-line bg-surface-sunken/60">
                                <tr>
                                    <th scope="row" colspan="5" class="px-4 py-3 text-right text-sm font-semibold text-ink">
                                        {{ __('app.po.subtotal') }}
                                    </th>
                                    <td class="px-4 py-3 text-right text-sm font-semibold tabular-nums text-ink">
                                        @money($order->subtotal)
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                @endif
            </x-card>
        </div>
    </div>
</x-app-layout>
