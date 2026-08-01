<x-app-layout :title="__('app.category.title')">
    <x-slot:actions>
        @if (auth()->user()->canManageCatalog())
            <x-button :href="route('categories.create')" size="sm">{{ __('app.category.add') }}</x-button>
        @endif
    </x-slot:actions>

    <div class="space-y-5">
        <x-card>
            <form method="GET" action="{{ route('categories.index') }}" class="flex flex-wrap items-end gap-3 p-4">
                <x-field
                    :label="__('app.common.search')"
                    name="search"
                    :value="$filters['search'] ?? null"
                    :placeholder="__('app.category.name')"
                    class="min-w-56 flex-1"
                />
                @if ($trashed)
                    <input type="hidden" name="trashed" value="1">
                @endif

                <x-button type="submit" size="sm">{{ __('app.common.search') }}</x-button>
                <x-button :href="route('categories.index')" variant="secondary" size="sm">{{ __('app.common.reset') }}</x-button>
            </form>
        </x-card>

        @if (auth()->user()->canDelete() && ($trashedCount > 0 || $trashed))
            <div class="flex flex-wrap items-center gap-2">
                @if ($trashed)
                    <x-button :href="route('categories.index')" variant="secondary" size="sm">
                        {{ __('app.common.back_to_list') }}
                    </x-button>
                @else
                    <x-button :href="route('categories.index', ['trashed' => 1])" variant="secondary" size="sm">
                        {{ __('app.common.recycle_bin', ['count' => $trashedCount]) }}
                    </x-button>
                @endif
            </div>
        @endif

        <x-card :title="$trashed ? __('app.common.deleted_records') : null">
            @if ($categories->isEmpty())
                <x-empty-state
                    :title="$trashed ? __('app.common.recycle_bin_empty') : __('app.category.none_yet')"
                    :description="$trashed ? __('app.common.recycle_bin_empty_sub') : __('app.category.none_yet_sub')"
                >
                    @if (! $trashed && auth()->user()->canManageCatalog())
                        <x-button :href="route('categories.create')" size="sm">{{ __('app.category.add') }}</x-button>
                    @endif
                </x-empty-state>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-line text-sm">
                        <thead class="bg-surface-sunken text-left text-xs font-semibold uppercase tracking-wider text-ink-muted">
                            <tr>
                                <th scope="col" class="px-4 py-2.5">{{ __('app.category.one') }}</th>
                                <th scope="col" class="px-4 py-2.5 text-right">{{ __('app.category.products_count') }}</th>
                                <th scope="col" class="px-4 py-2.5 text-right">{{ __('app.category.units') }}</th>
                                <th scope="col" class="px-4 py-2.5">{{ __('app.common.status') }}</th>
                                <th scope="col" class="px-4 py-2.5 text-right"><span class="sr-only">{{ __('app.common.actions') }}</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($categories as $index => $category)
                                <tr class="animate-fade stagger row-hover" style="--d: {{ $index * 35 }}ms">
                                    <td class="px-4 py-3">
                                        <p class="font-medium text-ink">{{ $category->name }}</p>
                                        @if (filled($category->description))
                                            <p class="max-w-md truncate text-xs text-ink-muted">{{ $category->description }}</p>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right tabular-nums">
                                        <a
                                            href="{{ route('products.index', ['category' => $category->id]) }}"
                                            class="font-medium text-brand-600 dark:text-brand-400 hover:text-brand-700 dark:hover:text-brand-300 dark:text-brand-300"
                                        >
                                            @qty($category->products_count)
                                        </a>
                                    </td>
                                    <td class="px-4 py-3 text-right tabular-nums text-ink-muted">
                                        @qty($category->units_on_hand ?? 0)
                                    </td>
                                    <td class="px-4 py-3">
                                        @if ($category->is_active)
                                            <x-badge classes="bg-emerald-50 dark:bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 ring-emerald-600/20 dark:ring-emerald-400/30">{{ __('app.common.active') }}</x-badge>
                                        @else
                                            <x-badge classes="bg-surface-sunken text-ink-muted ring-line">{{ __('app.common.inactive') }}</x-badge>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center justify-end gap-1">
                                            @if ($trashed)
                                                <form method="POST" action="{{ route('categories.restore', $category->id) }}">
                                                    @csrf
                                                    <x-button type="submit" variant="secondary" size="sm">
                                                        {{ __('app.common.restore') }}
                                                    </x-button>
                                                </form>
                                            @else
                                                @if (auth()->user()->canManageCatalog())
                                                    <x-button :href="route('categories.edit', $category)" variant="ghost" size="sm">{{ __('app.common.edit') }}</x-button>
                                                @endif
                                                @if (auth()->user()->canDelete())
                                                    <form
                                                        method="POST"
                                                        action="{{ route('categories.destroy', $category) }}"
                                                        {{-- Was a hardcoded English sentence, which the
                                                             owner of this shop cannot read. --}}
                                                        onsubmit="return confirm(@js(__('app.category.confirm_delete', ['name' => $category->name, 'count' => $category->products_count])));"
                                                    >
                                                        @csrf
                                                        @method('DELETE')
                                                        <x-button type="submit" variant="ghost" size="sm" class="text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-500/15 dark:bg-rose-500/15">
                                                            {{ __('app.common.delete') }}
                                                        </x-button>
                                                    </form>
                                                @endif
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($categories->hasPages())
                    <div class="border-t border-line px-4 py-3">
                        {{ $categories->links() }}
                    </div>
                @endif
            @endif
        </x-card>
    </div>
</x-app-layout>
