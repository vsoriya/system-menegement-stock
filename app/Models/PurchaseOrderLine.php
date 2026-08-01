<?php

namespace App\Models;

use Database\Factories\PurchaseOrderLineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'purchase_order_id',
    'product_id',
    'quantity_ordered',
    'quantity_received',
    'unit_cost',
])]
class PurchaseOrderLine extends Model
{
    /** @use HasFactory<PurchaseOrderLineFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity_ordered' => 'integer',
            'quantity_received' => 'integer',
            'unit_cost' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<PurchaseOrder, $this>
     */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    /**
     * Quantity still to be delivered.
     */
    protected function outstanding(): Attribute
    {
        return Attribute::get(fn (): int => max(0, $this->quantity_ordered - $this->quantity_received));
    }

    protected function lineTotal(): Attribute
    {
        return Attribute::get(fn (): float => round($this->quantity_ordered * (float) $this->unit_cost, 2));
    }
}
