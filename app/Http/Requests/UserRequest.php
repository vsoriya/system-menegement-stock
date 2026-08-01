<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => [
                'required',
                'email',
                'max:150',
                Rule::unique('users', 'email')->ignore($this->route('user')),
            ],
            'role' => ['required', Rule::enum(UserRole::class)],
            'is_active' => ['boolean'],
            'password' => [
                // Required when creating, optional when editing.
                $this->isCreating() ? 'required' : 'nullable',
                'confirmed',
                Password::min(8),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => __('app.user.name'),
            'email' => __('app.user.email'),
            'role' => __('app.user.role'),
            'password' => __('app.user.password'),
        ];
    }

    /**
     * Stop an administrator from locking themselves out by removing their own
     * admin rights or deactivating their own account.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $target = $this->route('user');

                if (! $target instanceof User || $target->getKey() !== $this->user()?->getKey()) {
                    return;
                }

                if ($this->input('role') !== UserRole::Admin->value) {
                    $validator->errors()->add('role', __('app.user.cannot_demote_self'));
                }

                if (! $this->boolean('is_active')) {
                    $validator->errors()->add('is_active', __('app.user.cannot_demote_self'));
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    public function isCreating(): bool
    {
        return $this->route('user') === null;
    }

    /**
     * Attributes to persist. The password is only included when one was given,
     * so editing a user without touching the field keeps the old password.
     *
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $payload = $this->safe()->only(['name', 'email', 'role', 'is_active']);

        if ($this->filled('password')) {
            // The User model casts 'password' => 'hashed'.
            $payload['password'] = $this->string('password')->toString();
        }

        return $payload;
    }
}
