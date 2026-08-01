<x-app-layout :title="__('app.movement.title')">
    <x-slot:actions>
        <x-button :href="route('movements.create')" size="sm">{{ __('app.movement.record') }}</x-button>
    </x-slot:actions>

    <div class="space-y-5">
        <!-- Filters -->
        <x-card>
            <form method="GET" action="{{ route('movements.index') }}" class="grid grid-cols-1 gap-4 p-4 sm:grid-cols-2 lg:grid-cols-5">
                <x-field
                    :label="__('app.common.search')"
                    name="search"
                    :value="$filters['search'] ?? null"
                    :placeholder="__('app.movement.search_placeholder')"
                    class="lg:col-span-2"
                />

                <x-field
                    :label="__('app.movement.type')"
                    name="type"
                    type="select"
                    :value="$filters['type'] ?? null"
                    :placeholder="__('app.movement.all_types')"
                    :options="$types"
                />

                <x-field
                    :label="__('app.movement.from')"
                    name="from"
                    type="date"
                    :value="$filters['from'] ?? null"
                />

                <x-field
                    :label="__('app.movement.to')"
                    name="to"
                    type="date"
                    :value="$filters['to'] ?? null"
                />

                <div class="flex items-end gap-2 sm:col-span-2 lg:col-span-5">
                    <x-button type="submit" size="sm">{{ __('app.common.apply') }}</x-button>
                    <x-button :href="route('movements.index')" variant="secondary" size="sm">{{ __('app.common.reset') }}</x-button>
                    <p class="ml-auto self-center text-xs text-ink-muted">
                        {{ __('app.common.found', ['count' => number_format($movements->total())]) }}
                    </p>
                </div>
            </form>
        </x-card>

        <!-- History -->
        <x-card>
            @if ($movements->isEmpty())
                <x-empty-state
                    :title="__('app.movement.none_found')"
                    :description="__('app.movement.none_found_sub')"
                >
                    <x-button :href="route('movements.index')" variant="secondary" size="sm">{{ __('app.common.clear') }}</x-button>
                    <x-button :href="route('movements.create')" size="sm">{{ __('app.movement.record') }}</x-button>
                </x-empty-state>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-line text-sm">
                        <thead class="bg-surface-sunken text-left text-xs font-semibold uppercase tracking-wider text-ink-muted">
                            <tr>
                                <th scope="col" class="px-4 py-2.5">{{ __('app.common.when') }}</th>
                                <th scope="col" class="px-4 py-2.5">{{ __('app.product.one') }}</th>
                                <th scope="col" class="px-4 py-2.5">{{ __('app.movement.type') }}</th>
                                <th scope="col" class="px-4 py-2.5 text-right">{{ __('app.movement.change') }}</th>
                                <th scope="col" class="px-4 py-2.5 text-right">{{ __('app.movement.balance') }}</th>
                                <th scope="col" class="px-4 py-2.5 text-right">{{ __('app.product.value') }}</th>
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
                                        <a href="{{ route('products.show', $movement->product) }}" class="font-medium text-ink hover:text-brand-600 dark:hover:text-brand-400 dark:text-brand-400">
                                            {{ $movement->product->name }}
                                        </a>
                                        <p class="font-mono text-xs text-ink-muted">{{ $movement->product->sku }}</p>
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
                                    <td class="px-4 py-3 text-right tabular-nums text-ink-muted">
                                        {{ $movement->total_cost === null ? '—' : config('app.currency_symbol').number_format($movement->total_cost, 2) }}
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
</x-app-layout>
