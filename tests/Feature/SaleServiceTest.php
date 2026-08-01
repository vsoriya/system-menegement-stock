<?php

namespace Tests\Feature;

use App\Enums\MovementType;
use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Exceptions\InsufficientStockException;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Services\SaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Selling is where money and stock meet, so these tests care about two things
 * on every sale: the takings add up, and the shelf count moved by exactly the
 * amount that left the shop, with a movement record naming the invoice.
 */
class SaleServiceTest extends TestCase
{
    use RefreshDatabase;

    private SaleService $sales;

    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sales = app(SaleService::class);
        $this->cashier = User::factory()->staff()->create();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function product(int $quantity, float $cost = 8, float $price = 12, array $attributes = []): Product
    {
        return Product::factory()->create([
            'quantity' => $quantity,
            'cost_price' => $cost,
            'sale_price' => $price,
            ...$attributes,
        ]);
    }

    public function test_a_sale_takes_the_stock_off_the_shelf(): void
    {
        $product = $this->product(20);

        $sale = $this->sales->record(
            [['product_id' => $product->id, 'quantity' => 3]],
            [],
            $this->cashier,
        );

        $this->assertSame(17, $product->fresh()->quantity);
        $this->assertSame(SaleStatus::Completed, $sale->status);
        $this->assertSame(36.0, (float) $sale->total);
        $this->assertSame($this->cashier->id, $sale->user_id);
    }

    public function test_the_stock_movement_names_the_invoice_it_came_from(): void
    {
        $product = $this->product(20);

        $sale = $this->sales->record(
            [['product_id' => $product->id, 'quantity' => 4]],
            [],
            $this->cashier,
        );

        $movement = $product->movements()->sole();

        // The reference is what makes a shortfall traceable back to a receipt
        // months later, so it is not optional.
        $this->assertSame($sale->number, $movement->reference);
        $this->assertSame(MovementType::Out, $movement->type);
        $this->assertSame(-4, $movement->quantity_change);
        $this->assertSame(20, $movement->quantity_before);
        $this->assertSame(16, $movement->quantity_after);
        $this->assertSame($this->cashier->id, $movement->user_id);
    }

    public function test_prices_are_snapshotted_onto_the_line(): void
    {
        $product = $this->product(10, cost: 8, price: 12);

        $sale = $this->sales->record(
            [['product_id' => $product->id, 'quantity' => 2]],
            [],
            $this->cashier,
        );

        // Changing the product afterwards must not rewrite what the invoice
        // said, otherwise every past sale's profit shifts with today's costs.
        $product->update(['cost_price' => 99, 'sale_price' => 150]);

        $line = $sale->lines()->sole();

        $this->assertSame(12.0, (float) $line->unit_price);
        $this->assertSame(8.0, (float) $line->unit_cost);
        $this->assertSame(24.0, $line->line_total);
        $this->assertSame(8.0, $line->line_profit);
    }

    public function test_a_short_line_rolls_the_whole_sale_back(): void
    {
        $plenty = $this->product(50);
        $scarce = $this->product(1);

        try {
            $this->sales->record([
                ['product_id' => $plenty->id, 'quantity' => 5],
                ['product_id' => $scarce->id, 'quantity' => 9],
            ], [], $this->cashier);

            $this->fail('A sale with an unfillable line should not be recorded.');
        } catch (InsufficientStockException $e) {
            $this->assertSame($scarce->id, $e->product->id);
        }

        // The first line had already been taken out when the second one failed.
        // If that is not undone, the shop loses stock it never sold.
        $this->assertSame(50, $plenty->fresh()->quantity);
        $this->assertSame(1, $scarce->fresh()->quantity);
        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('sale_lines', 0);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_the_same_product_scanned_twice_becomes_one_line(): void
    {
        $product = $this->product(20);

        $sale = $this->sales->record([
            ['product_id' => $product->id, 'quantity' => 2],
            ['product_id' => $product->id, 'quantity' => 3],
        ], [], $this->cashier);

        $this->assertSame(1, $sale->lines()->count());
        $this->assertSame(5, $sale->lines()->sole()->quantity);
        $this->assertSame(60.0, (float) $sale->total);
        $this->assertSame(15, $product->fresh()->quantity);
    }

    public function test_a_line_price_can_be_overridden(): void
    {
        $product = $this->product(10, cost: 8, price: 12);

        $sale = $this->sales->record(
            [['product_id' => $product->id, 'quantity' => 2, 'unit_price' => 10]],
            [],
            $this->cashier,
        );

        $this->assertSame(10.0, (float) $sale->lines()->sole()->unit_price);
        $this->assertSame(20.0, (float) $sale->total);
    }

    public function test_a_negative_line_price_is_refused(): void
    {
        $product = $this->product(10);

        $this->expectException(InvalidArgumentException::class);

        $this->sales->record(
            [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => -5]],
            [],
            $this->cashier,
        );
    }

