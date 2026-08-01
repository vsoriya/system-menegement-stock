<?php

namespace App\Services;

use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PurchaseOrderService
{
    public function __construct(private readonly StockService $stock) {}

    /**
     * Raise a new draft order.
     *
     * @param  array<string, mixed>  $attributes
     * @param  list<array<string, mixed>>  $lines
     */
    public function create(array $attributes, array $lines, User $user): PurchaseOrder
    {
        return DB::transaction(function () use ($attributes, $lines, $user): PurchaseOrder {
            $order = PurchaseOrder::query()->create([
                ...$attributes,
                'number' => PurchaseOrder::nextNumber(),
                'created_by' => $user->getKey(),
                'status' => PurchaseOrderStatus::Draft,
            ]);

            $this->writeLines($order, $lines);

            return $order;
        });
    }

    /**
     * Replace the contents of a draft order.
     *
     * Lines are rewritten wholesale rather than diffed. A draft has no receipts
     * against it, so nothing is lost.
     *
     * @param  array<string, mixed>  $attributes
     * @param  list<array<string, mixed>>  $lines
     */
    public function update(PurchaseOrder $order, array $attributes, array $lines): PurchaseOrder
    {
        return DB::transaction(function () use ($order, $attributes, $lines): PurchaseOrder {
            $order->update($attributes);

            $order->lines()->delete();
            $this->writeLines($order, $lines);

            return $order;
        });
    }

    /**
     * Sign the order off so it can be received. Lines become read only.
     */
    public function approve(PurchaseOrder $order, User $user): void
    {
        $order->forceFill([
            'status' => PurchaseOrderStatus::Approved,
            'approved_by' => $user->getKey(),
            'approved_at' => now(),
        ])->save();
    }

    /**
     * Stock already received stays in the warehouse, because it physically
     * arrived. Cancelling only stops anything further from being booked in.
     */
    public function cancel(PurchaseOrder $order): void
    {
        $order->forceFill([
            'status' => PurchaseOrderStatus::Cancelled,
        ])->save();
    }

    /**
     * Book a delivery into stock, in full or in part.
     *
     * @param  array<int, int>  $quantities  received quantity keyed by line id
     * @return int number of products that entered stock
     */
    public function receive(PurchaseOrder $order, array $quantities, ?string $note, User $user): int
    {
        return DB::transaction(function () use ($order, $quantities, $note, $user): int {
            $order->load('lines.product');
            $reference = __('app.po.receive_reference', ['number' => $order->number]);
            $received = 0;

            foreach ($order->lines as $line) {
                $quantity = min(
                    (int) ($quantities[$line->getKey()] ?? 0),
                    $line->outstanding,
                );

                if ($quantity < 1) {
                    continue;
                }

                // A product deleted after the order was raised cannot take
                // stock, so its line is left outstanding instead.
                if ($line->product === null || $line->product->trashed()) {
                    continue;
                }

                $this->stock->stockIn($line->product, $quantity, [
                    'unit_cost' => $line->unit_cost,
                    'reference' => $reference,
                    'note' => $note,
                ], $user);

                $line->forceFill([
                    'quantity_received' => $line->quantity_received + $quantity,
                ])->save();

                $received++;
            }

            // Only close the order once every line is satisfied, so partial
            // deliveries leave it open for the next drop.
            $order->load('lines');

            if ($order->is_fully_received) {
                $order->forceFill([
                    'status' => PurchaseOrderStatus::Received,
                    'received_at' => now(),
                ])->save();
            }

            return $received;
        });
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    protected function writeLines(PurchaseOrder $order, array $lines): void
    {
        foreach ($lines as $line) {
            $order->lines()->create([
                'product_id' => $line['product_id'],
                'quantity_ordered' => $line['quantity_ordered'],
                'unit_cost' => $line['unit_cost'],
                'quantity_received' => 0,
            ]);
        }

        $order->setRelation('lines', $order->lines()->get());
    }
}
