<?php

namespace App\Models;

use Database\Factories\StockTakeLineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'stock_take_id',
    'product_id',
    'expected_quantity',
    'counted_quantity',
])]
class StockTakeLine extends Model
{
    /** @use HasFactory<StockTakeLineFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expected_quantity' => 'integer',
            'counted_quantity' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<StockTake, $this>
     */
    public function stockTake(): BelongsTo
    {
        return $this->belongsTo(StockTake::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    /**
     * Counted minus expected, or null when the line has not been counted.
     */
    protected function variance(): Attribute
    {
        return Attribute::get(fn (): ?int => $this->counted_quantity === null
            ? null
            : $this->counted_quantity - $this->expected_quantity);
    }

    /**
     * Whether posting this line would actually change stock.
     */
    public function hasVariance(): bool
    {
        return $this->counted_quantity !== null
            && $this->counted_quantity !== $this->expected_quantity;
    }
}
