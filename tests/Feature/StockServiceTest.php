<?php

namespace Tests\Feature;

use App\Enums\MovementType;
use App\Exceptions\InsufficientStockException;
use App\Models\Product;
use App\Models\User;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * The stock maths, which is the part of this system a customer would actually
 * notice being wrong. Every movement has to leave the product quantity and the
 * movement history agreeing with each other.
 */
class StockServiceTest extends TestCase
{
    use RefreshDatabase;

    private StockService $stock;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stock = app(StockService::class);
        $this->user = User::factory()->manager()->create();
    }

    private function product(int $quantity, array $attributes = []): Product
    {
        return Product::factory()->create([
            'quantity' => $quantity,
            ...$attributes,
        ]);
    }

    public function test_stock_in_adds_to_the_quantity_on_hand(): void
    {
        $product = $this->product(10);

        $movement = $this->stock->stockIn($product, 15, [], $this->user);

        $this->assertSame(25, $product->fresh()->quantity);
        $this->assertSame(MovementType::In, $movement->type);
        $this->assertSame(10, $movement->quantity_before);
        $this->assertSame(25, $movement->quantity_after);
        $this->assertSame(15, $movement->quantity_change);
    }

    public function test_stock_out_subtracts_from_the_quantity_on_hand(): void
    {
        $product = $this->product(30);

        $movement = $this->stock->stockOut($product, 12, [], $this->user);

        $this->assertSame(18, $product->fresh()->quantity);
        $this->assertSame(30, $movement->quantity_before);
        $this->assertSame(18, $movement->quantity_after);

        // Stock leaving is stored as a negative change.
        $this->assertSame(-12, $movement->quantity_change);
    }

    public function test_stock_out_down_to_exactly_zero_is_allowed(): void
    {
        $product = $this->product(7);

        $this->stock->stockOut($product, 7, [], $this->user);

        $this->assertSame(0, $product->fresh()->quantity);
    }

    public function test_stock_out_beyond_what_is_available_is_refused(): void
    {
        $product = $this->product(5);

        try {
            $this->stock->stockOut($product, 6, [], $this->user);
            $this->fail('Taking out more than exists should have been refused.');
        } catch (InsufficientStockException $exception) {
            $this->assertSame(6, $exception->requested);
            $this->assertSame(5, $exception->available);
        }

        // Nothing may have moved, and no movement may have been written.
        $this->assertSame(5, $product->fresh()->quantity);
        $this->assertSame(0, $product->movements()->count());
    }

    public function test_an_adjustment_sets_the_quantity_to_the_counted_figure(): void
    {
        $product = $this->product(40);

        $movement = $this->stock->adjust($product, 33, [], $this->user);

        $this->assertSame(33, $product->fresh()->quantity);
        $this->assertSame(MovementType::Adjustment, $movement->type);
        $this->assertSame(40, $movement->quantity_before);
        $this->assertSame(33, $movement->quantity_after);
        $this->assertSame(-7, $movement->quantity_change);
    }

    public function test_an_adjustment_can_count_stock_up_as_well_as_down(): void
    {
        $product = $this->product(4);

        $movement = $this->stock->adjust($product, 9, [], $this->user);

        $this->assertSame(9, $product->fresh()->quantity);
        $this->assertSame(5, $movement->quantity_change);
    }

    public function test_an_adjustment_to_zero_is_allowed(): void
    {
        $product = $this->product(12);

        $this->stock->adjust($product, 0, [], $this->user);

        $this->assertSame(0, $product->fresh()->quantity);
    }

    public function test_a_negative_counted_quantity_is_rejected(): void
    {
        $product = $this->product(12);

        $this->expectException(InvalidArgumentException::class);

        $this->stock->adjust($product, -1, [], $this->user);
    }

    public function test_moving_zero_or_fewer_units_is_rejected(): void
    {
        $product = $this->product(12);

        $this->expectException(InvalidArgumentException::class);

        $this->stock->stockIn($product, 0, [], $this->user);
    }

    public function test_the_passed_in_model_is_left_in_step_with_the_database(): void
    {
        $product = $this->product(10);

        $this->stock->stockIn($product, 5, [], $this->user);

        // Without this, calling code would keep showing the stale quantity.
        $this->assertSame(15, $product->quantity);
        $this->assertFalse($product->isDirty('quantity'));
    }

    public function test_reference_note_and_unit_cost_are_recorded(): void
    {
        $product = $this->product(0);

        $movement = $this->stock->stockIn($product, 3, [
            'reference' => 'PO-2026-0001',
            'note' => 'Arrived damaged',
            'unit_cost' => 12.50,
        ], $this->user);

        $this->assertSame('PO-2026-0001', $movement->reference);
        $this->assertSame('Arrived damaged', $movement->note);
        $this->assertSame('12.50', $movement->unit_cost);
        $this->assertSame($this->user->id, $movement->user_id);
    }

    public function test_a_movement_can_be_recorded_without_a_user(): void
    {
        $product = $this->product(1);

        $movement = $this->stock->stockIn($product, 1, [], null);

        $this->assertNull($movement->user_id);
    }

    public function test_a_run_of_movements_keeps_the_history_continuous(): void
    {
        $product = $this->product(0);

        $this->stock->stockIn($product, 100, [], $this->user);
        $this->stock->stockOut($product, 30, [], $this->user);
        $this->stock->stockIn($product, 5, [], $this->user);
        $this->stock->adjust($product, 70, [], $this->user);

        $this->assertSame(70, $product->fresh()->quantity);

        // Every movement must start where the previous one finished.
        $movements = $product->movements()->orderBy('id')->get();

        $this->assertCount(4, $movements);

        $expectedBefore = 0;

        foreach ($movements as $movement) {
            $this->assertSame(
                $expectedBefore,
                $movement->quantity_before,
                'A movement did not start from the previous balance.'
            );

            $expectedBefore = $movement->quantity_after;
        }

        $this->assertSame(70, $expectedBefore);
    }

    public function test_the_dashboard_summary_adds_up(): void
    {
        Product::query()->forceDelete();

        $this->product(10, ['cost_price' => 2.00, 'sale_price' => 5.00, 'reorder_level' => 0]);
        $this->product(4, ['cost_price' => 1.50, 'sale_price' => 3.00, 'reorder_level' => 10]);
        $this->product(0, ['cost_price' => 9.00, 'sale_price' => 20.00, 'reorder_level' => 5]);

        $summary = $this->stock->summary();

        $this->assertSame(3, $summary['products_count']);
        $this->assertSame(14, $summary['units_on_hand']);

        // 10 * 2.00 + 4 * 1.50 + 0 * 9.00
        $this->assertSame(26.0, $summary['stock_value']);

        // 10 * 5.00 + 4 * 3.00 + 0 * 20.00
        $this->assertSame(62.0, $summary['retail_value']);

        // Only the second product is on hand and at or below its reorder level.
        $this->assertSame(1, $summary['low_stock_count']);
        $this->assertSame(1, $summary['out_of_stock_count']);
    }
}
