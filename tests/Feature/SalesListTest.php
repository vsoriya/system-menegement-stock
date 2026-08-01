<?php

namespace Tests\Feature;

use App\Enums\SaleStatus;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleLine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The sales list and its filters.
 *
 * Filters are the part of a listing that silently lies. A wrong join or an
 * ungrouped OR still returns a page of rows and a 200, so these tests check
 * which rows came back and what the totals say, not that the page rendered.
 */
class SalesListTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function sale(float $total, array $attributes = []): Sale
    {
        return Sale::factory()->create([
            'user_id' => $this->admin->id,
            'subtotal' => $total,
            'total' => $total,
            'paid' => $total,
            'discount' => 0,
            'change_due' => 0,
            ...$attributes,
        ]);
    }

    /**
     * @param  array<string, mixed>  $query
     * @return \Illuminate\Testing\TestResponse
     */
    private function list(array $query = [])
    {
        return $this->actingAs($this->admin)->get(route('sales.index', $query));
    }

    /**
     * @return array<int, string>
     */
    private function numbersOn(array $query = []): array
    {
        return $this->list($query)
            ->viewData('sales')
            ->pluck('number')
            ->all();
    }

    public function test_the_list_shows_every_sale_by_default(): void
    {
        $first = $this->sale(10);
        $second = $this->sale(20);

        $numbers = $this->numbersOn();

        $this->assertCount(2, $numbers);
        $this->assertContains($first->number, $numbers);
        $this->assertContains($second->number, $numbers);
    }

    public function test_searching_by_invoice_number_narrows_the_list(): void
    {
        $wanted = $this->sale(10, ['number' => 'INV-2026-000777']);
        $other = $this->sale(20, ['number' => 'INV-2026-000888']);

        $numbers = $this->numbersOn(['search' => '000777']);

        $this->assertSame([$wanted->number], $numbers);
        $this->assertNotContains($other->number, $numbers);
    }

    public function test_searching_by_customer_name_narrows_the_list(): void
    {
        $customer = Customer::factory()->create(['name' => 'Chea Sophea']);
        $wanted = $this->sale(10, ['customer_id' => $customer->id]);
        $this->sale(20, ['customer_id' => null]);

        $this->assertSame([$wanted->number], $this->numbersOn(['search' => 'Sophea']));
    }

    /**
     * The customer condition sits inside a whereHas callback holding an OR.
     * If those conditions are not grouped, the OR escapes the correlation and
     * every sale matches. This is the test that would catch that.
     */
    public function test_searching_by_customer_phone_does_not_match_unrelated_sales(): void
    {
        $customer = Customer::factory()->create([
            'name' => 'Chea Sophea',
            'phone' => '012 999 111',
        ]);

        $wanted = $this->sale(10, ['customer_id' => $customer->id]);
        $walkIn = $this->sale(20, ['customer_id' => null, 'note' => null]);

        $numbers = $this->numbersOn(['search' => '012 999 111']);

        $this->assertSame([$wanted->number], $numbers);
        $this->assertNotContains($walkIn->number, $numbers);
    }

    public function test_a_search_that_matches_nothing_returns_nothing(): void
    {
        $this->sale(10);

        $this->assertSame([], $this->numbersOn(['search' => 'no-such-thing']));
    }

    public function test_the_status_filter_separates_reversed_sales(): void
    {
        $live = $this->sale(10);
        $reversed = Sale::factory()->voided()->create([
            'user_id' => $this->admin->id,
            'total' => 20,
        ]);

        $this->assertSame([$live->number], $this->numbersOn(['status' => SaleStatus::Completed->value]));
        $this->assertSame([$reversed->number], $this->numbersOn(['status' => SaleStatus::Voided->value]));
        $this->assertCount(2, $this->numbersOn());
    }

    public function test_the_date_range_filter_includes_its_own_boundaries(): void
    {
        $before = $this->sale(10, ['sold_at' => now()->subDays(10)]);
        $onFrom = $this->sale(20, ['sold_at' => now()->subDays(5)->setTime(9, 0)]);
        $onTo = $this->sale(30, ['sold_at' => now()->subDays(3)->setTime(21, 30)]);
        $after = $this->sale(40, ['sold_at' => now()]);

        $numbers = $this->numbersOn([
            'from' => now()->subDays(5)->toDateString(),
            'to' => now()->subDays(3)->toDateString(),
        ]);

        // Both ends are inclusive, and the late hour on the closing day still
        // counts, which is the case a plain datetime comparison gets wrong.
        $this->assertCount(2, $numbers);
        $this->assertContains($onFrom->number, $numbers);
        $this->assertContains($onTo->number, $numbers);
        $this->assertNotContains($before->number, $numbers);
        $this->assertNotContains($after->number, $numbers);
    }

    public function test_the_totals_follow_the_filter_not_the_page(): void
    {
        $this->sale(10, ['sold_at' => now()->subDays(9)]);
        $this->sale(25, ['sold_at' => now()->subDays(2)]);
        $this->sale(65, ['sold_at' => now()->subDays(1)]);

        $response = $this->list([
            'from' => now()->subDays(3)->toDateString(),
        ]);

        $this->assertSame(2, $response->viewData('salesCount'));
        $this->assertSame(90.0, $response->viewData('revenue'));
    }

    public function test_reversed_sales_are_left_out_of_the_totals_but_still_listed(): void
    {
        $this->sale(40);
        Sale::factory()->voided()->create([
            'user_id' => $this->admin->id,
            'total' => 1000,
        ]);

        $response = $this->list();

        // Listed, so the reversal is visible to anyone auditing the day.
        $this->assertCount(2, $response->viewData('sales'));

        // But a reversed sale is not money taken.
        $this->assertSame(1, $response->viewData('salesCount'));
        $this->assertSame(40.0, $response->viewData('revenue'));
    }

    public function test_the_totals_cover_every_page_not_just_the_first(): void
    {
        // 25 sales at 4.00 each, against a page size of 20.
        Sale::factory()->count(25)->create([
            'user_id' => $this->admin->id,
            'subtotal' => 4,
            'total' => 4,
            'paid' => 4,
            'discount' => 0,
            'change_due' => 0,
        ]);

        $response = $this->list();

        $this->assertCount(20, $response->viewData('sales'));
        $this->assertSame(25, $response->viewData('salesCount'));
        $this->assertSame(100.0, $response->viewData('revenue'));
    }

    public function test_each_row_reports_the_units_sold(): void
    {
        $sale = $this->sale(50);

        SaleLine::factory()->create([
            'sale_id' => $sale->id,
            'product_id' => Product::factory(),
            'quantity' => 3,
        ]);
        SaleLine::factory()->create([
            'sale_id' => $sale->id,
            'product_id' => Product::factory(),
            'quantity' => 4,
        ]);

        $row = $this->list()->viewData('sales')->sole();

        $this->assertSame(7, (int) $row->units_sold);
    }

    public function test_the_newest_sale_is_listed_first(): void
    {
        $older = $this->sale(10, ['sold_at' => now()->subDays(2)]);
        $newest = $this->sale(20, ['sold_at' => now()]);
        $middle = $this->sale(30, ['sold_at' => now()->subDay()]);

        $this->assertSame(
            [$newest->number, $middle->number, $older->number],
            $this->numbersOn(),
        );
    }

    public function test_filters_survive_into_the_pagination_links(): void
    {
        Sale::factory()->count(22)->create([
            'user_id' => $this->admin->id,
            'total' => 5,
        ]);

        $sales = $this->list(['search' => 'INV'])->viewData('sales');

        // Without withQueryString, page two would quietly drop the filter and
        // show a different set of rows than page one.
        $this->assertStringContainsString('search=INV', $sales->nextPageUrl());
    }

    public function test_staff_can_open_the_sales_list(): void
    {
        $this->sale(10);

        $this->actingAs(User::factory()->staff()->create())
            ->get(route('sales.index'))
            ->assertOk();
    }
}
