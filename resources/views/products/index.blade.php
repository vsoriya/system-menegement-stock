@php
    $sort = $filters['sort'] ?? 'name';
    $direction = ($filters['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

    $sortLink = function (string $column) use ($filters, $sort, $direction) {
        return request()->fullUrlWithQuery([
            'sort' => $column,
            'direction' => $sort === $column && $direction === 'asc' ? 'desc' : 'asc',
        ]);
    };
@endphp

<x-app-layout :title="__('app.product.title')">
    <x-slot:actions>
        @if (auth()->user()->canManageCatalog())
            <x-button :href="route('products.create')" size="sm">{{ __('app.product.add') }}</x-button>
        @endif
    </x-slot:actions>

    <div class="space-y-5">
        <!-- Filters -->
        <x-card>
            <form method="GET" action="{{ route('products.index') }}" class="grid grid-cols-1 gap-4 p-4 sm:grid-cols-2 lg:grid-cols-5">
                <x-field
                    :label="__('app.common.search')"
                    name="search"
                    :value="$filters['search'] ?? null"
                    :placeholder="__('app.product.search_placeholder')"
                    class="lg:col-span-2"
                />

                <x-field
                    :label="__('app.product.filter_status')"
                    name="status"
                    type="select"
                    :value="$filters['status'] ?? null"
                    :placeholder="__('app.product.any_status')"
                    :options="[
                        'in_stock' => __('app.stock_status.in_stock'),
                        'low_stock' => __('app.stock_status.low_stock'),
                        'out_of_stock' => __('app.stock_status.out_of_stock'),
                        'needs_reorder' => __('app.stock_status.needs_reorder'),
                    ]"
                />

                <x-field
                    :label="__('app.product.category')"
                    name="category"
                    type="select"
                    :value="$filters['category'] ?? null"
                    :placeholder="__('app.product.all_categories')"
                    :options="$categories->pluck('name', 'id')->all()"
                />

                <x-field
                    :label="__('app.product.supplier')"
                    name="supplier"
                    type="select"
                    :value="$filters['supplier'] ?? null"
                    :placeholder="__('app.product.all_suppliers')"
                    :options="$suppliers->pluck('name', 'id')->all()"
                />

                {{-- Keeps the recycle bin open while filtering inside it. --}}
                @if ($trashed)
                    <input type="hidden" name="trashed" value="1">
                @endif

                <div class="flex items-end gap-2 sm:col-span-2 lg:col-span-5">
                    <x-button type="submit" size="sm">{{ __('app.common.apply') }}</x-button>
                    <x-button :href="route('products.index')" variant="secondary" size="sm">{{ __('app.common.reset') }}</x-button>
                    <p class="ml-auto self-center text-xs text-ink-muted">
                        {{ __('app.common.found', ['count' => number_format($products->total())]) }}
                    </p>
                </div>
            </form>
        </x-card>

        {{-- The restore routes existed from the start but nothing in the app
             reached them, so a deleted product was effectively unrecoverable
             without going into the database by hand. --}}
        @if (auth()->user()->canDelete() && ($trashedCount > 0 || $trashed))
            <div class="flex flex-wrap items-center gap-2">
                @if ($trashed)
                    <x-button :href="route('products.index')" variant="secondary" size="sm">
                        {{ __('app.common.back_to_list') }}
                    </x-button>
                @else
                    <x-button :href="route('products.index', ['trashed' => 1])" variant="secondary" size="sm">
                        {{ __('app.common.recycle_bin', ['count' => $trashedCount]) }}
                    </x-button>
                @endif
            </div>
        @endif

        <!-- Table -->
        <x-card :title="$trashed ? __('app.common.deleted_records') : null">
            @if ($products->isEmpty())
                <x-empty-state
                    :title="$trashed ? __('app.common.recycle_bin_empty') : __('app.product.none_match')"
                    :description="$trashed ? __('app.common.recycle_bin_empty_sub') : __('app.product.none_match_sub')"
                >
                    @unless ($trashed)
                        <x-button :href="route('products.index')" variant="secondary" size="sm">{{ __('app.common.clear') }}</x-button>
                        @if (auth()->user()->canManageCatalog())
                            <x-button :href="route('products.create')" size="sm">{{ __('app.product.add') }}</x-button>
                        @endif
                    @endunless
                </x-empty-state>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-line text-sm">
                        <thead class="bg-surface-sunken text-left text-xs font-semibold uppercase tracking-wider text-ink-muted">
                            <tr>
                                <th scope="col" class="px-4 py-2.5">
                                    <a href="{{ $sortLink('name') }}" class="inline-flex items-center gap-1 hover:text-ink">
                                        {{ __('app.product.one') }}
                                        @if ($sort === 'name')
                                            <span aria-hidden="true">{{ $direction === 'asc' ? '↑' : '↓' }}</span>
                                            <span class="sr-only">sorted {{ $direction === 'asc' ? 'ascending' : 'descending' }}</span>
                                        @endif
                                    </a>
                                </th>
                                <th scope="col" class="px-4 py-2.5">{{ __('app.product.category') }}</th>
                                <th scope="col" class="px-4 py-2.5">{{ __('app.product.supplier') }}</th>
                                <th scope="col" class="px-4 py-2.5 text-right">
                                    <a href="{{ $sortLink('quantity') }}" class="inline-flex items-center gap-1 hover:text-ink">
                                        {{ __('app.product.on_hand') }}
                                        @if ($sort === 'quantity')
                                            <span aria-hidden="true">{{ $direction === 'asc' ? '↑' : '↓' }}</span>
                                            <span class="sr-only">sorted {{ $direction === 'asc' ? 'ascending' : 'descending' }}</span>
                                        @endif
                                    </a>
                                </th>
                                <th scope="col" class="px-4 py-2.5 text-right">
                                    <a href="{{ $sortLink('cost_price') }}" class="inline-flex items-center gap-1 hover:text-ink">
                                        {{ __('app.product.cost') }}
                                        @if ($sort === 'cost_price')
                                            <span aria-hidden="true">{{ $direction === 'asc' ? '↑' : '↓' }}</span>
                                        @endif
                                    </a>
                                </th>
                                <th scope="col" class="px-4 py-2.5 text-right">{{ __('app.product.value') }}</th>
                                <th scope="col" class="px-4 py-2.5">{{ __('app.common.status') }}</th>
                                <th scope="col" class="px-4 py-2.5 text-right">
                                    <span class="sr-only">{{ __('app.common.actions') }}</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($products as $index => $product)
                                <tr class="animate-fade stagger row-hover" style="--d: {{ $index * 35 }}ms">
                                    <td class="px-4 py-3">
                                        @if ($trashed)
                                            {{-- Not a link: route binding skips deleted records, so
                                                 the detail page would answer 404. --}}
                                            <span class="font-medium text-ink">{{ $product->name }}</span>
                                        @else
                                            <a href="{{ route('products.show', $product) }}" class="font-medium text-ink transition hover:text-brand-600 dark:hover:text-brand-400 dark:text-brand-400">
                                                {{ $product->name }}
                                            </a>
                                        @endif
                                        <p class="font-mono text-xs text-ink-muted">{{ $product->sku }}</p>
                                        @unless ($product->is_active)
                                            <x-badge classes="bg-surface-sunken text-ink-muted ring-line" class="mt-1">{{ __('app.common.inactive') }}</x-badge>
                                        @endunless
                                    </td>
                                    <td class="px-4 py-3 text-ink-muted">{{ $product->category?->name ?? '—' }}</td>
                                    <td class="px-4 py-3 text-ink-muted">{{ $product->supplier?->name ?? '—' }}</td>
                                    <td class="px-4 py-3 text-right tabular-nums">
                                        <span class="font-semibold">@qty($product->quantity)</span>
                                        <span class="text-xs text-ink-muted">{{ $product->unit }}</span>
                                        <p class="text-xs text-ink-subtle">{{ __('app.product.reorder_at', ['count' => number_format($product->reorder_level)]) }}</p>
                                    </td>
                                    <td class="px-4 py-3 text-right tabular-nums">@money($product->cost_price)</td>
                                    <td class="px-4 py-3 text-right font-medium tabular-nums">@money($product->stock_value)</td>
                                    <td class="px-4 py-3">
                                        <x-badge
                                            :classes="$product->stock_status_classes"
                                            :dot="$product->stock_status === 'out_of_stock' ? 'pulse' : true"
                                        >{{ $product->stock_status_label }}</x-badge>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center justify-end gap-1">
                                            @if ($trashed)
                                                <form method="POST" action="{{ route('products.restore', $product->id) }}">
                                                    @csrf
                                                    <x-button type="submit" variant="secondary" size="sm">
                                                        {{ __('app.common.restore') }}
                                                    </x-button>
                                                </form>

                                                <form
                                                    method="POST"
                                                    action="{{ route('products.force-delete', $product->id) }}"
                                                    onsubmit="return confirm(@js(__('app.common.confirm_purge', ['name' => $product->name])));"
                                                >
                                                    @csrf
                                                    @method('DELETE')
                                                    <x-button type="submit" variant="danger" size="sm">
                                                        {{ __('app.common.delete_permanently') }}
                                                    </x-button>
                                                </form>
                                            @else
                                                <x-button
                                                    :href="route('movements.create', ['product' => $product->id])"
                                                    variant="secondary"
                                                    size="sm"
                                                >
                                                    {{ __('app.movement.record') }}
                                                </x-button>
                                                @if (auth()->user()->canManageCatalog())
                                                    <x-button
                                                        :href="route('products.edit', $product)"
                                                        variant="ghost"
                                                        size="sm"
                                                    >
                                                        {{ __('app.common.edit') }}
                                                    </x-button>
                                                @endif
                                            @endif
                                        </div>
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
