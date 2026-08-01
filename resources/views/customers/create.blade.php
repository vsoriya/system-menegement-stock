<x-app-layout :title="__('app.customer.add')">
    <div class="mx-auto max-w-3xl">
        <x-card :title="__('app.customer.new')">
            <form method="POST" action="{{ route('customers.store') }}">
                @csrf

                {{-- Set when the cashier came from the till, so saving returns
                     there with this customer already selected. --}}
                @if (request()->filled('from_pos'))
                    <input type="hidden" name="from_pos" value="1">
                @endif

                @include('customers.partials.form')

                <div class="flex items-center justify-end gap-2 border-t border-line bg-surface-sunken px-4 py-3 sm:px-5">
                    <x-button
                        :href="request()->filled('from_pos') ? route('pos.index') : route('customers.index')"
                        variant="secondary"
                    >{{ __('app.common.cancel') }}</x-button>
                    <x-button type="submit">{{ __('app.common.create') }}</x-button>
                </div>
            </form>
        </x-card>
    </div>
</x-app-layout>
