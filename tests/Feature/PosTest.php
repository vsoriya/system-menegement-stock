<?php

namespace Tests\Feature;

use App\Enums\SaleStatus;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The till as a customer actually uses it: through HTTP, with the form the
 * browser posts. SaleServiceTest already proves the maths; this proves the
 * wiring around it, and who is allowed to do what.
 */
class PosTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->staff = User::factory()->staff()->create();
        $this->manager = User::factory()->manager()->create();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function product(int $quantity = 20, array $attributes = []): Product
    {
        return Product::factory()->create([
            'quantity' => $quantity,
            'cost_price' => 8,
            'sale_price' => 12,
            ...$attributes,
        ]);
    }

    public function test_staff_can_ring_up_a_sale(): void
    {
        $product = $this->product(20);

        $response = $this->actingAs($this->staff)->post(route('pos.store'), [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 3, 'unit_price' => 12],
            ],
            'payment_method' => 'cash',
            'paid' => 40,
        ]);

        $sale = Sale::query()->sole();

        $response->assertRedirect(route('sales.show', $sale));

        $this->assertSame(17, $product->fresh()->quantity);
        $this->assertSame(36.0, (float) $sale->total);
        $this->assertSame(4.0, (float) $sale->change_due);
        $this->assertSame($this->staff->id, $sale->user_id);
    }

    public function test_a_sale_needs_at_least_one_line(): void
    {
        $this->actingAs($this->staff)
            ->post(route('pos.store'), [
                'payment_method' => 'cash',
            ])
            ->assertSessionHasErrors('items');

        $this->assertDatabaseCount('sales', 0);
    }

    public function test_an_inactive_product_cannot_be_sold(): void
    {
        $product = $this->product(20, ['is_active' => false]);

        $this->actingAs($this->staff)
            ->post(route('pos.store'), [
                'items' => [['product_id' => $product->id, 'quantity' => 1]],
                'payment_method' => 'cash',
            ])
            ->assertSessionHasErrors('items.0.product_id');

        $this->assertDatabaseCount('sales', 0);
        $this->assertSame(20, $product->fresh()->quantity);
    }

    public function test_a_deleted_product_cannot_be_sold(): void
    {
        $product = $this->product(20);
        $product->delete();

        $this->actingAs($this->staff)
            ->post(route('pos.store'), [
                'items' => [['product_id' => $product->id, 'quantity' => 1]],
                'payment_method' => 'cash',
            ])
            ->assertSessionHasErrors('items.0.product_id');

        $this->assertDatabaseCount('sales', 0);
    }

    public function test_selling_more_than_the_shelf_holds_is_refused_without_moving_stock(): void
    {
        $product = $this->product(2);

        $this->actingAs($this->staff)
            ->post(route('pos.store'), [
                'items' => [['product_id' => $product->id, 'quantity' => 5]],
                'payment_method' => 'cash',
                'paid' => 100,
            ])
            ->assertSessionHasErrors('items');

        $this->assertSame(2, $product->fresh()->quantity);
        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_paying_too_little_is_refused(): void
    {
        $product = $this->product(20);

        $this->actingAs($this->staff)
            ->post(route('pos.store'), [
                'items' => [['product_id' => $product->id, 'quantity' => 3]],
                'payment_method' => 'cash',
                'paid' => 5,
            ])
            ->assertSessionHasErrors('items');

        $this->assertDatabaseCount('sales', 0);
    }

    public function test_a_sale_can_be_rung_up_against_a_customer(): void
    {
        $product = $this->product(20);
        $customer = Customer::factory()->create();

        $this->actingAs($this->staff)->post(route('pos.store'), [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'customer_id' => $customer->id,
            'payment_method' => 'transfer',
        ]);

        $this->assertSame($customer->id, Sale::query()->sole()->customer_id);
    }

    public function test_staff_cannot_reverse_a_sale(): void
    {
        $product = $this->product(20);

        $this->actingAs($this->staff)->post(route('pos.store'), [
            'items' => [['product_id' => $product->id, 'quantity' => 4]],
            'payment_method' => 'cash',
            'paid' => 48,
        ]);

        $sale = Sale::query()->sole();

        $this->actingAs($this->staff)
            ->post(route('sales.void', $sale), ['reason' => 'Changed my mind'])
            ->assertForbidden();

        // Nothing moved, so a cashier cannot quietly undo their own takings.
        $this->assertSame(SaleStatus::Completed, $sale->fresh()->status);
        $this->assertSame(16, $product->fresh()->quantity);
    }

    public function test_a_manager_can_reverse_a_sale_and_the_stock_comes_back(): void
    {
        $product = $this->product(20);

        $this->actingAs($this->staff)->post(route('pos.store'), [
            'items' => [['product_id' => $product->id, 'quantity' => 4]],
            'payment_method' => 'cash',
            'paid' => 48,
        ]);

        $sale = Sale::query()->sole();

        $this->actingAs($this->manager)
            ->post(route('sales.void', $sale), ['reason' => 'Customer returned it'])
            ->assertRedirect(route('sales.show', $sale));

        $this->assertSame(SaleStatus::Voided, $sale->fresh()->status);
        $this->assertSame(20, $product->fresh()->quantity);
    }

    public function test_a_guest_cannot_reach_the_till(): void
    {
        $this->get(route('pos.index'))->assertRedirect(route('login'));
        $this->post(route('pos.store'), [])->assertRedirect(route('login'));
        $this->get(route('sales.index'))->assertRedirect(route('login'));
        $this->get(route('customers.index'))->assertRedirect(route('login'));
    }

    public function test_staff_can_add_a_customer_from_the_till(): void
    {
        $response = $this->actingAs($this->staff)->post(route('customers.store'), [
            'name' => 'Sok Dara',
            'phone' => '012 345 678',
            'from_pos' => '1',
        ]);

        $customer = Customer::query()->sole();

        // Sent back to the till with the new customer already chosen, rather
        // than stranded on a detail page mid-sale.
        $response->assertRedirect(route('pos.index', ['customer' => $customer->id]));
        $this->assertSame('Sok Dara', $customer->name);
    }

    public function test_only_admins_can_delete_a_customer(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($this->manager)
            ->delete(route('customers.destroy', $customer))
            ->assertForbidden();

        $this->actingAs(User::factory()->admin()->create())
            ->delete(route('customers.destroy', $customer))
            ->assertRedirect(route('customers.index'));

        $this->assertSoftDeleted($customer);
    }

    /**
     * Asserts the actual figures, not just a 200. A page can render perfectly
     * while every number on it is wrong, which is how the dashboard once showed
     * a stock value of zero for weeks without anyone noticing.
     */
    public function test_the_sales_list_reports_the_real_takings(): void
    {
        $product = $this->product(20);

        $this->actingAs($this->staff)->post(route('pos.store'), [
            'items' => [['product_id' => $product->id, 'quantity' => 3]],
            'payment_method' => 'cash',
            'paid' => 40,
        ]);

        $response = $this->actingAs($this->manager)->get(route('sales.index'));

        $response->assertOk();

        // Three units at 12.00 is 36.00 taken, against 8.00 cost each.
        $this->assertSame(1, $response->viewData('today')['sales_count']);
        $this->assertSame(36.0, $response->viewData('today')['revenue']);
        $this->assertSame(12.0, $response->viewData('today')['profit']);
        $this->assertSame(1, $response->viewData('salesCount'));
        $this->assertSame(36.0, $response->viewData('revenue'));
    }

    public function test_a_voided_sale_is_left_out_of_the_listed_takings(): void
    {
        $product = $this->product(20);

        $this->actingAs($this->staff)->post(route('pos.store'), [
            'items' => [['product_id' => $product->id, 'quantity' => 3]],
            'payment_method' => 'cash',
            'paid' => 40,
        ]);

        $this->actingAs($this->manager)->post(route('sales.void', Sale::query()->sole()));

        $response = $this->actingAs($this->manager)->get(route('sales.index'));

        $this->assertSame(0, $response->viewData('today')['sales_count']);
        $this->assertSame(0.0, $response->viewData('today')['revenue']);
        $this->assertSame(0.0, $response->viewData('revenue'));
    }

    /**
     * The browser filters the grid by comparing these ids with === against
     * numbers read from the category buttons. MySQL hands integer columns back
     * as strings, so an uncast id matched nothing and picking any category
     * showed an empty grid. SQLite returns real integers, which is exactly why
     * the suite was green while the running app was broken.
     */
    public function test_the_till_payload_uses_numbers_for_ids(): void
    {
        $category = Category::factory()->create(['name' => 'Drink']);
        $product = $this->product(10, ['category_id' => $category->id]);

        $tile = $this->actingAs($this->staff)
            ->get(route('pos.index'))
            ->viewData('products')
            ->firstWhere('id', $product->id);

        $this->assertNotNull($tile);
        $this->assertIsInt($tile['id']);
        $this->assertIsInt($tile['category_id']);
        $this->assertSame($category->id, $tile['category_id']);
        $this->assertIsInt($tile['stock']);
        $this->assertIsFloat($tile['price']);
        $this->assertIsString($tile['barcode']);
    }

    public function test_a_product_with_no_category_still_reaches_the_till(): void
    {
        $product = $this->product(10, ['category_id' => null]);

        $tile = $this->actingAs($this->staff)
            ->get(route('pos.index'))
            ->viewData('products')
            ->firstWhere('id', $product->id);

        // Null rather than zero, so the category filter leaves it out while the
        // "all" view still shows it.
        $this->assertNotNull($tile);
        $this->assertNull($tile['category_id']);
    }

    public function test_the_till_lists_the_categories_products_can_be_filtered_by(): void
    {
        $drink = Category::factory()->create(['name' => 'Drink']);
        $this->product(10, ['category_id' => $drink->id]);

        $categories = $this->actingAs($this->staff)
            ->get(route('pos.index'))
            ->viewData('categories');

        $this->assertTrue($categories->contains('name', 'Drink'));
    }

    public function test_the_till_only_offers_products_that_can_be_sold(): void
    {
        $sellable = $this->product(5, ['name' => 'Sellable widget']);
        $withdrawn = $this->product(5, ['name' => 'Withdrawn widget', 'is_active' => false]);

        $response = $this->actingAs($this->staff)->get(route('pos.index'));

        $response->assertOk();
        $response->assertSee($sellable->name);
        $response->assertDontSee($withdrawn->name);
    }
}
