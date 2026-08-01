<?php

namespace App\Http\Requests;

use App\Services\StockTakeService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StockTakeRequest extends FormRequest
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
            'reference' => ['required', 'string', 'max:120'],
            'counted_at' => ['required', 'date'],
            'scope' => ['required', 'in:all,category'],
            'category_id' => [
                'nullable',
                'required_if:scope,category',
                Rule::exists('categories', 'id')->whereNull('deleted_at'),
            ],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'reference' => __('app.stocktake.reference'),
            'counted_at' => __('app.stocktake.counted_at'),
            'scope' => __('app.stocktake.scope'),
            'category_id' => __('app.product.category'),
        ];
    }

    /**
     * A sheet with no products on it is useless, so say so before creating one.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                if (app(StockTakeService::class)->scopeSize($this->categoryId()) < 1) {
                    $validator->errors()->add(
                        $this->input('scope') === 'category' ? 'category_id' : 'scope',
                        __('app.movement.no_active_products'),
                    );
                }
            },
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'reference' => $this->safe()->string('reference')->toString(),
            'counted_at' => $this->safe()->string('counted_at')->toString(),
            'notes' => $this->safe()->string('notes')->toString() ?: null,
            'category_id' => $this->categoryId(),
        ];
    }

    /**
     * The category being counted, or null when counting everything.
     */
    protected function categoryId(): ?int
    {
        if ($this->input('scope') !== 'category') {
            return null;
        }

        $categoryId = $this->integer('category_id');

        return $categoryId > 0 ? $categoryId : null;
    }
}
