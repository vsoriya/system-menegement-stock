<?php

namespace App\Http\Requests;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ReceivePurchaseOrderRequest extends FormRequest
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
            // Keyed by purchase order line id.
            'receipts' => ['required', 'array'],
            'receipts.*' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * A delivery may not exceed what is still outstanding, and submitting an
     * all-zero sheet is treated as a mistake rather than a silent no-op.
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

                $lines = $this->order()->lines->keyBy(fn (PurchaseOrderLine $line) => $line->getKey());
                $total = 0;

                foreach ($this->quantities() as $lineId => $quantity) {
                    $line = $lines->get($lineId);

                    // Keys that do not belong to this order are ignored, exactly
                    // as the service does when it books the delivery in.
                    if ($line === null) {
                        continue;
                    }

                    if ($quantity > $line->outstanding) {
                        $validator->errors()->add("receipts.{$lineId}", __('app.po.receive_too_many', [
                            'count' => $line->outstanding,
                        ]));

                        continue;
                    }

                    $total += $quantity;
                }

                if ($total < 1) {
                    $validator->errors()->add('receipts', __('app.po.nothing_to_receive'));
                }
            },
        ];
    }

    /**
     * Quantities being received, keyed by line id, with blanks dropped.
     *
     * @return array<int, int>
     */
    public function quantities(): array
    {
        return collect($this->safe()->array('receipts'))
            ->map(fn ($quantity): int => (int) $quantity)
            ->filter(fn (int $quantity): bool => $quantity > 0)
            ->mapWithKeys(fn (int $quantity, $lineId): array => [(int) $lineId => $quantity])
            ->all();
    }

    protected function order(): PurchaseOrder
    {
        /** @var PurchaseOrder $order */
        $order = $this->route('purchase_order');

        return $order->loadMissing('lines');
    }
}
