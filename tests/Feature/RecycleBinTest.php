<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Sale;
use App\Models\SaleLine;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The recycle bin.
 *
 * The restore routes shipped from the start but no page in the app reached them,
 * so a deletion made by mistake could only be undone with a database client.
 * These tests cover the round trip now that the buttons exist.
 */
class RecycleBinTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->manager = User::factory()->manager()->create();
    }

    public function test_the_bin_is_offered_once_something_has_been_deleted(): void
    {
        $binUrl = route('products.index', ['trashed' => 1]);

        // Nothing deleted, so there is nothing to offer.
        $this->actingAs($this->admin)
            ->get(route('products.index'))
            ->assertOk()
            ->assertDontSee($binUrl, false);

        Product::factory()->create()->delete();

        $this->actingAs($this->admin)
            ->get(route('products.index'))
            ->assertOk()
            ->assertSee($binUrl, false);
    }

    public function test_only_an_admin_is_offered_the_bin(): void
    {
        Product::factory()->create()->delete();

        $this->actingAs($this->manager)
            ->get(route('products.index'))
            ->assertOk()
            ->assertDontSee(route('products.index', ['trashed' => 1]), false);
    }

    public function test_the_bin_lists_deleted_products_and_nothing_live(): void
    {
        $live = Product::factory()->create(['name' => 'Still On Sale']);
        $deleted = Product::factory()->create(['name' => 'Withdrawn Item']);
        $deleted->delete();

        $response = $this->actingAs($this->admin)->get(route('products.index', ['trashed' => 1]));

        $response->assertOk();
        $response->assertSee('Withdrawn Item');
        $response->assertDontSee('Still On Sale');

        // Route binding skips deleted records, so the name must not be a link to
        // a page that would answer 404. Matched on the href attribute, because
        // the restore form's own action contains the show URL as a prefix.
        $response->assertDontSee('href="'.route('products.show', $deleted->id).'"', false);
        $this->assertTrue($response->viewData('trashed'));
    }

    public function test_a_manager_asking_for_the_bin_just_gets_the_normal_list(): void
    {
        $deleted = Product::factory()->create(['name' => 'Withdrawn Item']);
        $deleted->delete();

        $response = $this->actingAs($this->manager)->get(route('products.index', ['trashed' => 1]));

        $response->assertOk();
        $this->assertFalse($response->viewData('trashed'));
        $response->assertDontSee('Withdrawn Item');
    }

    public function test_a_deleted_product_can_be_restored(): void
    {
        $product = Product::factory()->create(['quantity' => 12]);
        $product->delete();

        $this->actingAs($this->admin)
            ->post(route('products.restore', $product->id))
            ->assertRedirect(route('products.index', ['trashed' => 1]));

        $this->assertNotSoftDeleted($product);

        // The quantity on hand comes back with it, untouched.
        $this->assertSame(12, $product->fresh()->quantity);
    }

    public function test_a_deleted_category_and_supplier_can_be_restored(): void
    {
        $category = Category::factory()->create();
        $supplier = Supplier::factory()->create();
        $category->delete();
        $supplier->delete();

        $this->actingAs($this->admin)
            ->post(route('categories.restore', $category->id))
            ->assertRedirect(route('categories.index', ['trashed' => 1]));

        $this->actingAs($this->admin)
            ->post(route('suppliers.restore', $supplier->id))
            ->assertRedirect(route('suppliers.index', ['trashed' => 1]));

        $this->assertNotSoftDeleted($category);
        $this->assertNotSoftDeleted($supplier);
    }

    public function test_a_manager_cannot_restore_anything(): void
    {
        $product = Product::factory()->create();
        $product->delete();

        $this->actingAs($this->manager)
            ->post(route('products.restore', $product->id))
            ->assertForbidden();

        $this->assertSoftDeleted($product);
    }

    public function test_a_never_traded_product_can_be_purged_for_good(): void
    {
        $product = Product::factory()->create();
        $product->delete();

        $this->actingAs($this->admin)
            ->delete(route('products.force-delete', $product->id))
            ->assertRedirect(route('products.index', ['trashed' => 1]));

        $this->assertDatabaseCount('products', 0);
    }

    /**
     * Both purchase_order_lines and sale_lines restrict deletion at the database
     * level, so without this guard the purge came back as a raw SQL error page.
     */
    public function test_a_product_on_a_purchase_order_cannot_be_purged(): void
    {
        $product = Product::factory()->create();

        $order = PurchaseOrder::factory()->create(['created_by' => $this->admin->id]);
        PurchaseOrderLine::factory()->create([
            'purchase_order_id' => $order->id,
            'product_id' => $product->id,
        ]);

        $product->delete();

        $this->actingAs($this->admin)
            ->delete(route('products.force-delete', $product->id))
            ->assertRedirect(route('products.index', ['trashed' => 1]))
            ->assertSessionHasErrors('product');

        // Still there, still deleted rather than purged.
        $this->assertSoftDeleted($product);
        $this->assertDatabaseCount('products', 1);
    }

    public function test_a_product_on_an_invoice_cannot_be_purged(): void
    {
        $product = Product::factory()->create();

        $sale = Sale::factory()->create(['user_id' => $this->admin->id]);
        SaleLine::factory()->create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
        ]);

        $product->delete();

        $this->actingAs($this->admin)
            ->delete(route('products.force-delete', $product->id))
            ->assertSessionHasErrors('product');

        $this->assertSoftDeleted($product);
    }

    public function test_a_manager_cannot_purge_a_product(): void
    {
        $product = Product::factory()->create();
        $product->delete();

        $this->actingAs($this->manager)
            ->delete(route('products.force-delete', $product->id))
            ->assertForbidden();

        $this->assertDatabaseCount('products', 1);
    }

    public function test_filtering_inside_the_bin_stays_inside_the_bin(): void
    {
        $wanted = Product::factory()->create(['name' => 'Deleted Widget']);
        $other = Product::factory()->create(['name' => 'Deleted Gadget']);
        $wanted->delete();
        $other->delete();

        $response = $this->actingAs($this->admin)->get(route('products.index', [
            'trashed' => 1,
            'search' => 'Widget',
        ]));

        $response->assertOk();
        $this->assertTrue($response->viewData('trashed'));
        $response->assertSee('Deleted Widget');
        $response->assertDontSee('Deleted Gadget');
    }

    public function test_the_empty_bin_still_renders(): void
    {
        // Something has to be deleted for the bin to be reachable, so delete
        // one record and then filter it out.
        Product::factory()->create(['name' => 'Deleted Widget'])->delete();

        $this->actingAs($this->admin)
            ->get(route('products.index', ['trashed' => 1, 'search' => 'nothing-matches-this']))
            ->assertOk()
            ->assertSee(__('app.common.recycle_bin_empty'));
    }

    public function test_the_category_and_supplier_bins_render(): void
    {
        Category::factory()->create(['name' => 'Deleted Category'])->delete();
        Supplier::factory()->create(['name' => 'Deleted Supplier'])->delete();

        $this->actingAs($this->admin)
            ->get(route('categories.index', ['trashed' => 1]))
            ->assertOk()
            ->assertSee('Deleted Category');

        $this->actingAs($this->admin)
            ->get(route('suppliers.index', ['trashed' => 1]))
            ->assertOk()
            ->assertSee('Deleted Supplier');
    }
}
