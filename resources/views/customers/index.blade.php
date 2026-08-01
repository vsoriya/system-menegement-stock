<x-app-layout :title="__('app.customer.title')">
    <x-slot:actions>
        <x-button :href="route('customers.create')" size="sm">{{ __('app.customer.add') }}</x-button>
    </x-slot:actions>

    <div class="space-y-5">
        <x-card>
            <form method="GET" action="{{ route('customers.index') }}" class="flex flex-wrap items-end gap-3 p-4">
                <x-field
                    :label="__('app.common.search')"
                    name="search"
                    :value="$filters['search'] ?? null"
                    :placeholder="__('app.customer.search_placeholder')"
                    class="min-w-56 flex-1"
                />

                <x-field
                    :label="__('app.common.status')"
                    name="active"
                    type="select"
                    :value="$filters['active'] ?? null"
                    :placeholder="__('app.common.all')"
                    :options="['1' => __('app.common.active'), '0' => __('app.common.inactive')]"
                    class="min-w-40"
                />

                @if ($trashed)
                    <input type="hidden" name="trashed" value="1">
                @endif

                <x-button type="submit" size="sm">{{ __('app.common.search') }}</x-button>
                <x-button :href="route('customers.index')" variant="secondary" size="sm">{{ __('app.common.reset') }}</x-button>
            </form>
        </x-card>

        @if (auth()->user()->canDelete() && ($trashedCount > 0 || $trashed))
            <div class="flex flex-wrap items-center gap-2">
                @if ($trashed)
                    <x-button :href="route('customers.index')" variant="secondary" size="sm">
                        {{ __('app.common.back_to_list') }}
                    </x-button>
                @else
                    <x-button :href="route('customers.index', ['trashed' => 1])" variant="secondary" size="sm">
                        {{ __('app.common.recycle_bin', ['count' => $trashedCount]) }}
                    </x-button>
                @endif
            </div>
        @endif

        <x-card
            :title="$trashed ? __('app.common.deleted_records') : __('app.customer.title')"
            :description="__('app.common.found', ['count' => number_format($customers->total())])"
        >
            @if ($customers->isEmpty())
                <x-empty-state
                    :title="filled($filters['search'] ?? null) ? __('app.customer.none_match') : __('app.customer.none_yet')"
                    :description="filled($filters['search'] ?? null) ? __('app.customer.none_match_sub') : __('app.customer.none_yet_sub')"
                >
                    @unless ($trashed)
                        <x-button :href="route('customers.create')" size="sm">{{ __('app.customer.add') }}</x-button>
                    @endunless
                </x-empty-state>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-line text-sm">
                        <thead class="bg-surface-sunken text-left text-xs font-semibold uppercase tracking-wider text-ink-muted">
                            <tr>
                                <th scope="col" class="px-4 py-2.5">{{ __('app.customer.one') }}</th>
                                <th scope="col" class="px-4 py-2.5">{{ __('app.customer.email') }}</th>
                                <th scope="col" class="px-4 py-2.5 text-right">{{ __('app.sale.title') }}</th>
                                <th scope="col" class="px-4 py-2.5 text-right">{{ __('app.customer.total_spent') }}</th>
                                <th scope="col" class="px-4 py-2.5">{{ __('app.common.status') }}</th>
                                <th scope="col" class="px-4 py-2.5 text-right"><span class="sr-only">{{ __('app.common.actions') }}</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($customers as $index => $customer)
                                <tr class="animate-fade stagger row-hover" style="--d: {{ $index * 35 }}ms">
                                    <td class="px-4 py-3">
                                        <a href="{{ route('customers.show', $customer) }}" class="font-medium text-ink hover:text-brand-600 dark:hover:text-brand-400">
                                            {{ $customer->name }}
                                        </a>
                                        @if (filled($customer->phone))
                                            <p class="text-xs text-ink-muted">{{ $customer->phone }}</p>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-ink-muted">
                                        @if (filled($customer->email))
                                            <a href="mailto:{{ $customer->email }}" class="text-xs text-brand-600 dark:text-brand-400 hover:text-brand-700 dark:hover:text-brand-300">
                                                {{ $customer->email }}
                                            </a>
                                        @else
                                            <span class="text-xs">—</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right tabular-nums text-ink-muted">
                                        @qty($customer->completed_sales_count ?? 0)
                                    </td>
                                    <td class="px-4 py-3 text-right font-medium tabular-nums text-ink">
                                        @money($customer->spent_total ?? 0)
                                    </td>
                                    <td class="px-4 py-3">
                                        @if ($customer->is_active)
                                            <x-badge classes="bg-emerald-50 dark:bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 ring-emerald-600/20 dark:ring-emerald-400/30">{{ __('app.common.active') }}</x-badge>
                                        @else
                                            <x-badge classes="bg-surface-sunken text-ink-muted ring-line">{{ __('app.common.inactive') }}</x-badge>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center justify-end gap-1">
                                            @if ($trashed)
                                                @if (auth()->user()->canDelete())
                                                    <form method="POST" action="{{ route('customers.restore', $customer->id) }}">
                                                        @csrf
                                                        <x-button type="submit" variant="secondary" size="sm">{{ __('app.common.restore') }}</x-button>
                                                    </form>
                                                @endif
                                            @else
                                                <x-button :href="route('customers.edit', $customer)" variant="ghost" size="sm">{{ __('app.common.edit') }}</x-button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($customers->hasPages())
                    <div class="border-t border-line px-4 py-3">
                        {{ $customers->links() }}
                    </div>
                @endif
            @endif
        </x-card>
    </div>
</x-app-layout>
