<?php

namespace App\Http\Requests;

use App\Enums\MovementType;
use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StockMovementRequest extends FormRequest
{
    /**
     * Any signed in user may move stock in or out. An adjustment overwrites the
     * balance rather than shifting it, so it is limited to managers and admins.
     *
     * The form does not offer the option to anyone else, so reaching this by
     * posting the value directly is tampering, and a 403 is the honest answer.
     */
    public function authorize(): bool
    {
        if ($this->user() === null) {
            return false;
        }

        if ($this->type() === MovementType::Adjustment) {
            return $this->user()->canAdjustStock();
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'product_id' => ['required', Rule::exists('products', 'id')],
            'type' => ['required', Rule::enum(MovementType::class)],
            'quantity' => [
                'required',
                'integer',
                // An adjustment sets the counted quantity, which may be zero.
                $this->type() === MovementType::Adjustment ? 'min:0' : 'min:1',
                'max:1000000',
            ],
            'unit_cost' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'reference' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'product_id' => __('app.movement.one'),
            'unit_cost' => __('app.movement.unit_cost'),
        ];
    }

    /**
     * Reject a stock out that would push the product below zero, so the user
     * gets a field error instead of an exception page.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty() || $this->type() !== MovementType::Out) {
                    return;
                }

                $product = Product::query()->find($this->input('product_id'));

                if ($product === null) {
                    return;
                }

                $requested = (int) $this->input('quantity');

                if ($requested > $product->quantity) {
                    $validator->errors()->add('quantity', __('app.movement.insufficient', [
                        'count' => $product->quantity,
                        'unit' => $product->unit,
                        'name' => $product->name,
                    ]));
                }
            },
        ];
    }

    public function type(): ?MovementType
    {
        return MovementType::tryFrom((string) $this->input('type'));
    }

    /**
     * Extra details stored alongside the movement.
     *
     * @return array<string, mixed>
     */
    public function meta(): array
    {
        return [
            'unit_cost' => $this->input('unit_cost'),
            'reference' => $this->input('reference'),
            'note' => $this->input('note'),
        ];
    }
}
