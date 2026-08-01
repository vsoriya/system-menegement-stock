<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PurchaseOrderRequest extends FormRequest
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
            'supplier_id' => [
                'required',
                Rule::exists('suppliers', 'id')->whereNull('deleted_at'),
            ],
            'ordered_at' => ['required', 'date'],
            'expected_at' => ['nullable', 'date', 'after_or_equal:ordered_at'],
            'notes' => ['nullable', 'string', 'max:2000'],

            'lines' => ['required', 'array', 'min:1', 'max:200'],

            // The line keys come from the browser, so they are never trusted as
            // database ids. Only the values below are read.
            'lines.*.product_id' => [
                'required',
                'distinct',
                Rule::exists('products', 'id')->whereNull('deleted_at'),
            ],
            'lines.*.quantity_ordered' => ['required', 'integer', 'min:1', 'max:1000000'],
            'lines.*.unit_cost' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
        ];
    }

    /**
     * An empty date input posts an empty string, which would be cast to "now"
     * on the way into a date column. Normalise the optional fields to null.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'expected_at' => $this->filled('expected_at') ? $this->input('expected_at') : null,
            'notes' => $this->filled('notes') ? $this->input('notes') : null,
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'supplier_id' => __('app.po.supplier'),
            'ordered_at' => __('app.po.ordered_at'),
            'expected_at' => __('app.po.expected_at'),
            'lines' => __('app.po.lines'),
            'lines.*.product_id' => __('app.po.product'),
            'lines.*.quantity_ordered' => __('app.po.quantity'),
            'lines.*.unit_cost' => __('app.po.unit_cost'),
        ];
    }

    /**
     * Order header values, without the lines.
     *
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return $this->safe()->only([
            'supplier_id',
            'ordered_at',
            'expected_at',
            'notes',
        ]);
    }

    /**
     * Order lines, re-indexed and normalised for writing.
     *
     * @return list<array<string, mixed>>
     */
    public function lines(): array
    {
        return collect($this->safe()->array('lines'))
            ->map(fn (array $line): array => [
                'product_id' => (int) $line['product_id'],
                'quantity_ordered' => (int) $line['quantity_ordered'],
                'unit_cost' => round((float) ($line['unit_cost'] ?? 0), 2),
            ])
            ->values()
            ->all();
    }
}
