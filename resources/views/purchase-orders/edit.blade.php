<x-app-layout :title="__('app.po.edit', ['number' => $order->number])">
    <div class="mx-auto max-w-5xl">
        <x-card :title="__('app.po.edit', ['number' => $order->number])" :description="__('app.po.new_sub')">
            <x-slot:cardActions>
                <x-badge :classes="$order->status->badgeClasses()">{{ $order->status->label() }}</x-badge>
            </x-slot:cardActions>

            <form method="POST" action="{{ route('purchase-orders.update', $order) }}">
                @csrf
                @method('PUT')

                @include('purchase-orders.partials.form')

                <div class="flex items-center justify-end gap-2 border-t border-line bg-surface-sunken px-4 py-3 sm:px-5">
                    <x-button :href="route('purchase-orders.show', $order)" variant="secondary">{{ __('app.common.cancel') }}</x-button>
                    <x-button type="submit">{{ __('app.common.save') }}</x-button>
                </div>
            </form>
        </x-card>
    </div>
</x-app-layout>
