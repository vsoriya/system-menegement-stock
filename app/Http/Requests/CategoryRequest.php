<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->canManageCatalog();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('categories', 'name')->ignore($this->route('category')),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    /**
     * Payload ready to be persisted.
     *
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return $this->safe()->only(['name', 'description', 'is_active']);
    }
}
