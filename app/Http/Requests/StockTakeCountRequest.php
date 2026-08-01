<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StockTakeCountRequest extends FormRequest
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
            // Which button was pressed: keep counting, or write the differences.
            'action' => ['required', 'in:save,post'],

            // Keyed by stock take line id. A blank means "not counted yet".
            'counts' => ['nullable', 'array'],
            'counts.*' => ['nullable', 'integer', 'min:0', 'max:1000000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'counts.*' => __('app.stocktake.counted'),
        ];
    }

    public function shouldPost(): bool
    {
        return $this->input('action') === 'post';
    }

    /**
     * Counted quantities keyed by line id. Blanks are kept so clearing a value
     * resets the line back to "not counted".
     *
     * @return array<int, mixed>
     */
    public function counts(): array
    {
        return collect($this->safe()->array('counts'))
            ->mapWithKeys(fn ($value, $lineId): array => [(int) $lineId => $value])
            ->all();
    }
}
