@use('App\Enums\StockTakeStatus')

@php
    $canManage = auth()->user()->canManageCatalog();
    $canDelete = auth()->user()->canDelete();
    $editable = $take->status->isEditable() && $canManage;
    $isPosted = $take->status === StockTakeStatus::Posted;
    $progress = $totalLines > 0 ? round(($countedLines / $totalLines) * 100) : 0;
@endphp

<x-app-layout :title="$take->reference">
    <x-slot:actions>
        <x-button :href="route('stock-takes.index')" variant="secondary" size="sm">{{ __('app.common.back') }}</x-button>
    </x-slot:actions>

    <div class="space-y-5">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-stat-card :label="__('app.common.status')" :value="$take->status->label()" :hint="$take->reference" />

            <x-stat-card
                :label="__('app.stocktake.lines')"
                :count="$totalLines"
                :hint="$take->category?->name ?? __('app.stocktake.scope_all')"
            />

            <x-stat-card
                :label="__('app.stocktake.counted')"
                :count="$countedLines"
                :hint="$progress.'%'"
                :tone="$countedLines >= $totalLines ? 'success' : 'default'"
            />

            <x-stat-card
                :label="__('app.stocktake.variance')"
                :count="$varianceLines"
                :hint="__('app.stocktake.variance_summary', ['count' => number_format($varianceLines)])"
                :tone="$varianceLines > 0 ? 'warning' : 'success'"
            />
        </div>

        <x-card :title="__('app.common.details')">
            <dl class="grid grid-cols-1 divide-y divide-line text-sm sm:grid-cols-2 sm:divide-y-0">
                <div class="flex justify-between gap-4 px-4 py-3">
                    <dt class="text-ink-muted">{{ __('app.stocktake.counted_at') }}</dt>
                    <dd class="text-right text-ink">
                        <time datetime="{{ $take->counted_at->toDateString() }}">{{ $take->counted_at->format('d M Y') }}</time>
                    </dd>
                </div>

                <div class="flex justify-between gap-4 px-4 py-3">
                    <dt class="text-ink-muted">{{ __('app.stocktake.scope') }}</dt>
                    <dd class="text-right text-ink">{{ $take->category?->name ?? __('app.stocktake.scope_all') }}</dd>
                </div>

                <div class="flex justify-between gap-4 px-4 py-3">
                    <dt class="text-ink-muted">{{ __('app.common.created') }}</dt>
                    <dd class="text-right text-ink">
                        {{ $take->creator?->name ?? __('app.movement.system') }}
                        <p class="text-xs text-ink-muted">{{ $take->created_at->format('d M Y H:i') }}</p>
                    </dd>
                </div>

                @if ($take->posted_at)
                    <div class="flex justify-between gap-4 px-4 py-3">
                        <dt class="text-ink-muted">{{ __('app.stocktake.posted') }}</dt>
                        <dd class="text-right text-ink">
                            <time datetime="{{ $take->posted_at->toIso8601String() }}">
                                {{ $take->posted_at->format('d M Y H:i') }}
                            </time>
                        </dd>
                    </div>
                @endif
            </dl>

            @if (filled($take->notes))
                <div class="border-t border-line px-4 py-3">
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-ink-muted">{{ __('app.common.notes') }}</h3>
                    <p class="mt-1.5 text-sm whitespace-pre-line text-ink">{{ $take->notes }}</p>
                </div>
            @endif

            @if (($canManage && $take->status->isEditable()) || ($canDelete && ! $isPosted))
                <div class="border-t border-line px-4 py-3">
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-ink-muted">
                        {{ __('app.common.danger_zone') }}
                    </h3>

                    <div class="mt-2 flex flex-wrap gap-2">
                        @if ($canManage && $take->status->isEditable())
                            <form
                                method="POST"
                                action="{{ route('stock-takes.cancel', $take) }}"
                                onsubmit="return confirm(@js(__('app.stocktake.confirm_cancel')));"
                            >
                                @csrf
                                <x-button type="submit" variant="secondary" size="sm">{{ __('app.stocktake.cancel_count') }}</x-button>
                            </form>
                        @endif

                        @if ($canDelete && ! $isPosted)
                            <form
                                method="POST"
                                action="{{ route('stock-takes.destroy', $take) }}"
                                onsubmit="return confirm(@js(__('app.common.confirm_delete', ['name' => $take->reference])));"
                            >
                                @csrf
                                @method('DELETE')
                                <x-button type="submit" variant="danger" size="sm">{{ __('app.common.delete') }}</x-button>
                            </form>
                        @endif
                    </div>
                </div>
            @endif
        </x-card>

        <x-card :title="__('app.stocktake.lines')" :description="__('app.stocktake.lines_sub')">
            @if ($lines->isEmpty())
                <x-empty-state
                    :title="__('app.movement.no_active_products')"
                    :description="__('app.movement.no_active_products_sub')"
                />
            @else
                {{--
                    The sheet is paginated, so the page links live inside the
                    form. Typed counts are only persisted on submit, so leaving
                    the page with unsaved edits is confirmed first.
                --}}
                <form
                    method="POST"
                    action="{{ route('stock-takes.update', $take) }}"
                    x-data="{ dirty: false }"
                    x-on:input="dirty = true"
                    x-on:submit="dirty = false"
                >
                    @csrf
                    @method('PUT')

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-line text-sm">
                            <thead class="bg-surface-sunken text-left text-xs font-semibold uppercase tracking-wider text-ink-muted">
                                <tr>
                                    <th scope="col" class="px-4 py-2.5">{{ __('app.product.one') }}</th>
                                    <th scope="col" class="px-4 py-2.5 text-right">{{ __('app.stocktake.expected') }}</th>
                                    <th scope="col" class="w-40 px-4 py-2.5 text-right">{{ __('app.stocktake.counted') }}</th>
                                    <th scope="col" class="px-4 py-2.5 text-right">{{ __('app.stocktake.variance') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-line">
                                @foreach ($lines as $index => $line)
                                    @php
                                        $field = 'counts.'.$line->id;
                                        $variance = $line->variance;
                                    @endphp
                                    <tr class="animate-fade stagger align-top" style="--d: {{ $index * 25 }}ms">
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

                                        <td class="px-4 py-3 text-right tabular-nums text-ink-muted">
                                            @qty($line->expected_quantity)
                                            <span class="text-xs">{{ $line->product?->unit }}</span>
                                        </td>

                                        <td class="px-4 py-3">
                                            <label class="sr-only" for="count-{{ $line->id }}">
                                                {{ $line->product?->name }} {{ __('app.stocktake.counted') }}
                                            </label>
                                            <input
                                                type="number"
                                                id="count-{{ $line->id }}"
                                                name="counts[{{ $line->id }}]"
                                                value="{{ old($field, $line->counted_quantity) }}"
                                                min="0"
                                                step="1"
                                                inputmode="numeric"
                                                @disabled(! $editable)
                                                @error($field) aria-invalid="true" aria-describedby="count-{{ $line->id }}-error" @enderror
                                                class="block w-full rounded-xl border bg-surface px-3 py-2 text-right text-sm tabular-nums text-ink shadow-soft transition hover:border-ink-subtle focus:border-brand-500 focus:ring-4 focus:ring-brand-500/15 focus:outline-none disabled:cursor-not-allowed disabled:bg-surface-sunken disabled:text-ink-muted @error($field) border-rose-400 dark:border-rose-500 @else border-line @enderror"
                                            >

                                            @error($field)
                                                <p id="count-{{ $line->id }}-error" class="mt-1 text-xs font-medium text-rose-600 dark:text-rose-400">
                                                    {{ $message }}
                                                </p>
                                            @enderror
                                        </td>

                                        <td class="px-4 py-3 text-right tabular-nums">
                                            @if ($variance === null)
                                                <span class="text-xs text-ink-subtle">{{ __('app.stocktake.not_counted') }}</span>
                                            @elseif ($variance === 0)
                                                <x-badge classes="bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-500/15 dark:text-emerald-300 dark:ring-emerald-400/30">
                                                    {{ __('app.stocktake.no_variance') }}
                                                </x-badge>
                                            @else
                                                <span class="font-semibold {{ $variance > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                                                    {{ $variance > 0 ? '+' : '' }}@qty($variance)
                                                </span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @if ($lines->hasPages())
                        <div
                            class="border-t border-line px-4 py-3"
                            x-on:click="if (dirty && ! window.confirm(@js(__('app.stocktake.unsaved_warning')))) $event.preventDefault()"
                        >
                            {{ $lines->links() }}
                        </div>
                    @endif

                    @if ($editable)
                        <div class="flex flex-wrap items-center justify-end gap-2 border-t border-line bg-surface-sunken px-4 py-3 sm:px-5">
                            <p class="mr-auto text-xs text-ink-muted">
                                {{ __('app.stocktake.lines_counted', [
                                    'counted' => number_format($countedLines),
                                    'total' => number_format($totalLines),
                                ]) }}
                            </p>

                            {{-- Listed first so pressing Enter in the sheet saves rather than posts. --}}
                            <x-button type="submit" name="action" value="save" variant="secondary">
                                {{ __('app.common.save') }}
                            </x-button>

                            <x-button
                                type="submit"
                                name="action"
                                value="post"
                                onclick="return confirm(@js(__('app.stocktake.post_confirm')));"
                            >
                                {{ __('app.stocktake.post') }}
                            </x-button>
                        </div>
                    @endif
                </form>
            @endif
        </x-card>
    </div>
</x-app-layout>
