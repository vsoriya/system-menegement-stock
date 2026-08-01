<x-app-layout :title="__('app.user.add')">
    <div class="mx-auto max-w-3xl">
        <x-card :title="__('app.user.new')" :description="__('app.user.new_sub')">
            <form method="POST" action="{{ route('users.store') }}">
                @csrf

                @include('users.partials.form', ['creating' => true])

                <div class="flex items-center justify-end gap-2 border-t border-line bg-surface-sunken px-4 py-3 sm:px-5">
                    <x-button :href="route('users.index')" variant="secondary">{{ __('app.common.cancel') }}</x-button>
                    <x-button type="submit">{{ __('app.common.create') }}</x-button>
                </div>
            </form>
        </x-card>
    </div>
</x-app-layout>
