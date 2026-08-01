{{-- Shared user form. @param \App\Models\User $user  @param array $roles  @param bool $creating --}}

<div class="grid grid-cols-1 gap-5 p-4 sm:p-5 lg:grid-cols-2">
    <x-field
        :label="__('app.user.name')"
        name="name"
        :value="$user->name"
        required
    />

    <x-field
        :label="__('app.user.email')"
        name="email"
        type="email"
        :value="$user->email"
        autocomplete="off"
        required
    />

    <x-field
        :label="__('app.user.role')"
        name="role"
        type="select"
        :value="$user->role?->value"
        :options="$roles"
        :hint="__('app.user.role_hint')"
        required
    />

    <x-field
        :label="__('app.common.status')"
        name="is_active"
        type="checkbox"
        :value="$user->is_active ?? true"
        :hint="__('app.user.active_hint')"
    />

    <x-field
        :label="$creating ? __('app.user.password') : __('app.auth.new_password')"
        name="password"
        type="password"
        :hint="$creating ? __('app.user.password_hint') : __('app.user.password_edit_hint')"
        autocomplete="new-password"
        :required="$creating"
    />

    <x-field
        :label="__('app.user.confirm_password')"
        name="password_confirmation"
        type="password"
        autocomplete="new-password"
        :required="$creating"
    />
</div>
