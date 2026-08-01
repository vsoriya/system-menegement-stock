<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ProfilePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // The current_password rule checks the value against the hash of
            // the signed in user, so a stolen session cannot set a new password.
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', 'different:current_password', Password::min(8)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'current_password' => __('app.profile.current_password'),
            'password' => __('app.auth.new_password'),
        ];
    }

    public function newPassword(): string
    {
        return $this->string('password')->toString();
    }
}
