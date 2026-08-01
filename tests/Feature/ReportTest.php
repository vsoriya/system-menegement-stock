<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The money figures on the dashboard and the valuation report.
 *
 * These are the numbers a shop owner reads first, so a silent zero is worse
 * than an error page. Both of these queries alias aggregates as stock_value
 * and retail_value, which are also accessor names on the Product model, so
 * they have to stay on the base query builder to keep working.
 */
class ReportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    public function test_the_valuation_report_shows_real_totals_not_zero(): void
    {
        $drinks = Category::factory()->create(['name' => 'Drinks']);

        Product::factory()->create([
            'category_id' => $drinks->id,
            'quantity' => 10,
            'cost_price' => '2.00',
            'sale_price' => '5.00',
        ]);

        Product::factory()->create([
            'category_id' => $drinks->id,
            'quantity' => 3,
            'cost_price' => '4.00',
            'sale_price' => '9.00',
        ]);

        $response = $this->actingAs($this->admin)->get(route('reports.valuation'));

        $response->assertOk();

        // 10 * 2.00 + 3 * 4.00 = 32.00 at cost
        // 10 * 5.00 + 3 * 9.00 = 77.00 at retail
        $response->assertSee('$32.00');
        $response->assertSee('$77.00');

        // The share column divides by the total, so a zero total used to make
        // every row read 0.0 percent.
        $response->assertSee('100.0%');
        $response->assertDontSee('$0.00');
    }

    public function test_the_valuation_report_splits_by_category(): void
    {
        $drinks = Category::factory()->create(['name' => 'Drinks']);
        $snacks = Category::factory()->create(['name' => 'Snacks']);

        Product::factory()->create([
            'category_id' => $drinks->id,
            'quantity' => 5,
            'cost_price' => '10.00',
            'sale_price' => '15.00',
        ]);

        Product::factory()->create([
            'category_id' => $snacks->id,
            'quantity' => 2,
            'cost_price' => '25.00',
            'sale_price' => '30.00',
        ]);

        $response = $this->actingAs($this->admin)->get(route('reports.valuation'));

        $response->assertOk()
            ->assertSee('Drinks')
            ->assertSee('Snacks')
            // 5 * 10.00 and 2 * 25.00, equal halves of a 100.00 total.
            ->assertSee('$50.00')
            ->assertSee('50.0%');
    }

    public function test_the_dashboard_shows_the_stock_value(): void
    {
        Product::factory()->create([
            'quantity' => 8,
            'cost_price' => '3.00',
            'sale_price' => '7.00',
            'reorder_level' => 0,
        ]);

        $response = $this->actingAs($this->admin)->get(route('dashboard'));

        $response->assertOk();

        // The headline figure counts up in JavaScript from a data attribute, so
        // the rendered fallback is what proves the number reached the page.
        // 8 * 3.00 at cost, 8 * 7.00 at retail.
        $response->assertSee('24.00');
        $response->assertSee('56.00');
    }

    public function test_the_low_stock_report_lists_what_needs_reordering(): void
    {
        $needsOrdering = Product::factory()->create([
            'name' => 'Almost gone',
            'quantity' => 2,
            'reorder_level' => 10,
        ]);

        $wellStocked = Product::factory()->create([
            'name' => 'Plenty left',
            'quantity' => 500,
            'reorder_level' => 10,
        ]);

        $response = $this->actingAs($this->admin)->get(route('reports.low-stock'));

        $response->assertOk()
            ->assertSee($needsOrdering->name)
            ->assertDontSee($wellStocked->name);
    }

    /**
     * The ordering here subtracts two unsigned columns, which MySQL refuses
     * outright unless they are promoted first.
     */
    public function test_the_low_stock_report_sorts_the_worst_shortfall_first(): void
    {
        Product::factory()->create(['name' => 'Mild shortfall', 'quantity' => 9, 'reorder_level' => 10]);
        Product::factory()->create(['name' => 'Severe shortfall', 'quantity' => 0, 'reorder_level' => 80]);

        $response = $this->actingAs($this->admin)->get(route('reports.low-stock'));

        $response->assertOk();

        $body = $response->getContent();

        $this->assertLessThan(
            strpos($body, 'Mild shortfall'),
            strpos($body, 'Severe shortfall'),
            'The biggest shortfall should be listed first.'
        );
    }

    public function test_an_out_of_stock_product_still_appears_in_the_reorder_list(): void
    {
        $product = Product::factory()->create([
            'name' => 'Completely out',
            'quantity' => 0,
            'reorder_level' => 5,
        ]);

        $this->actingAs($this->admin)
            ->get(route('reports.low-stock'))
            ->assertOk()
            ->assertSee($product->name);
    }

    public function test_products_with_no_category_are_grouped_together(): void
    {
        Product::factory()->create([
            'category_id' => null,
            'quantity' => 4,
            'cost_price' => '2.50',
            'sale_price' => '5.00',
        ]);

        $this->actingAs($this->admin)
            ->get(route('reports.valuation'))
            ->assertOk()
            ->assertSee('Uncategorised')
            ->assertSee('$10.00');
    }
}
