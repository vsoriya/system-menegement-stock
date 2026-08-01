<?php

namespace Tests\Feature;

use App\Enums\MovementType;
use App\Enums\StockTakeStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\StockTake;
use App\Models\StockTakeLine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Counting the shelves and correcting the system to match.
 *
 * The important guarantees are that starting a count freezes what the system
 * believed at that moment, and that posting only moves stock for lines that
 * were actually counted.
 */
class StockTakeTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = User::factory()->manager()->create();
    }

    public function test_starting_a_count_snapshots_every_active_product(): void
    {
        Product::factory()->create(['quantity' => 25]);
        Product::factory()->create(['quantity' => 4]);
        Product::factory()->inactive()->create(['quantity' => 99]);

        $this->actingAs($this->manager)
            ->post(route('stock-takes.store'), [
                'reference' => 'August count',
                'counted_at' => '2026-08-01',
                'scope' => 'all',
            ])
            ->assertRedirect();

        $take = StockTake::query()->latest('id')->firstOrFail();

        $this->assertSame(StockTakeStatus::Open, $take->status);
        $this->assertSame($this->manager->id, $take->created_by);

        // Inactive products are left off the sheet.
        $this->assertSame(2, $take->lines()->count());

        $this->assertSame([4, 25], $take->lines()->orderBy('expected_quantity')->pluck('expected_quantity')->all());

        // Nothing is counted yet.
        $this->assertSame(2, $take->lines()->whereNull('counted_quantity')->count());
    }

    public function test_a_count_can_be_limited_to_one_category(): void
    {
        $category = Category::factory()->create();

        Product::factory()->count(2)->create(['category_id' => $category->id]);
        Product::factory()->create();

        $this->actingAs($this->manager)
            ->post(route('stock-takes.store'), [
                'reference' => 'Drinks only',
                'counted_at' => '2026-08-01',
                'scope' => 'category',
                'category_id' => $category->id,
            ])
            ->assertRedirect();

        $take = StockTake::query()->latest('id')->firstOrFail();

        $this->assertSame($category->id, $take->category_id);
        $this->assertSame(2, $take->lines()->count());
    }

    public function test_choosing_a_category_scope_without_a_category_is_refused(): void
    {
        Product::factory()->create();

        $this->actingAs($this->manager)
            ->post(route('stock-takes.store'), [
                'reference' => 'Nowhere',
                'counted_at' => '2026-08-01',
                'scope' => 'category',
            ])
            ->assertSessionHasErrors('category_id');
    }

    public function test_a_count_over_nothing_is_refused(): void
    {
        // No active products at all.
        Product::factory()->inactive()->create();

        $this->actingAs($this->manager)
            ->post(route('stock-takes.store'), [
                'reference' => 'Empty shop',
                'counted_at' => '2026-08-01',
                'scope' => 'all',
            ])
            ->assertSessionHasErrors('scope');

        $this->assertSame(0, StockTake::query()->count());
    }

    public function test_saving_the_sheet_stores_the_counts_without_moving_stock(): void
    {
        [$take, $line] = $this->openCountFor(systemQuantity: 20);

        $this->actingAs($this->manager)
            ->put(route('stock-takes.update', $take), [
                'action' => 'save',
                'counts' => [$line->id => 17],
            ])
            ->assertRedirect();

        $this->assertSame(17, $line->fresh()->counted_quantity);
        $this->assertSame(-3, $line->fresh()->variance);

        // Saving is not posting. Stock must be untouched.
        $this->assertSame(20, $line->product->fresh()->quantity);
        $this->assertSame(StockTakeStatus::Open, $take->fresh()->status);
    }

    public function test_clearing_a_count_puts_the_line_back_to_uncounted(): void
    {
        [$take, $line] = $this->openCountFor(systemQuantity: 20);

        $this->actingAs($this->manager)->put(route('stock-takes.update', $take), [
            'action' => 'save',
            'counts' => [$line->id => 17],
        ]);

        $this->actingAs($this->manager)->put(route('stock-takes.update', $take), [
            'action' => 'save',
            'counts' => [$line->id => ''],
        ]);

        $this->assertNull($line->fresh()->counted_quantity);
        $this->assertNull($line->fresh()->variance);
    }

    public function test_posting_corrects_stock_to_the_counted_figure(): void
    {
        [$take, $line] = $this->openCountFor(systemQuantity: 20);

        $this->actingAs($this->manager)
            ->put(route('stock-takes.update', $take), [
                'action' => 'post',
                'counts' => [$line->id => 17],
            ])
            ->assertRedirect(route('stock-takes.show', $take));

        $take->refresh();

        $this->assertSame(StockTakeStatus::Posted, $take->status);
        $this->assertNotNull($take->posted_at);

        // Stock now matches the shelf.
        $this->assertSame(17, $line->product->fresh()->quantity);

        // And the correction is on the record as an adjustment.
        $movement = $line->product->movements()->firstOrFail();

        $this->assertSame(MovementType::Adjustment, $movement->type);
        $this->assertSame(20, $movement->quantity_before);
        $this->assertSame(17, $movement->quantity_after);
        $this->assertSame($take->reference, $movement->reference);
        $this->assertSame($this->manager->id, $movement->user_id);
    }

    public function test_posting_leaves_uncounted_lines_alone(): void
    {
        $take = StockTake::factory()->create(['created_by' => $this->manager->id]);

        $counted = StockTakeLine::factory()->create([
            'stock_take_id' => $take->id,
            'product_id' => Product::factory()->create(['quantity' => 10])->id,
            'expected_quantity' => 10,
        ]);

        $untouched = StockTakeLine::factory()->create([
            'stock_take_id' => $take->id,
            'product_id' => Product::factory()->create(['quantity' => 50])->id,
            'expected_quantity' => 50,
        ]);

        $this->actingAs($this->manager)->put(route('stock-takes.update', $take), [
            'action' => 'post',
            'counts' => [$counted->id => 8],
        ]);

        $this->assertSame(8, $counted->product->fresh()->quantity);

        // Never counted, so never adjusted.
        $this->assertSame(50, $untouched->product->fresh()->quantity);
        $this->assertSame(0, $untouched->product->movements()->count());
    }

    public function test_posting_a_count_where_everything_matches_is_refused(): void
    {
        [$take, $line] = $this->openCountFor(systemQuantity: 20);

        $this->actingAs($this->manager)
            ->put(route('stock-takes.update', $take), [
                'action' => 'post',
                'counts' => [$line->id => 20],
            ])
            ->assertSessionHas('error');

        $this->assertSame(StockTakeStatus::Open, $take->fresh()->status);
        $this->assertSame(0, $line->product->movements()->count());
    }

    public function test_a_posted_count_cannot_be_changed_again(): void
    {
        [$take, $line] = $this->openCountFor(systemQuantity: 20);
        $take->forceFill(['status' => StockTakeStatus::Posted])->save();

        $this->actingAs($this->manager)
            ->put(route('stock-takes.update', $take), [
                'action' => 'save',
                'counts' => [$line->id => 1],
            ])
            ->assertSessionHas('error');

        $this->assertNull($line->fresh()->counted_quantity);
    }

    public function test_an_open_count_can_be_cancelled(): void
    {
        [$take] = $this->openCountFor(systemQuantity: 5);

        $this->actingAs($this->manager)
            ->post(route('stock-takes.cancel', $take))
            ->assertRedirect(route('stock-takes.index'));

        $this->assertSame(StockTakeStatus::Cancelled, $take->fresh()->status);
    }

    public function test_a_posted_count_cannot_be_deleted(): void
    {
        $admin = User::factory()->admin()->create();

        [$take] = $this->openCountFor(systemQuantity: 5);
        $take->forceFill(['status' => StockTakeStatus::Posted])->save();

        $this->actingAs($admin)
            ->delete(route('stock-takes.destroy', $take))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('stock_takes', ['id' => $take->id]);
    }

    /**
     * A count sheet with one product on it.
     *
     * @return array{0: StockTake, 1: StockTakeLine}
     */
    private function openCountFor(int $systemQuantity): array
    {
        $take = StockTake::factory()->create(['created_by' => $this->manager->id]);

        $line = StockTakeLine::factory()->create([
            'stock_take_id' => $take->id,
            'product_id' => Product::factory()->create(['quantity' => $systemQuantity])->id,
            'expected_quantity' => $systemQuantity,
            'counted_quantity' => null,
        ]);

        return [$take, $line];
    }
}
