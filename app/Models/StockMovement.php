<?php

namespace App\Models;

use App\Enums\MovementType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'product_id',
    'user_id',
    'type',
    'quantity_change',
    'quantity_before',
    'quantity_after',
    'unit_cost',
    'reference',
    'note',
])]
class StockMovement extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => MovementType::class,
            'quantity_change' => 'integer',
            'quantity_before' => 'integer',
            'quantity_after' => 'integer',
            'unit_cost' => 'decimal:2',
        ];
    }

    /**
     * Includes soft deleted products, so history stays readable after a
     * product is removed from the catalog.
     *
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Absolute quantity moved, without the direction sign.
     */
    protected function quantityMoved(): Attribute
    {
        return Attribute::get(fn (): int => abs($this->quantity_change));
    }

    /**
     * Total cost of the movement when a unit cost was recorded.
     */
    protected function totalCost(): Attribute
    {
        return Attribute::get(function (): ?float {
            if ($this->unit_cost === null) {
                return null;
            }

            return round(abs($this->quantity_change) * (float) $this->unit_cost, 2);
        });
    }

    #[Scope]
    protected function ofType(Builder $query, ?string $type): void
    {
        $query->when(filled($type), fn (Builder $query) => $query->where('type', $type));
    }

    #[Scope]
    protected function betweenDates(Builder $query, ?string $from, ?string $to): void
    {
        $query->when(filled($from), fn (Builder $query) => $query->whereDate('created_at', '>=', $from))
            ->when(filled($to), fn (Builder $query) => $query->whereDate('created_at', '<=', $to));
    }

    #[Scope]
    protected function search(Builder $query, ?string $term): void
    {
        $query->when(filled($term), function (Builder $query) use ($term): void {
            $query->where(function (Builder $query) use ($term): void {
                $query->where('reference', 'like', "%{$term}%")
                    ->orWhere('note', 'like', "%{$term}%")
                    ->orWhereHas('product', function (Builder $query) use ($term): void {
                        $query->where('name', 'like', "%{$term}%")
                            ->orWhere('sku', 'like', "%{$term}%");
                    });
            });
        });
    }
}