    public function test_an_empty_basket_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->sales->record([], [], $this->cashier);
    }

    public function test_a_basket_of_nothing_but_junk_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        // Zero quantities and unknown products are dropped, which must leave an
        // empty basket rather than a zero value sale.
        $this->sales->record([
            ['product_id' => 999999, 'quantity' => 2],
            ['product_id' => 1, 'quantity' => 0],
        ], [], $this->cashier);
    }

    public function test_a_discount_is_taken_off_the_total(): void
    {
        $product = $this->product(10, cost: 8, price: 12);

        $sale = $this->sales->record(
            [['product_id' => $product->id, 'quantity' => 2]],
            ['discount' => 4],
            $this->cashier,
        );

        $this->assertSame(24.0, (float) $sale->subtotal);
        $this->assertSame(4.0, (float) $sale->discount);
        $this->assertSame(20.0, (float) $sale->total);

        // Profit is what is left after the discount, not before it.
        $this->assertSame(4.0, $sale->profit);
    }

    public function test_a_discount_bigger_than_the_basket_is_refused(): void
    {
        $product = $this->product(10, price: 12);

        $this->expectException(InvalidArgumentException::class);

        $this->sales->record(
            [['product_id' => $product->id, 'quantity' => 1]],
            ['discount' => 50],
            $this->cashier,
        );
    }

    public function test_cash_change_is_worked_out(): void
    {
        $product = $this->product(10, price: 12);

        $sale = $this->sales->record(
            [['product_id' => $product->id, 'quantity' => 2]],
            ['payment_method' => 'cash', 'paid' => 30],
            $this->cashier,
        );

        $this->assertSame(24.0, (float) $sale->total);
        $this->assertSame(30.0, (float) $sale->paid);
        $this->assertSame(6.0, (float) $sale->change_due);
    }

    public function test_paying_less_than_the_total_is_refused(): void
    {
        $product = $this->product(10, price: 12);

        $this->expectException(InvalidArgumentException::class);

        $this->sales->record(
            [['product_id' => $product->id, 'quantity' => 2]],
            ['payment_method' => 'cash', 'paid' => 10],
            $this->cashier,
        );
    }

    public function test_card_payments_settle_for_the_exact_amount(): void
    {
        $product = $this->product(10, price: 12);

        $sale = $this->sales->record(
            [['product_id' => $product->id, 'quantity' => 2]],
            // A card cannot overpay, so a stray tendered amount is ignored
            // rather than producing change that nobody handed over.
            ['payment_method' => 'card', 'paid' => 500],
            $this->cashier,
        );

        $this->assertSame(PaymentMethod::Card, $sale->payment_method);
        $this->assertSame(24.0, (float) $sale->paid);
        $this->assertSame(0.0, (float) $sale->change_due);
    }

    public function test_a_sale_can_be_attached_to_a_customer(): void
    {
        $product = $this->product(10);
        $customer = Customer::factory()->create();

        $sale = $this->sales->record(
            [['product_id' => $product->id, 'quantity' => 1]],
            ['customer_id' => $customer->id],
            $this->cashier,
        );

        $this->assertSame($customer->id, $sale->customer->id);
        $this->assertSame(12.0, $customer->fresh()->total_spent);
    }

    public function test_invoice_numbers_run_in_sequence(): void
    {
        $product = $this->product(50);
        $year = now()->year;

        $first = $this->sales->record([['product_id' => $product->id, 'quantity' => 1]], [], $this->cashier);
        $second = $this->sales->record([['product_id' => $product->id, 'quantity' => 1]], [], $this->cashier);

        $this->assertSame("INV-{$year}-000001", $first->number);
        $this->assertSame("INV-{$year}-000002", $second->number);
    }

    public function test_voiding_a_sale_puts_the_stock_back(): void
    {
        $product = $this->product(20);

        $sale = $this->sales->record(
            [['product_id' => $product->id, 'quantity' => 6]],
            [],
            $this->cashier,
        );

        $this->assertSame(14, $product->fresh()->quantity);

        $this->sales->void($sale, $this->cashier, 'Customer changed their mind');

        $sale->refresh();

        $this->assertSame(20, $product->fresh()->quantity);
        $this->assertSame(SaleStatus::Voided, $sale->status);
        $this->assertNotNull($sale->voided_at);
        $this->assertTrue($sale->is_voided);
        $this->assertSame('Customer changed their mind', $sale->note);
    }

    public function test_voiding_writes_a_return_movement_rather_than_erasing_the_sale(): void
    {
        $product = $this->product(20);

        $sale = $this->sales->record(
            [['product_id' => $product->id, 'quantity' => 6]],
            [],
            $this->cashier,
        );

        $this->sales->void($sale, $this->cashier);

        // Two movements, out then back in, both pointing at the same invoice.
        // The sale row survives so the numbering never has a hole in it.
        $this->assertSame(2, $product->movements()->count());
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'type' => MovementType::In->value,
            'reference' => $sale->number,
            'quantity_change' => 6,
        ]);
        $this->assertDatabaseHas('sales', ['id' => $sale->id]);
    }

    public function test_a_sale_cannot_be_voided_twice(): void
    {
        $product = $this->product(20);

        $sale = $this->sales->record(
            [['product_id' => $product->id, 'quantity' => 5]],
            [],
            $this->cashier,
        );

        $this->sales->void($sale, $this->cashier);

        try {
            $this->sales->void($sale->fresh(), $this->cashier);

            $this->fail('Voiding twice should be refused.');
        } catch (InvalidArgumentException) {
            // Otherwise the stock would be returned a second time and the shop
            // would show inventory it does not have.
            $this->assertSame(20, $product->fresh()->quantity);
        }
    }

    public function test_voiding_skips_a_product_that_has_since_been_deleted(): void
    {
        $kept = $this->product(20);
        $removed = $this->product(20);

        $sale = $this->sales->record([
            ['product_id' => $kept->id, 'quantity' => 5],
            ['product_id' => $removed->id, 'quantity' => 5],
        ], [], $this->cashier);

        $removed->delete();

        $this->sales->void($sale, $this->cashier);

        $this->assertSame(20, $kept->fresh()->quantity);

        // The deleted product keeps the reduced count. Putting stock back on a
        // product nobody sells any more would be a phantom figure.
        $this->assertSame(15, Product::withTrashed()->find($removed->id)->quantity);
        $this->assertSame(SaleStatus::Voided, $sale->fresh()->status);
    }

    public function test_voided_sales_are_left_out_of_the_takings(): void
    {
        $product = $this->product(100, cost: 8, price: 12);

        $keep = $this->sales->record([['product_id' => $product->id, 'quantity' => 2]], [], $this->cashier);
        $scrap = $this->sales->record([['product_id' => $product->id, 'quantity' => 3]], [], $this->cashier);

        $this->sales->void($scrap, $this->cashier);

        $totals = $this->sales->dailyTotals();

        $this->assertSame(1, $totals['sales_count']);
        $this->assertSame(24.0, $totals['revenue']);
        $this->assertSame(8.0, $totals['profit']);
        $this->assertSame(24.0, (float) $keep->total);
    }

    public function test_daily_totals_add_up_across_sales(): void
    {
        $product = $this->product(100, cost: 8, price: 12);

        // 24.00 taken, 16.00 of it cost.
        $this->sales->record([['product_id' => $product->id, 'quantity' => 2]], [], $this->cashier);

        // 36.00 less a 2.00 discount, 24.00 of it cost.
        $this->sales->record(
            [['product_id' => $product->id, 'quantity' => 3]],
            ['discount' => 2],
            $this->cashier,
        );

        $totals = $this->sales->dailyTotals();

        $this->assertSame(2, $totals['sales_count']);
        $this->assertSame(58.0, $totals['revenue']);
        $this->assertSame(2.0, $totals['discounts']);
        $this->assertSame(18.0, $totals['profit']);
    }

    public function test_daily_totals_are_empty_on_a_day_with_no_sales(): void
    {
        $totals = $this->sales->dailyTotals(now()->subYear()->toDateString());

        $this->assertSame(0, $totals['sales_count']);
        $this->assertSame(0.0, $totals['revenue']);
        $this->assertSame(0.0, $totals['profit']);
    }

    public function test_item_count_totals_the_units_sold(): void
    {
        $first = $this->product(20);
        $second = $this->product(20);

        $sale = $this->sales->record([
            ['product_id' => $first->id, 'quantity' => 2],
            ['product_id' => $second->id, 'quantity' => 4],
        ], [], $this->cashier);

        $this->assertSame(6, $sale->load('lines')->item_count);
    }

    public function test_completed_scope_only_returns_live_sales(): void
    {
        $product = $this->product(100);

        $this->sales->record([['product_id' => $product->id, 'quantity' => 1]], [], $this->cashier);
        $voided = $this->sales->record([['product_id' => $product->id, 'quantity' => 1]], [], $this->cashier);
        $this->sales->void($voided, $this->cashier);

        $this->assertSame(1, Sale::query()->completed()->count());
        $this->assertSame(2, Sale::query()->count());
    }
}
