<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
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
        $rules = [
            'sku' => [
                'required',
                'string',
                'max:60',
                'regex:/^[A-Za-z0-9._-]+$/',
                Rule::unique('products', 'sku')->ignore($this->route('product')),
            ],
            'name' => ['required', 'string', 'max:180'],
            'barcode' => [
                'nullable',
                'string',
                'max:64',
                'regex:/^[A-Za-z0-9._-]+$/',
                Rule::unique('products', 'barcode')->ignore($this->route('product')),
            ],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_image' => ['boolean'],
            'description' => ['nullable', 'string', 'max:5000'],
            'category_id' => ['nullable', Rule::exists('categories', 'id')],
            'supplier_id' => ['nullable', Rule::exists('suppliers', 'id')],
            'unit' => ['required', 'string', 'max:20'],
            'cost_price' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'sale_price' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'reorder_level' => ['required', 'integer', 'min:0', 'max:1000000'],
            'is_active' => ['boolean'],
        ];

        // Quantity is only writable when creating, as opening stock. After that
        // it can only change through a recorded stock movement.
        if ($this->isCreating()) {
            $rules['quantity'] = ['required', 'integer', 'min:0', 'max:1000000'];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'sku.regex' => __('app.product.sku_hint'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'sku' => __('app.product.sku'),
            'category_id' => __('app.product.category'),
            'supplier_id' => __('app.product.supplier'),
            'quantity' => __('app.product.opening_qty'),
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'sku' => is_string($this->input('sku')) ? trim($this->input('sku')) : $this->input('sku'),
            // Empty string would trip the unique rule against other blanks.
            'barcode' => $this->filled('barcode') ? trim((string) $this->input('barcode')) : null,
            'is_active' => $this->boolean('is_active'),
            'category_id' => $this->filled('category_id') ? $this->input('category_id') : null,
            'supplier_id' => $this->filled('supplier_id') ? $this->input('supplier_id') : null,
        ]);
    }

    /**
     * Persist the uploaded image and return its relative path, or null when no
     * new file was supplied.
     */
    public function storeImage(): ?string
    {
        if (! $this->hasFile('image')) {
            return null;
        }

        return $this->file('image')->store('products', config('filesystems.images'));
    }

    public function isCreating(): bool
    {
        return $this->route('product') === null;
    }

    /**
     * Attributes safe to write to the product record.
     *
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return $this->safe()->only([
            'sku',
            'barcode',
            'name',
            'description',
            'category_id',
            'supplier_id',
            'unit',
            'cost_price',
            'sale_price',
            'reorder_level',
            'is_active',
        ]);
    }

    /**
     * Opening stock supplied when the product is created.
     */
    public function openingQuantity(): int
    {
        return (int) $this->input('quantity', 0);
    }
}
