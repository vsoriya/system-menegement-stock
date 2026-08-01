<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaleRequest extends FormRequest
{
    /**
     * Every signed in user can sell, staff included. Serving customers is the
     * whole point of the till, so it is not restricted to managers.
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
            'items' => ['required', 'array', 'min:1'],

            // exists() queries the table directly and ignores model scopes, so
            // the deleted and inactive checks have to be spelled out or a
            // withdrawn product could still be sold.
            'items.*.product_id' => [
                'required',
                'integer',
                Rule::exists('products', 'id')->whereNull('deleted_at')->where('is_active', true),
            ],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0', 'max:99999999'],

            'customer_id' => [
                'nullable',
                'integer',
                Rule::exists('customers', 'id')->whereNull('deleted_at'),
            ],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'discount' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'paid' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.required' => __('app.pos.cart_empty_error'),
            'items.min' => __('app.pos.cart_empty_error'),
        ];
    }

    /**
     * The basket, as SaleService expects it.
     *
     * @return array<int, array<string, mixed>>
     */
    public function items(): array
    {
        /** @var array<int, array<string, mixed>> $items */
        $items = $this->validated()['items'] ?? [];

        // Re-indexed, because the form posts whatever indexes the cart happened
        // to be using after rows were removed.
        return array_values($items);
    }

    /**
     * @return array<string, mixed>
     */
    public function options(): array
    {
        $validated = $this->validated();

        return [
            'customer_id' => $validated['customer_id'] ?? null,
            'payment_method' => PaymentMethod::from((string) $validated['payment_method']),
            'discount' => (float) ($validated['discount'] ?? 0),
            // Left null for card and transfer, where the service settles the
            // exact amount itself.
            'paid' => isset($validated['paid']) ? (float) $validated['paid'] : null,
            'note' => $validated['note'] ?? null,
        ];
    }
}
