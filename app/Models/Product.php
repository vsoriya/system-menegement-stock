<?php

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'sku',
    'barcode',
    'image_path',
    'name',
    'description',
    'category_id',
    'supplier_id',
    'unit',
    'cost_price',
    'sale_price',
    'quantity',
    'reorder_level',
    'is_active',
])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cost_price' => 'decimal:2',
            'sale_price' => 'decimal:2',
            'quantity' => 'integer',
            'reorder_level' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * @return HasMany<StockMovement, $this>
     */
    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class)->latest();
    }

    /**
     * @return HasMany<PurchaseOrderLine, $this>
     */
    public function purchaseOrderLines(): HasMany
    {
        return $this->hasMany(PurchaseOrderLine::class);
    }

    /**
     * @return HasMany<SaleLine, $this>
     */
    public function saleLines(): HasMany
    {
        return $this->hasMany(SaleLine::class);
    }

    /**
     * Whether this product is named on a purchase order or an invoice.
     *
     * Both tables restrict deletion at the database level on purpose, so a
     * permanent purge has to be refused politely rather than blowing up on a
     * foreign key error.
     */
    public function hasTradingHistory(): bool
    {
        return $this->purchaseOrderLines()->exists() || $this->saleLines()->exists();
    }

    /**
     * Public URL for the uploaded image, or null when there is none.
     *
     * The disk is configured rather than hardcoded, so the same code serves a
     * local folder on a single server and object storage on a host that hands
     * the application a throwaway disk.
     */
    protected function imageUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->image_path
            ? Storage::disk(config('filesystems.images'))->url($this->image_path)
            : null);
    }

    /**
     * Value of the quantity on hand at cost price.
     */
    protected function stockValue(): Attribute
    {
        return Attribute::get(fn (): float => round($this->quantity * (float) $this->cost_price, 2));
    }

    /**
     * Value of the quantity on hand at sale price.
     */
    protected function retailValue(): Attribute
    {
        return Attribute::get(fn (): float => round($this->quantity * (float) $this->sale_price, 2));
    }

    /**
     * One of: out_of_stock, low_stock, in_stock.
     */
    protected function stockStatus(): Attribute
    {
        return Attribute::get(function (): string {
            if ($this->quantity <= 0) {
                return 'out_of_stock';
            }

            return $this->quantity <= $this->reorder_level ? 'low_stock' : 'in_stock';
        });
    }

    protected function stockStatusLabel(): Attribute
    {
        return Attribute::get(fn (): string => __('app.stock_status.'.$this->stock_status));
    }

    protected function stockStatusClasses(): Attribute
    {
        return Attribute::get(fn (): string => match ($this->stock_status) {
            'out_of_stock' => 'bg-rose-50 dark:bg-rose-500/15 text-rose-700 dark:text-rose-300 ring-rose-600/20 dark:ring-rose-400/30',
            'low_stock' => 'bg-amber-50 dark:bg-amber-500/15 text-amber-700 dark:text-amber-300 ring-amber-600/20 dark:ring-amber-400/30',
            default => 'bg-emerald-50 dark:bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 ring-emerald-600/20 dark:ring-emerald-400/30',
        });
    }

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    #[Scope]
    protected function outOfStock(Builder $query): void
    {
        $query->where('quantity', '<=', 0);
    }

    /**
     * On hand but at or below the reorder level.
     */
    #[Scope]
    protected function lowStock(Builder $query): void
    {
        $query->where('quantity', '>', 0)
            ->whereColumn('quantity', '<=', 'reorder_level');
    }

    /**
     * Everything that needs restocking, including items already at zero.
     */
    #[Scope]
    protected function needsReorder(Builder $query): void
    {
        $query->whereColumn('quantity', '<=', 'reorder_level');
    }

    #[Scope]
    protected function search(Builder $query, ?string $term): void
    {
        $query->when(filled($term), function (Builder $query) use ($term): void {
            $query->where(function (Builder $query) use ($term): void {
                $query->where('name', 'like', "%{$term}%")
                    ->orWhere('sku', 'like', "%{$term}%")
                    // Barcode scanners type the full code, so match it exactly.
                    ->orWhere('barcode', $term)
                    ->orWhere('description', 'like', "%{$term}%");
            });
        });
    }

    /**
     * Filter by the stock status keys exposed in the UI.
     */
    #[Scope]
    protected function stockStatusIs(Builder $query, ?string $status): void
    {
        match ($status) {
            'out_of_stock' => $query->outOfStock(),
            'low_stock' => $query->lowStock(),
            'needs_reorder' => $query->needsReorder(),
            'in_stock' => $query->whereColumn('quantity', '>', 'reorder_level')->where('quantity', '>', 0),
            default => null,
        };
    }
}
