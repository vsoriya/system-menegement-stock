<?php

namespace App\Services;

use App\Enums\StockTakeStatus;
use App\Models\Product;
use App\Models\StockTake;
use App\Models\StockTakeLine;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class StockTakeService
{
    public function __construct(private readonly StockService $stock) {}

    /**
     * Open a count sheet and snapshot the current balances onto it.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function start(array $attributes, User $user): StockTake
    {
        return DB::transaction(function () use ($attributes, $user): StockTake {
            $take = StockTake::query()->create([
                ...$attributes,
                'created_by' => $user->getKey(),
                'status' => StockTakeStatus::Open,
            ]);

            $timestamp = now();

            $rows = $this->productsInScope($take->category_id)
                ->map(fn (Product $product): array => [
                    'stock_take_id' => $take->getKey(),
                    'product_id' => $product->getKey(),
                    'expected_quantity' => $product->quantity,
                    'counted_quantity' => null,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ])
                ->all();

            if ($rows !== []) {
                StockTakeLine::query()->insert($rows);
            }

            return $take;
        });
    }

    /**
     * Store the quantities someone counted.
     *
     * Only the lines present in the payload are touched, so a paginated sheet
     * can be saved one page at a time without clearing the other pages.
     *
     * @param  array<int|string, mixed>  $counts  counted quantity keyed by line id
     */
    public function saveCounts(StockTake $take, array $counts): void
    {
        $submitted = collect($counts)
            ->mapWithKeys(fn ($value, $lineId): array => [(int) $lineId => $value])
            ->all();

        DB::transaction(function () use ($take, $submitted): void {
            $lines = $take->lines()
                ->whereKey(array_keys($submitted))
                ->get();

            foreach ($lines as $line) {
                $value = $submitted[$line->getKey()];

                $line->forceFill([
                    'counted_quantity' => ($value === null || $value === '') ? null : (int) $value,
                ])->save();
            }
        });
    }

    /**
     * Write every difference to stock as an adjustment and close the count.
     *
     * @return int number of products whose stock changed
     */
    public function post(StockTake $take, User $user): int
    {
        return DB::transaction(function () use ($take, $user): int {
            $take->load('lines.product');
            $adjusted = 0;

            foreach ($take->lines as $line) {
                if (! $line->hasVariance()) {
                    continue;
                }

                // A product removed from the catalog mid count cannot take an
                // adjustment, so its line is skipped rather than resurrected.
                if ($line->product === null || $line->product->trashed()) {
                    continue;
                }

                // An adjustment sets the balance to the counted figure, which is
                // what a physical count is meant to do.
                $this->stock->adjust($line->product, (int) $line->counted_quantity, [
                    'reference' => $take->reference,
                    'note' => __('app.stocktake.one'),
                ], $user);

                $adjusted++;
            }

            $take->forceFill([
                'status' => StockTakeStatus::Posted,
                'posted_at' => now(),
            ])->save();

            return $adjusted;
        });
    }

    public function cancel(StockTake $take): void
    {
        $take->forceFill([
            'status' => StockTakeStatus::Cancelled,
        ])->save();
    }

    /**
     * How many products a count would cover. Used to reject an empty sheet
     * before one is created.
     */
    public function scopeSize(?int $categoryId): int
    {
        return Product::query()
            ->active()
            ->when($categoryId, fn (Builder $query) => $query->where('category_id', $categoryId))
            ->count();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Product>
     */
    protected function productsInScope(?int $categoryId)
    {
        return Product::query()
            ->active()
            ->when($categoryId, fn (Builder $query) => $query->where('category_id', $categoryId))
            ->orderBy('name')
            ->get(['id', 'quantity']);
    }
}
