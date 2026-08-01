<x-app-layout :title="__('app.profile.title')">
    <div class="mx-auto max-w-2xl space-y-5">
        <!-- Name and sign in email -->
        <x-card :title="__('app.profile.info_title')" :description="__('app.profile.info_sub')">
            <x-slot:cardActions>
                <x-badge classes="bg-surface-sunken text-ink-muted ring-line">{{ $user->role->label() }}</x-badge>
            </x-slot:cardActions>

            <form
                method="POST"
                action="{{ route('profile.update') }}"
                x-data="{ email: @js(old('email', $user->email)), original: @js($user->email) }"
            >
                @csrf
                @method('PUT')

                <div class="grid grid-cols-1 gap-5 p-4 sm:p-5">
                    <x-field
                        :label="__('app.user.name')"
                        name="name"
                        :value="$user->name"
                        autocomplete="name"
                        required
                    />

                    <x-field
                        :label="__('app.user.email')"
                        name="email"
                        type="email"
                        :value="$user->email"
                        autocomplete="email"
                        :hint="__('app.profile.email_locked_hint')"
                        x-model="email"
                        required
                    />

                    {{--
                        Only asked for when the email is actually being changed,
                        which mirrors the rule in ProfileRequest.
                    --}}
                    <div x-show="email.trim().toLowerCase() !== original.trim().toLowerCase()" x-cloak>
                        <x-field
                            :label="__('app.profile.current_password')"
                            name="email_current_password"
                            type="password"
                            autocomplete="current-password"
                            :hint="__('app.profile.email_needs_password')"
                        />
                    </div>

                    <p class="text-xs text-ink-subtle">{{ __('app.profile.role_note') }}</p>
                </div>

                <div class="flex items-center justify-end gap-2 border-t border-line bg-surface-sunken px-4 py-3 sm:px-5">
                    <x-button type="submit">{{ __('app.common.save') }}</x-button>
                </div>
            </form>
        </x-card>

        <!-- Password -->
        <x-card :title="__('app.profile.password_title')" :description="__('app.profile.password_sub')">
            <form method="POST" action="{{ route('profile.password') }}">
                @csrf
                @method('PUT')

                <div class="grid grid-cols-1 gap-5 p-4 sm:p-5">
                    <x-field
                        :label="__('app.profile.current_password')"
                        name="current_password"
                        type="password"
                        autocomplete="current-password"
                        :hint="__('app.profile.current_password_hint')"
                        required
                    />

                    <x-field
                        :label="__('app.auth.new_password')"
                        name="password"
                        type="password"
                        autocomplete="new-password"
                        :hint="__('app.user.password_hint')"
                        required
                    />

                    <x-field
                        :label="__('app.auth.confirm_password')"
                        name="password_confirmation"
                        type="password"
                        autocomplete="new-password"
                        required
                    />
                </div>

                <div class="flex items-center justify-end gap-2 border-t border-line bg-surface-sunken px-4 py-3 sm:px-5">
                    <x-button type="submit">{{ __('app.common.save') }}</x-button>
                </div>
            </form>
        </x-card>
    </div>
</x-app-layout>
