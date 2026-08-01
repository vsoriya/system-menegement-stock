<x-app-layout :title="__('app.po.receive_title', ['number' => $order->number])">
    <div class="mx-auto max-w-4xl">
        <x-card
            :title="__('app.po.receive_title', ['number' => $order->number])"
            :description="__('app.po.receive_sub')"
        >
            <x-slot:cardActions>
                <x-badge :classes="$order->status->badgeClasses()">{{ $order->status->label() }}</x-badge>
            </x-slot:cardActions>

            <form method="POST" action="{{ route('purchase-orders.receive.store', $order) }}">
                @csrf

                @error('receipts')
                    <p class="px-4 pt-4 text-xs font-medium text-rose-600 dark:text-rose-400 sm:px-5">{{ $message }}</p>
                @enderror

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-line text-sm">
                        <thead class="bg-surface-sunken text-left text-xs font-semibold uppercase tracking-wider text-ink-muted">
                            <tr>
                                <th scope="col" class="px-4 py-2.5">{{ __('app.po.product') }}</th>
                                <th scope="col" class="px-4 py-2.5 text-right">{{ __('app.po.quantity') }}</th>
                                <th scope="col" class="px-4 py-2.5 text-right">{{ __('app.po.received_qty') }}</th>
                                <th scope="col" class="px-4 py-2.5 text-right">{{ __('app.po.outstanding') }}</th>
                                <th scope="col" class="w-40 px-4 py-2.5 text-right">{{ __('app.po.receive') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($lines as $index => $line)
                                @php
                                    $field = 'receipts.'.$line->id;
                                @endphp
                                <tr class="animate-fade stagger align-top" style="--d: {{ $index * 35 }}ms">
                                    <td class="px-4 py-3">
                                        <p class="font-medium text-ink">{{ $line->product?->name ?? '—' }}</p>
                                        <p class="font-mono text-xs text-ink-muted">{{ $line->product?->sku }}</p>
                                    </td>
                                    <td class="px-4 py-3 text-right tabular-nums text-ink-muted">
                                        @qty($line->quantity_ordered)
                                    </td>
                                    <td class="px-4 py-3 text-right tabular-nums text-ink-muted">
                                        @qty($line->quantity_received)
                                    </td>
                                    <td class="px-4 py-3 text-right tabular-nums font-medium text-amber-600 dark:text-amber-400">
                                        @qty($line->outstanding)
                                    </td>
                                    <td class="px-4 py-3">
                                        <label class="sr-only" for="receive-{{ $line->id }}">
                                            {{ $line->product?->name }} {{ __('app.po.received_qty') }}
                                        </label>
                                        <input
                                            type="number"
                                            id="receive-{{ $line->id }}"
                                            name="receipts[{{ $line->id }}]"
                                            value="{{ old($field, $line->outstanding) }}"
                                            min="0"
                                            max="{{ $line->outstanding }}"
                                            step="1"
                                            @error($field) aria-invalid="true" aria-describedby="receive-{{ $line->id }}-error" @enderror
                                            class="block w-full rounded-xl border bg-surface px-3 py-2 text-right text-sm tabular-nums text-ink shadow-soft transition hover:border-ink-subtle focus:border-brand-500 focus:ring-4 focus:ring-brand-500/15 focus:outline-none @error($field) border-rose-400 dark:border-rose-500 @else border-line @enderror"
                                        >
                                        <p class="mt-1 text-right text-xs text-ink-muted">{{ $line->product?->unit }}</p>

                                        @error($field)
                                            <p id="receive-{{ $line->id }}-error" class="mt-1 text-xs font-medium text-rose-600 dark:text-rose-400">
                                                {{ $message }}
                                            </p>
                                        @enderror
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="border-t border-line p-4 sm:p-5">
                    <x-field
                        :label="__('app.common.notes')"
                        name="note"
                        type="textarea"
                        rows="2"
                        :hint="__('app.movement.reference_hint')"
                    />
                </div>

                <div class="flex items-center justify-end gap-2 border-t border-line bg-surface-sunken px-4 py-3 sm:px-5">
                    <x-button :href="route('purchase-orders.show', $order)" variant="secondary">{{ __('app.common.cancel') }}</x-button>
                    <x-button type="submit">{{ __('app.po.receive_now') }}</x-button>
                </div>
            </form>
        </x-card>
    </div>
</x-app-layout>
