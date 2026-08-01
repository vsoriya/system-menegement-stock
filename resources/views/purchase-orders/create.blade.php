<x-app-layout :title="__('app.po.add')">
    <div class="mx-auto max-w-5xl">
        <x-card :title="__('app.po.add')" :description="__('app.po.new_sub')">
            <x-slot:cardActions>
                <span class="font-mono text-xs text-ink-muted">{{ $nextNumber }}</span>
            </x-slot:cardActions>

            @if ($suppliers === [])
                <x-empty-state
                    :title="__('app.supplier.none_yet')"
                    :description="__('app.supplier.none_yet_sub')"
                >
                    <x-button :href="route('suppliers.create')" size="sm">{{ __('app.supplier.add') }}</x-button>
                </x-empty-state>
            @elseif ($products->isEmpty())
                <x-empty-state
                    :title="__('app.movement.no_active_products')"
                    :description="__('app.movement.no_active_products_sub')"
                >
                    <x-button :href="route('products.create')" size="sm">{{ __('app.product.add') }}</x-button>
                </x-empty-state>
            @else
                <form method="POST" action="{{ route('purchase-orders.store') }}">
                    @csrf

                    @include('purchase-orders.partials.form')

                    <div class="flex items-center justify-end gap-2 border-t border-line bg-surface-sunken px-4 py-3 sm:px-5">
                        <x-button :href="route('purchase-orders.index')" variant="secondary">{{ __('app.common.cancel') }}</x-button>
                        <x-button type="submit">{{ __('app.common.create') }}</x-button>
                    </div>
                </form>
            @endif
        </x-card>
    </div>
</x-app-layout>
