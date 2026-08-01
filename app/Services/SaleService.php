<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Exceptions\InsufficientStockException;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleLine;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Ringing up a sale, and reversing one.
 *
 * Stock leaves through StockService rather than by writing to products
 * directly, so a sale lands in the same audited movement history as everything
 * else, carries the invoice number as its reference, and inherits the row
 * locking that stops two tills selling the same last unit.
 *
 * The whole sale is one transaction. If the last line turns out to be short of
 * stock, the earlier lines are rolled back too, so a half sold basket can never
 * be committed.
 */
class SaleService
{
    public function __construct(private readonly StockService $stock) {}

    /**
     * @param  array<int, array<string, mixed>>  $items  product_id, quantity, optional unit_price
     * @param  array<string, mixed>  $options  customer_id, payment_method, discount, paid, note
     *
     * @throws InsufficientStockException when any line cannot be filled
     * @throws InvalidArgumentException when the basket or the money does not add up
     */
    public function record(array $items, array $options, User $cashier): Sale
    {
        $lines = $this->resolveLines($items);

        if ($lines === []) {
            throw new InvalidArgumentException('A sale needs at least one line.');
        }

        $subtotal = round(array_sum(array_map(
            fn (array $line): float => $line['quantity'] * $line['unit_price'],
            $lines,
        )), 2);

        $discount = round((float) ($options['discount'] ?? 0), 2);

        if ($discount < 0 || $discount > $subtotal) {
            throw new InvalidArgumentException('The discount cannot be negative or larger than the subtotal.');
        }

        $total = round($subtotal - $discount, 2);
        $method = $options['payment_method'] ?? PaymentMethod::Cash;
        $method = $method instanceof PaymentMethod ? $method : PaymentMethod::from((string) $method);

        // Only cash involves handing money over and getting change back. Card
        // and transfer settle for the exact amount.
        $paid = $method->needsChange()
            ? round((float) ($options['paid'] ?? $total), 2)
            : $total;

        if ($paid < $total) {
            throw new InvalidArgumentException('The amount paid is less than the total.');
        }

        return DB::transaction(function () use ($lines, $options, $cashier, $subtotal, $discount, $total, $method, $paid): Sale {
            $sale = Sale::query()->create([
                'number' => Sale::nextNumber(),
                'customer_id' => $options['customer_id'] ?? null,
                'user_id' => $cashier->getKey(),
                'status' => SaleStatus::Completed,
                'payment_method' => $method,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'total' => $total,
                'paid' => $paid,
                'change_due' => round($paid - $total, 2),
                'sold_at' => now(),
                'note' => $options['note'] ?? null,
            ]);

            foreach ($lines as $line) {
                $sale->lines()->create([
                    'product_id' => $line['product']->getKey(),
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'unit_cost' => $line['unit_cost'],
                ]);

                // Throws if the shelf cannot cover it, which rolls the whole
                // sale back including any lines already taken out.
                $this->stock->stockOut($line['product'], $line['quantity'], [
                    'reference' => $sale->number,
                    'unit_cost' => $line['unit_cost'],
                ], $cashier);
            }

            return $sale;
        });
    }

    /**
     * Reverse a sale and put the stock back on the shelf.
     *
     * The invoice is kept rather than deleted, so the numbering stays unbroken
     * and the reversal is visible to anyone auditing the day's takings.
     */
    public function void(Sale $sale, User $user, ?string $reason = null): void
    {
        if (! $sale->status->isVoidable()) {
            throw new InvalidArgumentException('This sale has already been reversed.');
        }

        DB::transaction(function () use ($sale, $user, $reason): void {
            $sale->load('lines.product');

            foreach ($sale->lines as $line) {
                // A product deleted since the sale cannot take stock back, so
                // its line is left alone rather than resurrecting the product.
                if ($line->product === null || $line->product->trashed()) {
                    continue;
                }

                $this->stock->stockIn($line->product, $line->quantity, [
                    'reference' => $sale->number,
                    'note' => $reason ?? __('app.sale.voided'),
                    'unit_cost' => $line->unit_cost,
                ], $user);
            }

            $sale->forceFill([
                'status' => SaleStatus::Voided,
                'voided_at' => now(),
                'note' => $reason ?? $sale->note,
            ])->save();
        });
    }

    /**
     * Turn a basket into priced lines, merging repeats of the same product.
     *
     * Scanning an item twice should raise its quantity, not create a second
     * line, which the unique index on sale_lines also insists on.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    protected function resolveLines(array $items): array
    {
        /** @var array<int, array<string, mixed>> $merged */
        $merged = [];

        foreach ($items as $item) {
            $productId = (int) ($item['product_id'] ?? 0);
            $quantity = (int) ($item['quantity'] ?? 0);

            if ($productId < 1 || $quantity < 1) {
                continue;
            }

            if (isset($merged[$productId])) {
                $merged[$productId]['quantity'] += $quantity;

                continue;
            }

            /** @var Product|null $product */
            $product = Product::query()->find($productId);

            if ($product === null) {
                continue;
            }

            $price = array_key_exists('unit_price', $item) && $item['unit_price'] !== null && $item['unit_price'] !== ''
                ? round((float) $item['unit_price'], 2)
                : round((float) $product->sale_price, 2);

            if ($price < 0) {
                throw new InvalidArgumentException('A line price cannot be negative.');
            }

            $merged[$productId] = [
                'product' => $product,
                'quantity' => $quantity,
                'unit_price' => $price,
                // Captured now, because the product cost will drift later.
                'unit_cost' => round((float) $product->cost_price, 2),
            ];
        }

        return array_values($merged);
    }

    /**
     * Takings for a day, for the dashboard and the end of shift check.
     *
     * @return array<string, int|float>
     */
    public function dailyTotals(?string $date = null): array
    {
        $day = $date ?? now()->toDateString();

        // completed() first, then toBase(). A base query builder has no
        // Eloquent scopes on it, and toBase() returns a plain row so the
        // aliases below are not shadowed by any accessor on the model.
        $totals = Sale::query()
            ->completed()
            ->toBase()
            ->whereDate('sold_at', $day)
            ->selectRaw('COUNT(*) as sales_count')
            ->selectRaw('COALESCE(SUM(total), 0) as revenue')
            ->selectRaw('COALESCE(SUM(discount), 0) as discounts')
            ->first();

        $cost = SaleLine::query()
            ->join('sales', 'sale_lines.sale_id', '=', 'sales.id')
            ->where('sales.status', SaleStatus::Completed->value)
            ->whereDate('sales.sold_at', $day)
            ->sum(DB::raw('sale_lines.quantity * sale_lines.unit_cost'));

        $revenue = round((float) ($totals->revenue ?? 0), 2);

        return [
            'sales_count' => (int) ($totals->sales_count ?? 0),
            'revenue' => $revenue,
            'discounts' => round((float) ($totals->discounts ?? 0), 2),
            'profit' => round($revenue - (float) $cost, 2),
        ];
    }
}
