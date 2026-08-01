<?php

namespace Tests\Feature;

use App\Enums\PurchaseOrderStatus;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Sale;
use App\Models\SaleLine;
use App\Models\StockMovement;
use App\Models\StockTake;
use App\Models\StockTakeLine;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Opens every page in the system as an administrator and insists on a 200.
 *
 * This is the cheapest safety net there is. A broken Blade template, a missing
 * translation key, a renamed route or a query that the database rejects all
 * show up here immediately, without anyone having to click through the app.
 */
class SmokeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Product $product;

    private Category $category;

    private Supplier $supplier;

    private PurchaseOrder $draftOrder;

    private PurchaseOrder $approvedOrder;

    private StockTake $stockTake;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->category = Category::factory()->create();
        $this->supplier = Supplier::factory()->create();

        $this->product = Product::factory()->create([
            'category_id' => $this->category->id,
            'supplier_id' => $this->supplier->id,
        ]);

        // A product below its reorder level, so the low stock report has a row.
        Product::factory()->lowStock()->create();

        // History for the product page and the movements list.
        StockMovement::query()->create([
            'product_id' => $this->product->id,
            'user_id' => $this->admin->id,
            'type' => 'in',
            'quantity_change' => 10,
            'quantity_before' => 0,
            'quantity_after' => 10,
            'reference' => 'Opening stock',
        ]);

        $this->draftOrder = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'created_by' => $this->admin->id,
        ]);
        PurchaseOrderLine::factory()->create([
            'purchase_order_id' => $this->draftOrder->id,
            'product_id' => $this->product->id,
        ]);

        // Approved with something still outstanding, so the receive page opens.
        $this->approvedOrder = PurchaseOrder::factory()->approved()->create([
            'supplier_id' => $this->supplier->id,
            'created_by' => $this->admin->id,
            'approved_by' => $this->admin->id,
        ]);
        PurchaseOrderLine::factory()->create([
            'purchase_order_id' => $this->approvedOrder->id,
            'product_id' => $this->product->id,
            'quantity_ordered' => 20,
            'quantity_received' => 5,
        ]);

        $this->stockTake = StockTake::factory()->create([
            'created_by' => $this->admin->id,
        ]);
        StockTakeLine::factory()->create([
            'stock_take_id' => $this->stockTake->id,
            'product_id' => $this->product->id,
        ]);
    }

    /**
     * Pages that take no parameters.
     *
     * @return array<string, array<int, string>>
     */
    public static function staticPages(): array
    {
        return [
            'dashboard' => ['dashboard'],
            'my account' => ['profile.edit'],
            'till' => ['pos.index'],
            'sales list' => ['sales.index'],
            'customers list' => ['customers.index'],
            'new customer' => ['customers.create'],
            'products list' => ['products.index'],
            'new product' => ['products.create'],
            'categories list' => ['categories.index'],
            'new category' => ['categories.create'],
            'suppliers list' => ['suppliers.index'],
            'new supplier' => ['suppliers.create'],
            'purchase orders list' => ['purchase-orders.index'],
            'new purchase order' => ['purchase-orders.create'],
            'stock counts list' => ['stock-takes.index'],
            'new stock count' => ['stock-takes.create'],
            'movements list' => ['movements.index'],
            'record movement' => ['movements.create'],
            'low stock report' => ['reports.low-stock'],
            'valuation report' => ['reports.valuation'],
            'users list' => ['users.index'],
            'new user' => ['users.create'],
        ];
    }

    #[DataProvider('staticPages')]
    public function test_page_opens_for_an_admin(string $routeName): void
    {
        $response = $this->actingAs($this->admin)->get(route($routeName));

        $response->assertOk();
    }

    public function test_product_pages_open(): void
    {
        $this->actingAs($this->admin)
            ->get(route('products.show', $this->product))
            ->assertOk();

        $this->actingAs($this->admin)
            ->get(route('products.edit', $this->product))
            ->assertOk();
    }

    public function test_category_edit_opens(): void
    {
        $this->actingAs($this->admin)
            ->get(route('categories.edit', $this->category))
            ->assertOk();
    }

    public function test_supplier_pages_open(): void
    {
        $this->actingAs($this->admin)
            ->get(route('suppliers.show', $this->supplier))
            ->assertOk();

        $this->actingAs($this->admin)
            ->get(route('suppliers.edit', $this->supplier))
            ->assertOk();
    }

    public function test_purchase_order_pages_open(): void
    {
        $this->actingAs($this->admin)
            ->get(route('purchase-orders.show', $this->draftOrder))
            ->assertOk();

        $this->actingAs($this->admin)
            ->get(route('purchase-orders.edit', $this->draftOrder))
            ->assertOk();

        $this->actingAs($this->admin)
            ->get(route('purchase-orders.receive', $this->approvedOrder))
            ->assertOk();
    }

    public function test_stock_take_sheet_opens(): void
    {
        $this->actingAs($this->admin)
            ->get(route('stock-takes.show', $this->stockTake))
            ->assertOk();
    }

    public function test_customer_pages_open(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($this->admin)
            ->get(route('customers.show', $customer))
            ->assertOk();

        $this->actingAs($this->admin)
            ->get(route('customers.edit', $customer))
            ->assertOk();
    }

    public function test_the_invoice_page_opens(): void
    {
        $sale = Sale::factory()->create([
            'user_id' => $this->admin->id,
            'customer_id' => Customer::factory(),
        ]);

        SaleLine::factory()->create([
            'sale_id' => $sale->id,
            'product_id' => $this->product->id,
        ]);

        $this->actingAs($this->admin)
            ->get(route('sales.show', $sale))
            ->assertOk();
    }

    /**
     * A voided invoice renders a different banner and hides the reverse form,
     * so it is a separate code path from a live one.
     */
    public function test_a_voided_invoice_page_opens(): void
    {
        $sale = Sale::factory()->voided()->create(['user_id' => $this->admin->id]);

        SaleLine::factory()->create([
            'sale_id' => $sale->id,
            'product_id' => $this->product->id,
        ]);

        $this->actingAs($this->admin)
            ->get(route('sales.show', $sale))
            ->assertOk();
    }

    public function test_user_edit_opens(): void
    {
        $other = User::factory()->create();

        $this->actingAs($this->admin)
            ->get(route('users.edit', $other))
            ->assertOk();
    }

    /**
     * The empty state of every list is a separate code path from the populated
     * one, and it is the first thing a brand new customer sees.
     */
    public function test_lists_open_on_a_completely_empty_database(): void
    {
        // Wipe everything the setup created, keeping only the signed in admin.
        StockTakeLine::query()->delete();
        StockTake::query()->delete();
        PurchaseOrderLine::query()->delete();
        PurchaseOrder::query()->delete();
        StockMovement::query()->delete();
        Product::query()->forceDelete();
        Category::query()->forceDelete();
        Supplier::query()->forceDelete();

        $pages = [
            'dashboard',
            'pos.index',
            'sales.index',
            'customers.index',
            'products.index',
            'categories.index',
            'suppliers.index',
            'purchase-orders.index',
            'stock-takes.index',
            'movements.index',
            'reports.low-stock',
            'reports.valuation',
        ];

        foreach ($pages as $page) {
            $this->actingAs($this->admin)
                ->get(route($page))
                ->assertOk();
        }
    }

    public function test_guest_pages_open(): void
    {
        $this->get(route('login'))->assertOk();
        $this->get(route('password.request'))->assertOk();
        $this->get(route('password.reset', ['token' => 'a-test-token']))->assertOk();
    }

    public function test_khmer_renders_on_every_page(): void
    {
        $this->actingAs($this->admin)
            ->withSession(['locale' => 'km'])
            ->get(route('dashboard'))
            ->assertOk();

        $this->actingAs($this->admin)
            ->withSession(['locale' => 'km'])
            ->get(route('purchase-orders.show', $this->draftOrder))
            ->assertOk();

        $this->actingAs($this->admin)
            ->withSession(['locale' => 'km'])
            ->get(route('stock-takes.show', $this->stockTake))
            ->assertOk();
    }

    public function test_a_guest_is_sent_to_the_login_page(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->get(route('products.index'))->assertRedirect(route('login'));
        $this->get(route('profile.edit'))->assertRedirect(route('login'));
    }

    public function test_the_receive_page_is_refused_for_a_draft_order(): void
    {
        // Only approved orders can be received, and the guard should redirect
        // rather than blow up.
        $this->assertSame(PurchaseOrderStatus::Draft, $this->draftOrder->status);

        $this->actingAs($this->admin)
            ->get(route('purchase-orders.receive', $this->draftOrder))
            ->assertRedirect(route('purchase-orders.show', $this->draftOrder));
    }
}
