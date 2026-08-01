<x-app-layout :title="__('app.stocktake.title')">
    <x-slot:actions>
        @if (auth()->user()->canManageCatalog())
            <x-button :href="route('stock-takes.create')" size="sm">{{ __('app.stocktake.add') }}</x-button>
        @endif
    </x-slot:actions>

    <div class="space-y-5">
        <x-card>
            <form method="GET" action="{{ route('stock-takes.index') }}" class="flex flex-wrap items-end gap-3 p-4">
                <x-field
                    :label="__('app.common.search')"
                    name="search"
                    :value="$filters['search'] ?? null"
                    :placeholder="__('app.stocktake.reference_placeholder')"
                    class="min-w-56 flex-1"
                />

                <x-field
                    :label="__('app.common.status')"
                    name="status"
                    type="select"
                    :value="$filters['status'] ?? null"
                    :placeholder="__('app.common.all')"
                    :options="$statuses"
                    class="w-44"
                />

                <x-button type="submit" size="sm">{{ __('app.common.search') }}</x-button>
                <x-button :href="route('stock-takes.index')" variant="secondary" size="sm">{{ __('app.common.reset') }}</x-button>

                <p class="ml-auto self-center text-xs text-ink-muted">
                    {{ __('app.common.found', ['count' => number_format($takes->total())]) }}
                </p>
            </form>
        </x-card>

        <x-card>
            @if ($takes->isEmpty())
                <x-empty-state :title="__('app.stocktake.none_yet')" :description="__('app.stocktake.none_yet_sub')">
                    @if (auth()->user()->canManageCatalog())
                        <x-button :href="route('stock-takes.create')" size="sm">{{ __('app.stocktake.add') }}</x-button>
                    @endif
                </x-empty-state>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-line text-sm">
                        <thead class="bg-surface-sunken text-left text-xs font-semibold uppercase tracking-wider text-ink-muted">
                            <tr>
                                <th scope="col" class="px-4 py-2.5">{{ __('app.stocktake.reference') }}</th>
                                <th scope="col" class="px-4 py-2.5">{{ __('app.stocktake.counted_at') }}</th>
                                <th scope="col" class="px-4 py-2.5">{{ __('app.stocktake.scope') }}</th>
                                <th scope="col" class="px-4 py-2.5">{{ __('app.stocktake.lines') }}</th>
                                <th scope="col" class="px-4 py-2.5 text-right">{{ __('app.stocktake.variance') }}</th>
                                <th scope="col" class="px-4 py-2.5">{{ __('app.common.status') }}</th>
                                <th scope="col" class="px-4 py-2.5 text-right">
                                    <span class="sr-only">{{ __('app.common.actions') }}</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($takes as $index => $take)
                                @php
                                    $total = (int) $take->lines_count;
                                    $counted = (int) $take->counted_count;
                                    $progress = $total > 0 ? round(($counted / $total) * 100) : 0;
                                @endphp
                                <tr class="animate-fade stagger row-hover" style="--d: {{ $index * 35 }}ms">
                                    <td class="px-4 py-3">
                                        <a
                                            href="{{ route('stock-takes.show', $take) }}"
                                            class="font-medium text-ink hover:text-brand-600 dark:hover:text-brand-400"
                                        >
                                            {{ $take->reference }}
                                        </a>
                                        <p class="text-xs text-ink-muted">
                                            {{ $take->creator?->name ?? __('app.movement.system') }}
                                        </p>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-ink-muted">
                                        <time datetime="{{ $take->counted_at->toDateString() }}">
                                            {{ $take->counted_at->format('d M Y') }}
                                        </time>
                                    </td>
                                    <td class="px-4 py-3 text-ink-muted">
                                        {{ $take->category?->name ?? __('app.stocktake.scope_all') }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <p class="text-xs text-ink-muted">
                                            {{ __('app.stocktake.lines_counted', [
                                                'counted' => number_format($counted),
                                                'total' => number_format($total),
                                            ]) }}
                                        </p>
                                        <div
                                            class="mt-1.5 h-1.5 w-28 overflow-hidden rounded-full bg-surface-sunken"
                                            role="progressbar"
                                            aria-valuenow="{{ $progress }}"
                                            aria-valuemin="0"
                                            aria-valuemax="100"
                                            aria-label="{{ __('app.stocktake.lines_counted', [
                                                'counted' => number_format($counted),
                                                'total' => number_format($total),
                                            ]) }}"
                                        >
                                            <span
                                                class="block h-full rounded-full bg-linear-to-r from-brand-400 to-brand-600"
                                                style="width: {{ $progress }}%"
                                            ></span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-right tabular-nums">
                                        @if ($take->variance_count > 0)
                                            <span class="font-medium text-amber-600 dark:text-amber-400">
                                                @qty($take->variance_count)
                                            </span>
                                        @else
                                            <span class="text-ink-muted">{{ __('app.stocktake.no_variance') }}</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        <x-badge :classes="$take->status->badgeClasses()">{{ $take->status->label() }}</x-badge>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center justify-end gap-1">
                                            <x-button :href="route('stock-takes.show', $take)" variant="ghost" size="sm">
                                                {{ $take->status->isEditable() && auth()->user()->canManageCatalog()
                                                    ? __('app.common.edit')
                                                    : __('app.common.view') }}
                                            </x-button>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($takes->hasPages())
                    <div class="border-t border-line px-4 py-3">
                        {{ $takes->links() }}
                    </div>
                @endif
            @endif
        </x-card>
    </div>
</x-app-layout>
