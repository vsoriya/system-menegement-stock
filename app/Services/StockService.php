<?php

namespace App\Services;

use App\Enums\MovementType;
use App\Exceptions\InsufficientStockException;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class StockService
{
    /**
     * Receive stock into the warehouse.
     *
     * @param  array<string, mixed>  $meta
     */
    public function stockIn(Product $product, int $quantity, array $meta = [], ?User $user = null): StockMovement
    {
        return $this->record($product, MovementType::In, $quantity, $meta, $user);
    }

    /**
     * Release stock out of the warehouse.
     *
     * @param  array<string, mixed>  $meta
     */
    public function stockOut(Product $product, int $quantity, array $meta = [], ?User $user = null): StockMovement
    {
        return $this->record($product, MovementType::Out, $quantity, $meta, $user);
    }

    /**
     * Correct the quantity on hand to a physically counted value.
     *
     * @param  array<string, mixed>  $meta
     */
    public function adjust(Product $product, int $countedQuantity, array $meta = [], ?User $user = null): StockMovement
    {
        return $this->record($product, MovementType::Adjustment, $countedQuantity, $meta, $user);
    }

    /**
     * Apply a movement and update the product quantity in one transaction.
     *
     * For In and Out, $quantity is the amount moved and must be positive.
     * For Adjustment, $quantity is the new counted quantity on hand.
     *
     * @param  array<string, mixed>  $meta  reference, note, unit_cost
     *
     * @throws InsufficientStockException
     * @throws InvalidArgumentException
     */
    public function record(
        Product $product,
        MovementType $type,
        int $quantity,
        array $meta = [],
        ?User $user = null,
    ): StockMovement {
        if ($type !== MovementType::Adjustment && $quantity < 1) {
            throw new InvalidArgumentException('Quantity must be at least 1.');
        }

        if ($type === MovementType::Adjustment && $quantity < 0) {
            throw new InvalidArgumentException('Counted quantity cannot be negative.');
        }

        return DB::transaction(function () use ($product, $type, $quantity, $meta, $user): StockMovement {
            // Lock the row so two concurrent movements cannot read the same
            // starting quantity and overwrite each other.
            /** @var Product $locked */
            $locked = Product::query()
                ->whereKey($product->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $before = $locked->quantity;

            $after = match ($type) {
                MovementType::In => $before + $quantity,
                MovementType::Out => $before - $quantity,
                MovementType::Adjustment => $quantity,
            };

            if ($after < 0) {
                throw new InsufficientStockException($locked, $quantity, $before);
            }

            $locked->forceFill(['quantity' => $after])->save();

            $movement = $locked->movements()->create([
                'user_id' => $user?->getKey(),
                'type' => $type,
                'quantity_change' => $after - $before,
                'quantity_before' => $before,
                'quantity_after' => $after,
                'unit_cost' => $meta['unit_cost'] ?? null,
                'reference' => $meta['reference'] ?? null,
                'note' => $meta['note'] ?? null,
            ]);

            // Keep the caller's instance in sync with what was persisted.
            $product->setAttribute('quantity', $after)->syncOriginalAttribute('quantity');

            return $movement;
        });
    }

    /**
     * Headline numbers for the dashboard.
     *
     * @return array<string, int|float>
     */
    public function summary(): array
    {
        // toBase() so the row comes back plain rather than hydrated into a
        // Product. On a Product, the stock_value and retail_value accessors
        // shadow the aliases below and recompute from quantity and cost_price,
        // neither of which this query selects, so both silently became zero.
        $totals = Product::query()
            ->toBase()
            ->selectRaw('COUNT(*) as products_count')
            ->selectRaw('COALESCE(SUM(quantity), 0) as units_on_hand')
            ->selectRaw('COALESCE(SUM(quantity * cost_price), 0) as stock_value')
            ->selectRaw('COALESCE(SUM(quantity * sale_price), 0) as retail_value')
            ->first();

        return [
            'products_count' => (int) ($totals->products_count ?? 0),
            'units_on_hand' => (int) ($totals->units_on_hand ?? 0),
            'stock_value' => round((float) ($totals->stock_value ?? 0), 2),
            'retail_value' => round((float) ($totals->retail_value ?? 0), 2),
            'low_stock_count' => Product::query()->lowStock()->count(),
            'out_of_stock_count' => Product::query()->outOfStock()->count(),
        ];
    }
}
