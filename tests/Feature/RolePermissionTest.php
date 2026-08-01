<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\StockTake;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Who is allowed to do what.
 *
 * This doubles as the written answer to "how does a stock clerk work, how does
 * a manager work, what can only an administrator do". If a rule changes, a test
 * here should change with it on purpose, never by accident.
 *
 * The shape of it:
 *
 *   Staff    read everything. Sell at the till, add customers, and move stock
 *            in or out. Cannot overwrite a balance with an Adjustment.
 *   Manager  everything Staff can, plus creating and editing the catalogue,
 *            purchase orders and stock counts, adjusting a balance, and
 *            reversing a sale. Cannot delete.
 *   Admin    everything, plus deleting, restoring and managing user accounts.
 */
class RolePermissionTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        return User::factory()->create();
    }

    private function manager(): User
    {
        return User::factory()->manager()->create();
    }

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    // ---------------------------------------------------------------------
    // Reading
    // ---------------------------------------------------------------------

    public function test_every_role_can_read_the_stock_lists(): void
    {
        Product::factory()->create();

        foreach ([$this->staff(), $this->manager(), $this->admin()] as $user) {
            foreach (['products.index', 'categories.index', 'suppliers.index', 'movements.index', 'purchase-orders.index', 'stock-takes.index', 'reports.low-stock', 'reports.valuation', 'dashboard'] as $page) {
                $this->actingAs($user)
                    ->get(route($page))
                    ->assertOk("{$user->role->value} should be able to read {$page}");
            }
        }
    }

    // ---------------------------------------------------------------------
    // Stock movements: the whole point of a stock clerk's day
    // ---------------------------------------------------------------------

    public function test_staff_can_record_a_stock_movement(): void
    {
        $product = Product::factory()->create(['quantity' => 10, 'is_active' => true]);

        $this->actingAs($this->staff())
            ->post(route('movements.store'), [
                'product_id' => $product->id,
                'type' => 'in',
                'quantity' => 5,
            ])
            ->assertRedirect();

        $this->assertSame(15, $product->fresh()->quantity);
    }

    public function test_staff_can_take_stock_out(): void
    {
        $product = Product::factory()->create(['quantity' => 10]);

        $this->actingAs($this->staff())
            ->post(route('movements.store'), [
                'product_id' => $product->id,
                'type' => 'out',
                'quantity' => 4,
            ])
            ->assertRedirect();

        $this->assertSame(6, $product->fresh()->quantity);
    }

    /**
     * An Adjustment replaces the balance rather than shifting it, so it can make
     * a shortfall vanish. Someone who both sells and can rewrite a balance could
     * take goods and then correct the count to match the shelf.
     */
    public function test_staff_cannot_adjust_a_balance(): void
    {
        $product = Product::factory()->create(['quantity' => 50]);

        $this->actingAs($this->staff())
            ->post(route('movements.store'), [
                'product_id' => $product->id,
                'type' => 'adjustment',
                'quantity' => 40,
            ])
            ->assertForbidden();

        $this->assertSame(50, $product->fresh()->quantity);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_a_manager_can_adjust_a_balance(): void
    {
        $product = Product::factory()->create(['quantity' => 50]);

        $this->actingAs($this->manager())
            ->post(route('movements.store'), [
                'product_id' => $product->id,
                'type' => 'adjustment',
                'quantity' => 40,
            ])
            ->assertRedirect();

        $this->assertSame(40, $product->fresh()->quantity);
    }

    public function test_an_admin_can_adjust_a_balance(): void
    {
        $product = Product::factory()->create(['quantity' => 50]);

        $this->actingAs($this->admin())
            ->post(route('movements.store'), [
                'product_id' => $product->id,
                'type' => 'adjustment',
                'quantity' => 0,
            ])
            ->assertRedirect();

        $this->assertSame(0, $product->fresh()->quantity);
    }

    public function test_the_movement_form_hides_adjustment_from_staff(): void
    {
        Product::factory()->create();

        // Not offered rather than offered and then refused, so nobody is invited
        // to press a button that will fail.
        $this->assertSame(
            ['in', 'out'],
            array_keys($this->actingAs($this->staff())->get(route('movements.create'))->viewData('types')),
        );

        $this->assertSame(
            ['in', 'out', 'adjustment'],
            array_keys($this->actingAs($this->manager())->get(route('movements.create'))->viewData('types')),
        );
    }

    public function test_asking_for_the_adjustment_form_as_staff_falls_back_to_stock_in(): void
    {
        Product::factory()->create();

        $response = $this->actingAs($this->staff())
            ->get(route('movements.create', ['type' => 'adjustment']));

        $response->assertOk();

        // Otherwise the form would open with nothing selected.
        $this->assertSame('in', $response->viewData('type'));
    }

    public function test_staff_can_still_correct_a_mistake_by_moving_stock(): void
    {
        // The point of the restriction is not to block honest work. A staff
        // member who miscounts a delivery can still put it right with a
        // movement, which leaves both entries in the history.
        $product = Product::factory()->create(['quantity' => 0]);
        $staff = $this->staff();

        $this->actingAs($staff)->post(route('movements.store'), [
            'product_id' => $product->id,
            'type' => 'in',
            'quantity' => 12,
        ]);

        $this->actingAs($staff)->post(route('movements.store'), [
            'product_id' => $product->id,
            'type' => 'out',
            'quantity' => 2,
        ]);

        $this->assertSame(10, $product->fresh()->quantity);
        $this->assertSame(2, $product->movements()->count());
    }

    // ---------------------------------------------------------------------
    // Selling: staff sell, managers reverse
    // ---------------------------------------------------------------------

    public function test_every_role_can_reach_the_till_and_sell(): void
    {
        foreach ([$this->staff(), $this->manager(), $this->admin()] as $user) {
            $product = Product::factory()->create(['quantity' => 10, 'sale_price' => 5]);

            $this->actingAs($user)->get(route('pos.index'))->assertOk();

            $this->actingAs($user)
                ->post(route('pos.store'), [
                    'items' => [['product_id' => $product->id, 'quantity' => 2]],
                    'payment_method' => 'cash',
                    'paid' => 10,
                ])
                ->assertRedirect();

            $this->assertSame(8, $product->fresh()->quantity, "{$user->role->value} should be able to sell");
        }
    }

    // ---------------------------------------------------------------------
    // Catalogue: managers and admins only
    // ---------------------------------------------------------------------

    public function test_staff_cannot_reach_the_product_form(): void
    {
        $this->actingAs($this->staff())
            ->get(route('products.create'))
            ->assertForbidden();
    }

    public function test_staff_cannot_create_a_product(): void
    {
        $this->actingAs($this->staff())
            ->post(route('products.store'), $this->productPayload())
            ->assertForbidden();

        $this->assertSame(0, Product::query()->count());
    }

    public function test_a_manager_can_create_a_product(): void
    {
        $this->actingAs($this->manager())
            ->post(route('products.store'), $this->productPayload())
            ->assertRedirect();

        $this->assertSame(1, Product::query()->count());
    }

    public function test_staff_cannot_create_a_category_or_supplier(): void
    {
        $staff = $this->staff();

        $this->actingAs($staff)
            ->post(route('categories.store'), ['name' => 'Drinks', 'is_active' => 1])
            ->assertForbidden();

        $this->actingAs($staff)
            ->post(route('suppliers.store'), ['name' => 'Some Vendor', 'is_active' => 1])
            ->assertForbidden();
    }

    // ---------------------------------------------------------------------
    // Deleting: administrators only
    // ---------------------------------------------------------------------

    public function test_a_manager_cannot_delete_a_product(): void
    {
        $product = Product::factory()->create();

        $this->actingAs($this->manager())
            ->delete(route('products.destroy', $product))
            ->assertForbidden();

        $this->assertNotSoftDeleted($product);
    }

    public function test_an_admin_can_delete_a_product(): void
    {
        $product = Product::factory()->create();

        $this->actingAs($this->admin())
            ->delete(route('products.destroy', $product))
            ->assertRedirect(route('products.index'));

        $this->assertSoftDeleted($product);
    }

    public function test_only_an_admin_can_restore_a_deleted_product(): void
    {
        $product = Product::factory()->create();
        $product->delete();

        $this->actingAs($this->manager())
            ->post(route('products.restore', $product->id))
            ->assertForbidden();

        $this->actingAs($this->admin())
            ->post(route('products.restore', $product->id))
            ->assertRedirect();

        $this->assertNotSoftDeleted($product->fresh());
    }

    // ---------------------------------------------------------------------
    // Purchasing: managers and admins
    // ---------------------------------------------------------------------

    public function test_staff_cannot_open_the_purchase_order_form(): void
    {
        $this->actingAs($this->staff())
            ->get(route('purchase-orders.create'))
            ->assertForbidden();
    }

    public function test_staff_cannot_approve_a_purchase_order(): void
    {
        $order = PurchaseOrder::factory()->create();
        PurchaseOrderLine::factory()->create(['purchase_order_id' => $order->id]);

        $this->actingAs($this->staff())
            ->post(route('purchase-orders.approve', $order))
            ->assertForbidden();
    }

    public function test_a_manager_can_approve_a_purchase_order(): void
    {
        $order = PurchaseOrder::factory()->create();
        PurchaseOrderLine::factory()->create(['purchase_order_id' => $order->id]);

        $this->actingAs($this->manager())
            ->post(route('purchase-orders.approve', $order))
            ->assertRedirect(route('purchase-orders.show', $order));

        $this->assertTrue($order->fresh()->status->isReceivable());
    }

    public function test_staff_cannot_start_a_stock_count(): void
    {
        $this->actingAs($this->staff())
            ->get(route('stock-takes.create'))
            ->assertForbidden();
    }

    public function test_a_manager_cannot_delete_a_stock_count(): void
    {
        $take = StockTake::factory()->create();

        $this->actingAs($this->manager())
            ->delete(route('stock-takes.destroy', $take))
            ->assertForbidden();
    }

    // ---------------------------------------------------------------------
    // User accounts: administrators only
    // ---------------------------------------------------------------------

    public function test_only_an_admin_can_reach_user_management(): void
    {
        $this->actingAs($this->staff())->get(route('users.index'))->assertForbidden();
        $this->actingAs($this->manager())->get(route('users.index'))->assertForbidden();
        $this->actingAs($this->admin())->get(route('users.index'))->assertOk();
    }

    public function test_a_manager_cannot_create_a_user(): void
    {
        $this->actingAs($this->manager())
            ->post(route('users.store'), [
                'name' => 'Sneaky',
                'email' => 'sneaky@example.com',
                'role' => 'admin',
                'is_active' => 1,
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'sneaky@example.com']);
    }

    public function test_an_admin_cannot_remove_their_own_admin_rights(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->put(route('users.update', $admin), [
                'name' => $admin->name,
                'email' => $admin->email,
                'role' => 'staff',
                'is_active' => 1,
            ])
            ->assertSessionHasErrors('role');

        $this->assertTrue($admin->fresh()->isAdmin());
    }

    public function test_an_admin_cannot_delete_their_own_account(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->delete(route('users.destroy', $admin))
            ->assertRedirect(route('users.index'));

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    // ---------------------------------------------------------------------
    // Own account: available to everyone
    // ---------------------------------------------------------------------

    public function test_every_role_can_open_their_own_account_page(): void
    {
        foreach ([$this->staff(), $this->manager(), $this->admin()] as $user) {
            $this->actingAs($user)->get(route('profile.edit'))->assertOk();
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function productPayload(): array
    {
        return [
            'sku' => 'TEST-001',
            'name' => 'Test product',
            'unit' => 'pcs',
            'category_id' => Category::factory()->create()->id,
            'supplier_id' => Supplier::factory()->create()->id,
            'cost_price' => '5.00',
            'sale_price' => '9.00',
            'quantity' => 10,
            'reorder_level' => 3,
            'is_active' => 1,
        ];
    }
}
