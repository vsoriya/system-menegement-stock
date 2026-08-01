<x-app-layout :title="__('app.user.edit', ['name' => $user->name])">
    <div class="mx-auto max-w-3xl space-y-5">
        <x-card :title="__('app.user.edit', ['name' => $user->name])" :description="$user->email">
            <form method="POST" action="{{ route('users.update', $user) }}">
                @csrf
                @method('PUT')

                @include('users.partials.form', ['creating' => false])

                <div class="flex items-center justify-end gap-2 border-t border-line bg-surface-sunken px-4 py-3 sm:px-5">
                    <x-button :href="route('users.index')" variant="secondary">{{ __('app.common.cancel') }}</x-button>
                    <x-button type="submit">{{ __('app.common.save') }}</x-button>
                </div>
            </form>
        </x-card>

        @unless ($user->is(auth()->user()))
            <x-card :title="__('app.common.danger_zone')" :description="__('app.user.danger_sub')">
                <div class="flex flex-wrap items-center justify-between gap-3 p-4 sm:p-5">
                    <p class="text-sm text-ink-muted">{{ __('app.user.danger_sub') }}</p>
                    <form
                        method="POST"
                        action="{{ route('users.destroy', $user) }}"
                        onsubmit="return confirm(@js(__('app.common.confirm_delete', ['name' => $user->name])));"
                    >
                        @csrf
                        @method('DELETE')
                        <x-button type="submit" variant="danger">{{ __('app.common.delete') }}</x-button>
                    </form>
                </div>
            </x-card>
        @endunless
    </div>
</x-app-layout>
