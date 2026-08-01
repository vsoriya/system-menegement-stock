<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Customers, and the figures shown next to them.
 *
 * The spend column is a SQL aggregate with an alias, which is the same shape as
 * the bug that once made the whole dashboard read zero. So these tests assert
 * the money, not the status code.
 */
class CustomerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->staff = User::factory()->staff()->create();
    }

    private function saleFor(?Customer $customer, float $total, bool $voided = false): Sale
    {
        $factory = $voided ? Sale::factory()->voided() : Sale::factory();

        return $factory->create([
            'customer_id' => $customer?->id,
            'user_id' => $this->staff->id,
            'subtotal' => $total,
            'total' => $total,
            'paid' => $total,
            'discount' => 0,
            'change_due' => 0,
        ]);
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function rowFor(Customer $customer, array $query = []): ?Customer
    {
        return $this->actingAs($this->admin)
            ->get(route('customers.index', $query))
            ->viewData('customers')
            ->firstWhere('id', $customer->id);
    }

    public function test_the_list_totals_what_each_customer_has_spent(): void
    {
        $customer = Customer::factory()->create();

        $this->saleFor($customer, 24.50);
        $this->saleFor($customer, 15.50);

        // Another customer's spending must not leak into this row.
        $this->saleFor(Customer::factory()->create(), 500);

        $row = $this->rowFor($customer);

        $this->assertSame(40.0, (float) $row->spent_total);
        $this->assertSame(2, (int) $row->completed_sales_count);
    }

    public function test_reversed_sales_do_not_count_towards_spending(): void
    {
        $customer = Customer::factory()->create();

        $this->saleFor($customer, 30);
        $this->saleFor($customer, 70, voided: true);

        $row = $this->rowFor($customer);

        $this->assertSame(30.0, (float) $row->spent_total);
        $this->assertSame(1, (int) $row->completed_sales_count);
    }

    public function test_a_customer_with_no_sales_shows_nothing_spent(): void
    {
        $customer = Customer::factory()->create();

        $row = $this->rowFor($customer);

        // SUM over no rows is null, and the view has to cope with that rather
        // than printing an empty amount.
        $this->assertSame(0.0, (float) ($row->spent_total ?? 0));
        $this->assertSame(0, (int) $row->completed_sales_count);
    }

    public function test_the_detail_page_totals_spending_too(): void
    {
        $customer = Customer::factory()->create();

        $this->saleFor($customer, 12);
        $this->saleFor($customer, 8);
        $this->saleFor($customer, 100, voided: true);

        $response = $this->actingAs($this->admin)->get(route('customers.show', $customer));

        $response->assertOk();

        // total_spent goes through the model accessor rather than the list
        // aggregate, so it is worth checking separately.
        $this->assertSame(20.0, $response->viewData('customer')->total_spent);

        // All three invoices are listed, including the reversed one.
        $this->assertCount(3, $response->viewData('sales'));
    }

    public function test_customers_can_be_searched_by_name_phone_and_email(): void
    {
        $wanted = Customer::factory()->create([
            'name' => 'Chea Sophea',
            'phone' => '012 555 444',
            'email' => 'sophea@example.com',
        ]);

        $other = Customer::factory()->create([
            'name' => 'Sok Dara',
            'phone' => '077 111 222',
            'email' => 'dara@example.com',
        ]);

        foreach (['Sophea', '012 555 444', 'sophea@example.com'] as $term) {
            $names = $this->actingAs($this->admin)
                ->get(route('customers.index', ['search' => $term]))
                ->viewData('customers')
                ->pluck('name')
                ->all();

            $this->assertSame([$wanted->name], $names, "Searching for {$term} should find only the one customer.");
            $this->assertNotContains($other->name, $names);
        }
    }

    public function test_the_active_filter_separates_dormant_customers(): void
    {
        $active = Customer::factory()->create();
        $dormant = Customer::factory()->inactive()->create();

        $this->assertNotNull($this->rowFor($active, ['active' => '1']));
        $this->assertNull($this->rowFor($dormant, ['active' => '1']));

        $this->assertNotNull($this->rowFor($dormant, ['active' => '0']));
        $this->assertNull($this->rowFor($active, ['active' => '0']));
    }

    public function test_staff_can_create_and_edit_a_customer(): void
    {
        $this->actingAs($this->staff)
            ->post(route('customers.store'), [
                'name' => 'Chea Sophea',
                'phone' => '012 555 444',
                'is_active' => '1',
            ])
            ->assertRedirect();

        $customer = Customer::query()->sole();

        $this->assertSame('Chea Sophea', $customer->name);
        $this->assertTrue($customer->is_active);

        $this->actingAs($this->staff)
            ->put(route('customers.update', $customer), [
                'name' => 'Chea Sophea',
                'phone' => '012 000 111',
                'is_active' => '1',
            ])
            ->assertRedirect(route('customers.show', $customer));

        $this->assertSame('012 000 111', $customer->fresh()->phone);
    }

    public function test_a_customer_needs_a_name(): void
    {
        $this->actingAs($this->staff)
            ->post(route('customers.store'), ['phone' => '012 555 444'])
            ->assertSessionHasErrors('name');

        $this->assertDatabaseCount('customers', 0);
    }

    public function test_a_bad_email_is_refused(): void
    {
        $this->actingAs($this->staff)
            ->post(route('customers.store'), [
                'name' => 'Chea Sophea',
                'email' => 'not-an-email',
            ])
            ->assertSessionHasErrors('email');
    }

    /**
     * Two walk-ins can genuinely share a name, and a family shares a phone, so
     * neither is unique on purpose.
     */
    public function test_two_customers_may_share_a_name_and_phone(): void
    {
        foreach ([1, 2] as $ignored) {
            $this->actingAs($this->staff)
                ->post(route('customers.store'), [
                    'name' => 'Sok Dara',
                    'phone' => '012 555 444',
                ])
                ->assertRedirect();
        }

        $this->assertDatabaseCount('customers', 2);
    }

    public function test_a_deleted_customer_can_be_restored(): void
    {
        $customer = Customer::factory()->create();
        $this->saleFor($customer, 20);

        $this->actingAs($this->admin)
            ->delete(route('customers.destroy', $customer))
            ->assertRedirect(route('customers.index'));

        $this->assertSoftDeleted($customer);

        // Gone from the normal list, present in the recycle bin.
        $this->assertNull($this->rowFor($customer));
        $this->assertNotNull($this->rowFor($customer, ['trashed' => 1]));

        $this->actingAs($this->admin)
            ->post(route('customers.restore', $customer->id))
            ->assertRedirect(route('customers.index', ['trashed' => 1]));

        $this->assertNotSoftDeleted($customer);
        $this->assertNotNull($this->rowFor($customer));
    }

    public function test_deleting_a_customer_keeps_their_invoices_readable(): void
    {
        $customer = Customer::factory()->create(['name' => 'Chea Sophea']);
        $sale = $this->saleFor($customer, 20);

        $customer->delete();

        // The Sale relation loads withTrashed on purpose, so an old receipt
        // still names who bought the goods.
        $this->actingAs($this->admin)
            ->get(route('sales.show', $sale))
            ->assertOk()
            ->assertSee('Chea Sophea');
    }

    public function test_only_admins_reach_the_recycle_bin(): void
    {
        $customer = Customer::factory()->create();
        $customer->delete();

        // A manager asking for trashed=1 is simply shown the normal list.
        $trashed = $this->actingAs(User::factory()->manager()->create())
            ->get(route('customers.index', ['trashed' => 1]))
            ->viewData('trashed');

        $this->assertFalse($trashed);

        $this->assertTrue(
            $this->actingAs($this->admin)
                ->get(route('customers.index', ['trashed' => 1]))
                ->viewData('trashed'),
        );
    }

    public function test_a_manager_cannot_restore_a_customer(): void
    {
        $customer = Customer::factory()->create();
        $customer->delete();

        $this->actingAs(User::factory()->manager()->create())
            ->post(route('customers.restore', $customer->id))
            ->assertForbidden();

        $this->assertSoftDeleted($customer);
    }

    public function test_the_till_preselects_a_customer_passed_in_the_url(): void
    {
        $customer = Customer::factory()->create();

        $selected = $this->actingAs($this->staff)
            ->get(route('pos.index', ['customer' => $customer->id]))
            ->viewData('selectedCustomer');

        $this->assertSame($customer->id, $selected);
    }

    public function test_the_till_only_offers_active_customers(): void
    {
        $active = Customer::factory()->create(['name' => 'Chea Sophea']);
        $dormant = Customer::factory()->inactive()->create(['name' => 'Retired Buyer']);

        $names = $this->actingAs($this->staff)
            ->get(route('pos.index'))
            ->viewData('customers')
            ->pluck('name')
            ->all();

        $this->assertContains($active->name, $names);
        $this->assertNotContains($dormant->name, $names);
    }
}
