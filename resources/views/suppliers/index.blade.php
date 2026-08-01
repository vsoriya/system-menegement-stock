<x-app-layout :title="__('app.supplier.title')">
    <x-slot:actions>
        @if (auth()->user()->canManageCatalog())
            <x-button :href="route('suppliers.create')" size="sm">{{ __('app.supplier.add') }}</x-button>
        @endif
    </x-slot:actions>

    <div class="space-y-5">
        <x-card>
            <form method="GET" action="{{ route('suppliers.index') }}" class="flex flex-wrap items-end gap-3 p-4">
                <x-field
                    :label="__('app.common.search')"
                    name="search"
                    :value="$filters['search'] ?? null"
                    :placeholder="__('app.supplier.search_placeholder')"
                    class="min-w-56 flex-1"
                />
                @if ($trashed)
                    <input type="hidden" name="trashed" value="1">
                @endif

                <x-button type="submit" size="sm">{{ __('app.common.search') }}</x-button>
                <x-button :href="route('suppliers.index')" variant="secondary" size="sm">{{ __('app.common.reset') }}</x-button>
            </form>
        </x-card>

        @if (auth()->user()->canDelete() && ($trashedCount > 0 || $trashed))
            <div class="flex flex-wrap items-center gap-2">
                @if ($trashed)
                    <x-button :href="route('suppliers.index')" variant="secondary" size="sm">
                        {{ __('app.common.back_to_list') }}
                    </x-button>
                @else
                    <x-button :href="route('suppliers.index', ['trashed' => 1])" variant="secondary" size="sm">
                        {{ __('app.common.recycle_bin', ['count' => $trashedCount]) }}
                    </x-button>
                @endif
            </div>
        @endif

        <x-card :title="$trashed ? __('app.common.deleted_records') : null">
            @if ($suppliers->isEmpty())
                <x-empty-state
                    :title="$trashed ? __('app.common.recycle_bin_empty') : __('app.supplier.none_yet')"
                    :description="$trashed ? __('app.common.recycle_bin_empty_sub') : __('app.supplier.none_yet_sub')"
                >
                    @if (! $trashed && auth()->user()->canManageCatalog())
                        <x-button :href="route('suppliers.create')" size="sm">{{ __('app.supplier.add') }}</x-button>
                    @endif
                </x-empty-state>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-line text-sm">
                        <thead class="bg-surface-sunken text-left text-xs font-semibold uppercase tracking-wider text-ink-muted">
                            <tr>
                                <th scope="col" class="px-4 py-2.5">{{ __('app.supplier.one') }}</th>
                                <th scope="col" class="px-4 py-2.5">{{ __('app.supplier.contact') }}</th>
                                <th scope="col" class="px-4 py-2.5 text-right">{{ __('app.category.products_count') }}</th>
                                <th scope="col" class="px-4 py-2.5 text-right">{{ __('app.category.units') }}</th>
                                <th scope="col" class="px-4 py-2.5">{{ __('app.common.status') }}</th>
                                <th scope="col" class="px-4 py-2.5 text-right"><span class="sr-only">{{ __('app.common.actions') }}</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($suppliers as $index => $supplier)
                                <tr class="animate-fade stagger row-hover" style="--d: {{ $index * 35 }}ms">
                                    <td class="px-4 py-3">
                                        @if ($trashed)
                                            {{-- Route binding skips deleted records, so linking here
                                                 would land on a 404. --}}
                                            <span class="font-medium text-ink">{{ $supplier->name }}</span>
                                        @else
                                            <a href="{{ route('suppliers.show', $supplier) }}" class="font-medium text-ink hover:text-brand-600 dark:hover:text-brand-400 dark:text-brand-400">
                                                {{ $supplier->name }}
                                            </a>
                                        @endif
                                        @if (filled($supplier->address))
                                            <p class="max-w-xs truncate text-xs text-ink-muted">{{ $supplier->address }}</p>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-ink-muted">
                                        <p>{{ $supplier->contact_person ?? '—' }}</p>
                                        @if (filled($supplier->phone))
                                            <p class="text-xs text-ink-muted">{{ $supplier->phone }}</p>
                                        @endif
                                        @if (filled($supplier->email))
                                            <a href="mailto:{{ $supplier->email }}" class="text-xs text-brand-600 dark:text-brand-400 hover:text-brand-700 dark:hover:text-brand-300 dark:text-brand-300">
                                                {{ $supplier->email }}
                                            </a>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right tabular-nums">
                                        <a
                                            href="{{ route('products.index', ['supplier' => $supplier->id]) }}"
                                            class="font-medium text-brand-600 dark:text-brand-400 hover:text-brand-700 dark:hover:text-brand-300 dark:text-brand-300"
                                        >
                                            @qty($supplier->products_count)
                                        </a>
                                    </td>
                                    <td class="px-4 py-3 text-right tabular-nums text-ink-muted">
                                        @qty($supplier->units_on_hand ?? 0)
                                    </td>
                                    <td class="px-4 py-3">
                                        @if ($supplier->is_active)
                                            <x-badge classes="bg-emerald-50 dark:bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 ring-emerald-600/20 dark:ring-emerald-400/30">{{ __('app.common.active') }}</x-badge>
                                        @else
                                            <x-badge classes="bg-surface-sunken text-ink-muted ring-line">{{ __('app.common.inactive') }}</x-badge>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center justify-end gap-1">
                                            @if ($trashed)
                                                <form method="POST" action="{{ route('suppliers.restore', $supplier->id) }}">
                                                    @csrf
                                                    <x-button type="submit" variant="secondary" size="sm">
                                                        {{ __('app.common.restore') }}
                                                    </x-button>
                                                </form>
                                            @elseif (auth()->user()->canManageCatalog())
                                                <x-button :href="route('suppliers.edit', $supplier)" variant="ghost" size="sm">{{ __('app.common.edit') }}</x-button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($suppliers->hasPages())
                    <div class="border-t border-line px-4 py-3">
                        {{ $suppliers->links() }}
                    </div>
                @endif
            @endif
        </x-card>
    </div>
</x-app-layout>
