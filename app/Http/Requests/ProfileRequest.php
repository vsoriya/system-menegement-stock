<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileRequest extends FormRequest
{
    /**
     * Never flash a submitted password back into the session. The base class
     * only covers its own default field names.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'email_current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Anyone signed in may edit their own details. There is no role check here
     * because the controller only ever writes to the authenticated user.
     */
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
            'name' => ['required', 'string', 'max:120'],
            'email' => [
                'required',
                'email',
                'max:150',
                Rule::unique('users', 'email')->ignore($this->user()),
            ],

            // Changing the address you sign in with is worth confirming, so the
            // current password is only demanded when the email actually moves.
            //
            // Named apart from the password form's own "current_password" field
            // so a failure on one form cannot show an error on the other.
            'email_current_password' => [
                Rule::when($this->isChangingEmail(), ['required', 'current_password']),
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
            'email_current_password' => __('app.profile.current_password'),
        ];
    }

    public function isChangingEmail(): bool
    {
        $submitted = mb_strtolower(trim((string) $this->input('email')));

        return $submitted !== '' && $submitted !== mb_strtolower((string) $this->user()?->email);
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return $this->safe()->only(['name', 'email']);
    }
}
