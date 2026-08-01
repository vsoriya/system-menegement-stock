<?php

namespace App\Models;

use Database\Factories\SaleLineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'sale_id',
    'product_id',
    'quantity',
    'unit_price',
    'unit_cost',
])]
class SaleLine extends Model
{
    /** @use HasFactory<SaleLineFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'unit_cost' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Sale, $this>
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /**
     * Includes soft deleted products so an old invoice still reads properly.
     *
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    protected function lineTotal(): Attribute
    {
        return Attribute::get(fn (): float => round($this->quantity * (float) $this->unit_price, 2));
    }

    /**
     * Profit on this line, using the cost captured when it was sold.
     */
    protected function lineProfit(): Attribute
    {
        return Attribute::get(fn (): float => round(
            $this->quantity * ((float) $this->unit_price - (float) $this->unit_cost),
            2,
        ));
    }
}
