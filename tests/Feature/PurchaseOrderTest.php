<?php

namespace Tests\Feature;

use App\Enums\PurchaseOrderStatus;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The purchasing flow end to end: draft, approved, received.
 *
 * The rule that matters commercially is that ordering stock must not change the
 * quantity on hand. Only receiving a delivery does that.
 */
class PurchaseOrderTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = User::factory()->manager()->create();
        $this->supplier = Supplier::factory()->create();
    }

    public function test_a_draft_order_is_created_with_its_lines(): void
    {
        $productA = Product::factory()->create(['quantity' => 0]);
        $productB = Product::factory()->create(['quantity' => 0]);

        $response = $this->actingAs($this->manager)->post(route('purchase-orders.store'), [
            'supplier_id' => $this->supplier->id,
            'ordered_at' => '2026-08-01',
            'expected_at' => '2026-08-10',
            'notes' => 'Monthly restock',
            'lines' => [
                ['product_id' => $productA->id, 'quantity_ordered' => 10, 'unit_cost' => '2.50'],
                ['product_id' => $productB->id, 'quantity_ordered' => 4, 'unit_cost' => '7.00'],
            ],
        ]);

        $response->assertRedirect();

        $order = PurchaseOrder::query()->latest('id')->firstOrFail();

        $this->assertSame(PurchaseOrderStatus::Draft, $order->status);
        $this->assertSame(2, $order->lines()->count());
        $this->assertSame($this->manager->id, $order->created_by);

        // 10 * 2.50 + 4 * 7.00
        $this->assertSame(53.0, $order->subtotal);

        // Ordering must not touch stock.
        $this->assertSame(0, $productA->fresh()->quantity);
        $this->assertSame(0, $productB->fresh()->quantity);
    }

    public function test_the_order_number_is_generated_and_sequential(): void
    {
        $product = Product::factory()->create();

        foreach ([1, 2] as $ignored) {
            $this->actingAs($this->manager)->post(route('purchase-orders.store'), [
                'supplier_id' => $this->supplier->id,
                'ordered_at' => '2026-08-01',
                'lines' => [
                    ['product_id' => $product->id, 'quantity_ordered' => 1, 'unit_cost' => '1.00'],
                ],
            ]);
        }

        $numbers = PurchaseOrder::query()->orderBy('id')->pluck('number')->all();

        $this->assertSame('PO-'.now()->year.'-0001', $numbers[0]);
        $this->assertSame('PO-'.now()->year.'-0002', $numbers[1]);
    }

    public function test_the_same_product_cannot_be_added_twice(): void
    {
        $product = Product::factory()->create();

        $this->actingAs($this->manager)
            ->post(route('purchase-orders.store'), [
                'supplier_id' => $this->supplier->id,
                'ordered_at' => '2026-08-01',
                'lines' => [
                    ['product_id' => $product->id, 'quantity_ordered' => 1, 'unit_cost' => '1.00'],
                    ['product_id' => $product->id, 'quantity_ordered' => 2, 'unit_cost' => '1.00'],
                ],
            ])
            ->assertSessionHasErrors();

        $this->assertSame(0, PurchaseOrder::query()->count());
    }

    public function test_an_order_with_no_lines_is_refused(): void
    {
        $this->actingAs($this->manager)
            ->post(route('purchase-orders.store'), [
                'supplier_id' => $this->supplier->id,
                'ordered_at' => '2026-08-01',
            ])
            ->assertSessionHasErrors('lines');
    }

    public function test_an_expected_date_before_the_order_date_is_refused(): void
    {
        $product = Product::factory()->create();

        $this->actingAs($this->manager)
            ->post(route('purchase-orders.store'), [
                'supplier_id' => $this->supplier->id,
                'ordered_at' => '2026-08-10',
                'expected_at' => '2026-08-01',
                'lines' => [
                    ['product_id' => $product->id, 'quantity_ordered' => 1, 'unit_cost' => '1.00'],
                ],
            ])
            ->assertSessionHasErrors('expected_at');
    }

    public function test_approving_locks_the_order_and_stamps_who_approved_it(): void
    {
        $order = $this->draftOrderWith(quantity: 5);

        $this->actingAs($this->manager)
            ->post(route('purchase-orders.approve', $order))
            ->assertRedirect(route('purchase-orders.show', $order));

        $order->refresh();

        $this->assertSame(PurchaseOrderStatus::Approved, $order->status);
        $this->assertSame($this->manager->id, $order->approved_by);
        $this->assertNotNull($order->approved_at);
        $this->assertFalse($order->status->isEditable());
    }

    public function test_an_empty_order_cannot_be_approved(): void
    {
        $order = PurchaseOrder::factory()->create(['supplier_id' => $this->supplier->id]);

        $this->actingAs($this->manager)
            ->post(route('purchase-orders.approve', $order))
            ->assertSessionHas('error');

        $this->assertSame(PurchaseOrderStatus::Draft, $order->fresh()->status);
    }

    public function test_an_approved_order_can_no_longer_be_edited(): void
    {
        $order = $this->draftOrderWith(quantity: 5);
        $order->forceFill(['status' => PurchaseOrderStatus::Approved])->save();

        $this->actingAs($this->manager)
            ->get(route('purchase-orders.edit', $order))
            ->assertRedirect(route('purchase-orders.show', $order));
    }

    public function test_receiving_a_delivery_in_full_adds_the_stock(): void
    {
        $order = $this->draftOrderWith(quantity: 12, startingStock: 3);
        $order->forceFill(['status' => PurchaseOrderStatus::Approved])->save();

        $line = $order->lines()->firstOrFail();

        $this->actingAs($this->manager)
            ->post(route('purchase-orders.receive.store', $order), [
                'receipts' => [$line->id => 12],
            ])
            ->assertRedirect(route('purchase-orders.show', $order));

        $order->refresh();

        $this->assertSame(PurchaseOrderStatus::Received, $order->status);
        $this->assertNotNull($order->received_at);
        $this->assertSame(12, $line->fresh()->quantity_received);

        // 3 already on hand plus the 12 that arrived.
        $this->assertSame(15, $line->product->fresh()->quantity);
    }

    public function test_a_partial_delivery_leaves_the_order_open(): void
    {
        $order = $this->draftOrderWith(quantity: 10, startingStock: 0);
        $order->forceFill(['status' => PurchaseOrderStatus::Approved])->save();

        $line = $order->lines()->firstOrFail();

        $this->actingAs($this->manager)
            ->post(route('purchase-orders.receive.store', $order), [
                'receipts' => [$line->id => 4],
            ])
            ->assertRedirect();

        $order->refresh();

        // Still approved, because six units are outstanding.
        $this->assertSame(PurchaseOrderStatus::Approved, $order->status);
        $this->assertSame(4, $line->fresh()->quantity_received);
        $this->assertSame(6, $line->fresh()->outstanding);
        $this->assertSame(4, $line->product->fresh()->quantity);

        // The rest arrives later and closes the order.
        $this->actingAs($this->manager)
            ->post(route('purchase-orders.receive.store', $order), [
                'receipts' => [$line->id => 6],
            ])
            ->assertRedirect();

        $this->assertSame(PurchaseOrderStatus::Received, $order->fresh()->status);
        $this->assertSame(10, $line->product->fresh()->quantity);
    }

    public function test_receiving_more_than_was_ordered_is_refused(): void
    {
        $order = $this->draftOrderWith(quantity: 5, startingStock: 0);
        $order->forceFill(['status' => PurchaseOrderStatus::Approved])->save();

        $line = $order->lines()->firstOrFail();

        $this->actingAs($this->manager)
            ->post(route('purchase-orders.receive.store', $order), [
                'receipts' => [$line->id => 6],
            ])
            ->assertSessionHasErrors();

        $this->assertSame(0, $line->fresh()->quantity_received);
        $this->assertSame(0, $line->product->fresh()->quantity);
    }

    public function test_receiving_nothing_at_all_is_refused(): void
    {
        $order = $this->draftOrderWith(quantity: 5, startingStock: 0);
        $order->forceFill(['status' => PurchaseOrderStatus::Approved])->save();

        $line = $order->lines()->firstOrFail();

        $this->actingAs($this->manager)
            ->post(route('purchase-orders.receive.store', $order), [
                'receipts' => [$line->id => 0],
            ])
            ->assertSessionHasErrors('receipts');
    }

    public function test_receiving_writes_a_traceable_stock_movement(): void
    {
        $order = $this->draftOrderWith(quantity: 8, startingStock: 0);
        $order->forceFill(['status' => PurchaseOrderStatus::Approved])->save();

        $line = $order->lines()->firstOrFail();

        $this->actingAs($this->manager)->post(route('purchase-orders.receive.store', $order), [
            'receipts' => [$line->id => 8],
            'note' => 'Left at the back door',
        ]);

        $movement = $line->product->movements()->firstOrFail();

        $this->assertStringContainsString($order->number, (string) $movement->reference);
        $this->assertSame('Left at the back door', $movement->note);
        $this->assertSame($this->manager->id, $movement->user_id);
    }

    public function test_a_draft_order_can_be_cancelled(): void
    {
        $order = $this->draftOrderWith(quantity: 3);

        $this->actingAs($this->manager)
            ->post(route('purchase-orders.cancel', $order))
            ->assertRedirect(route('purchase-orders.show', $order));

        $this->assertSame(PurchaseOrderStatus::Cancelled, $order->fresh()->status);
    }

    public function test_a_received_order_cannot_be_cancelled(): void
    {
        $order = $this->draftOrderWith(quantity: 3);
        $order->forceFill(['status' => PurchaseOrderStatus::Received])->save();

        $this->actingAs($this->manager)
            ->post(route('purchase-orders.cancel', $order))
            ->assertSessionHas('error');

        $this->assertSame(PurchaseOrderStatus::Received, $order->fresh()->status);
    }

    public function test_an_order_that_received_stock_cannot_be_deleted(): void
    {
        $admin = User::factory()->admin()->create();

        $order = $this->draftOrderWith(quantity: 5);
        $order->lines()->firstOrFail()->forceFill(['quantity_received' => 2])->save();

        $this->actingAs($admin)
            ->delete(route('purchase-orders.destroy', $order))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('purchase_orders', ['id' => $order->id]);
    }

    public function test_an_untouched_order_can_be_deleted_with_its_lines(): void
    {
        $admin = User::factory()->admin()->create();
        $order = $this->draftOrderWith(quantity: 5);

        $this->actingAs($admin)
            ->delete(route('purchase-orders.destroy', $order))
            ->assertRedirect(route('purchase-orders.index'));

        $this->assertDatabaseMissing('purchase_orders', ['id' => $order->id]);
        $this->assertDatabaseMissing('purchase_order_lines', ['purchase_order_id' => $order->id]);
    }

    private function draftOrderWith(int $quantity, int $startingStock = 0): PurchaseOrder
    {
        $order = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'created_by' => $this->manager->id,
        ]);

        PurchaseOrderLine::factory()->create([
            'purchase_order_id' => $order->id,
            'product_id' => Product::factory()->create(['quantity' => $startingStock])->id,
            'quantity_ordered' => $quantity,
            'quantity_received' => 0,
            'unit_cost' => '3.00',
        ]);

        return $order;
    }
}
