<x-app-layout :title="__('app.po.title')">
    <x-slot:actions>
        @if (auth()->user()->canManageCatalog())
            <x-button :href="route('purchase-orders.create')" size="sm">{{ __('app.po.add') }}</x-button>
        @endif
    </x-slot:actions>

    <div class="space-y-5">
        <x-card>
            <form method="GET" action="{{ route('purchase-orders.index') }}" class="flex flex-wrap items-end gap-3 p-4">
                <x-field
                    :label="__('app.common.search')"
                    name="search"
                    :value="$filters['search'] ?? null"
                    :placeholder="__('app.po.number').', '.__('app.po.supplier')"
                    class="min-w-56 flex-1"
                />

                <x-field
                    :label="__('app.common.status')"
                    name="status"
                    type="select"
                    :value="$filters['status'] ?? null"
                    :placeholder="__('app.po.all_statuses')"
                    :options="$statuses"
                    class="w-44"
                />

                <x-button type="submit" size="sm">{{ __('app.common.search') }}</x-button>
                <x-button :href="route('purchase-orders.index')" variant="secondary" size="sm">{{ __('app.common.reset') }}</x-button>

                <p class="ml-auto self-center text-xs text-ink-muted">
                    {{ __('app.common.found', ['count' => number_format($orders->total())]) }}
                </p>
            </form>
        </x-card>

        <x-card>
            @if ($orders->isEmpty())
                <x-empty-state :title="__('app.po.none_yet')" :description="__('app.po.none_yet_sub')">
                    @if (auth()->user()->canManageCatalog())
                        <x-button :href="route('purchase-orders.create')" size="sm">{{ __('app.po.add') }}</x-button>
                    @endif
                </x-empty-state>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-line text-sm">
                        <thead class="bg-surface-sunken text-left text-xs font-semibold uppercase tracking-wider text-ink-muted">
                            <tr>
                                <th scope="col" class="px-4 py-2.5">{{ __('app.po.number') }}</th>
                                <th scope="col" class="px-4 py-2.5">{{ __('app.po.supplier') }}</th>
                                <th scope="col" class="px-4 py-2.5">{{ __('app.po.ordered_at') }}</th>
                                <th scope="col" class="px-4 py-2.5">{{ __('app.po.expected_at') }}</th>
                                <th scope="col" class="px-4 py-2.5 text-right">{{ __('app.po.quantity') }}</th>
                                <th scope="col" class="px-4 py-2.5 text-right">{{ __('app.po.total_value') }}</th>
                                <th scope="col" class="px-4 py-2.5">{{ __('app.common.status') }}</th>
                                <th scope="col" class="px-4 py-2.5 text-right">
                                    <span class="sr-only">{{ __('app.common.actions') }}</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($orders as $index => $order)
                                @php
                                    $ordered = (int) ($order->ordered_units ?? 0);
                                    $received = (int) ($order->received_units ?? 0);
                                @endphp
                                <tr class="animate-fade stagger row-hover" style="--d: {{ $index * 35 }}ms">
                                    <td class="px-4 py-3">
                                        <a
                                            href="{{ route('purchase-orders.show', $order) }}"
                                            class="font-mono font-medium text-ink hover:text-brand-600 dark:hover:text-brand-400"
                                        >
                                            {{ $order->number }}
                                        </a>
                                        <p class="text-xs text-ink-muted">
                                            {{ __('app.po.lines') }}: @qty($order->lines_count)
                                        </p>
                                    </td>
                                    <td class="px-4 py-3">
                                        <a
                                            href="{{ route('suppliers.show', $order->supplier) }}"
                                            class="text-ink hover:text-brand-600 dark:hover:text-brand-400"
                                        >
                                            {{ $order->supplier->name }}
                                        </a>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-ink-muted">
                                        <time datetime="{{ $order->ordered_at->toDateString() }}">
                                            {{ $order->ordered_at->format('d M Y') }}
                                        </time>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-ink-muted">
                                        @if ($order->expected_at)
                                            <time datetime="{{ $order->expected_at->toDateString() }}">
                                                {{ $order->expected_at->format('d M Y') }}
                                            </time>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right tabular-nums">
                                        @qty($ordered)
                                        @if ($received > 0 && $received < $ordered)
                                            <p class="text-xs text-amber-600 dark:text-amber-400">
                                                {{ __('app.po.partially_received') }}
                                            </p>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right tabular-nums text-ink-muted">
                                        @money($order->order_value)
                                    </td>
                                    <td class="px-4 py-3">
                                        <x-badge :classes="$order->status->badgeClasses()">{{ $order->status->label() }}</x-badge>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center justify-end gap-1">
                                            <x-button :href="route('purchase-orders.show', $order)" variant="ghost" size="sm">
                                                {{ __('app.common.view') }}
                                            </x-button>

                                            @if (auth()->user()->canManageCatalog() && $order->status->isReceivable())
                                                <x-button :href="route('purchase-orders.receive', $order)" variant="ghost" size="sm">
                                                    {{ __('app.po.receive') }}
                                                </x-button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($orders->hasPages())
                    <div class="border-t border-line px-4 py-3">
                        {{ $orders->links() }}
                    </div>
                @endif
            @endif
        </x-card>
    </div>
</x-app-layout>
