<x-app-layout :title="__('app.customer.edit', ['name' => $customer->name])">
    <div class="mx-auto max-w-3xl space-y-5">
        <x-card :title="__('app.customer.edit', ['name' => $customer->name])">
            <form method="POST" action="{{ route('customers.update', $customer) }}">
                @csrf
                @method('PUT')

                @include('customers.partials.form')

                <div class="flex items-center justify-end gap-2 border-t border-line bg-surface-sunken px-4 py-3 sm:px-5">
                    <x-button :href="route('customers.show', $customer)" variant="secondary">{{ __('app.common.cancel') }}</x-button>
                    <x-button type="submit">{{ __('app.common.save') }}</x-button>
                </div>
            </form>
        </x-card>

        @if (auth()->user()->canDelete())
            <x-card :title="__('app.common.danger_zone')" :description="__('app.customer.danger_sub')">
                <div class="flex flex-wrap items-center justify-between gap-3 p-4 sm:p-5">
                    <p class="text-sm text-ink-muted">{{ __('app.customer.danger_text') }}</p>
                    <form
                        method="POST"
                        action="{{ route('customers.destroy', $customer) }}"
                        onsubmit="return confirm(@js(__('app.common.confirm_delete', ['name' => $customer->name])));"
                    >
                        @csrf
                        @method('DELETE')
                        <x-button type="submit" variant="danger">{{ __('app.common.delete') }}</x-button>
                    </form>
                </div>
            </x-card>
        @endif
    </div>
</x-app-layout>
