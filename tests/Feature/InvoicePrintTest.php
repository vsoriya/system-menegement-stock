<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Services\SaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The printable invoice, in both shapes.
 *
 * These are the only pages a customer physically takes away, so they are worth
 * asserting on properly: the right figures, the shop details when they are
 * configured, and the page size rule the printer actually needs.
 */
class InvoicePrintTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->staff = User::factory()->staff()->create();

        $this->product = Product::factory()->create([
            'name' => 'Angkor Beer Can',
            'sku' => 'BEER-001',
            'quantity' => 50,
            'cost_price' => 8,
            'sale_price' => 12,
        ]);
    }

    private function sale(float $discount = 0, float $paid = 40): Sale
    {
        return app(SaleService::class)->record(
            [['product_id' => $this->product->id, 'quantity' => 3]],
            ['payment_method' => 'cash', 'paid' => $paid, 'discount' => $discount],
            $this->staff,
        );
    }

    public function test_the_a5_invoice_prints_the_sale(): void
    {
        $sale = $this->sale();

        $response = $this->actingAs($this->staff)->get(route('sales.print', [$sale, 'a5']));

        $response->assertOk();
        $response->assertSee($sale->number);
        $response->assertSee('Angkor Beer Can');
        $response->assertSee('BEER-001');
        $response->assertSee('36.00');

        // The page rule is what makes it come out on A5 rather than Letter.
        $response->assertSee('size: A5 portrait', false);
    }

    public function test_the_receipt_prints_at_eighty_millimetres(): void
    {
        $sale = $this->sale();

        $response = $this->actingAs($this->staff)->get(route('sales.print', [$sale, 'receipt']));

        $response->assertOk();
        $response->assertSee($sale->number);
        $response->assertSee('Angkor Beer Can');
        $response->assertSee('36.00');

        // auto height, so the roll is cut to the length of the basket.
        $response->assertSee('size: 80mm auto', false);
    }

    public function test_the_default_format_is_the_a5_sheet(): void
    {
        $sale = $this->sale();

        $this->actingAs($this->staff)
            ->get(route('sales.print', $sale))
            ->assertOk()
            ->assertSee('size: A5 portrait', false);
    }

    public function test_an_unknown_format_is_not_served(): void
    {
        $sale = $this->sale();

        // Constrained in the route, so a typo cannot reach the controller and
        // silently fall back to something unexpected.
        $this->actingAs($this->staff)
            ->get(url("sales/{$sale->id}/print/letter"))
            ->assertNotFound();
    }

    public function test_the_printout_carries_the_change_given(): void
    {
        $sale = $this->sale(paid: 50);

        $this->actingAs($this->staff)
            ->get(route('sales.print', [$sale, 'receipt']))
            ->assertOk()
            // 50.00 handed over against a 36.00 total.
            ->assertSee('14.00');

        $this->assertSame(14.0, (float) $sale->change_due);
    }

    public function test_a_discount_appears_on_the_printout(): void
    {
        $sale = $this->sale(discount: 6);

        $this->actingAs($this->staff)
            ->get(route('sales.print', [$sale, 'a5']))
            ->assertOk()
            ->assertSee('30.00');

        $this->assertSame(30.0, (float) $sale->total);
    }

    public function test_shop_details_are_printed_when_they_are_configured(): void
    {
        config([
            'app.shop.address' => '12 Street 271, Phnom Penh',
            'app.shop.phone' => '012 345 678',
            'app.shop.tax_number' => 'K001-901234567',
            'app.shop.footer' => 'Goods sold are not returnable.',
        ]);

        $sale = $this->sale();

        foreach (['a5', 'receipt'] as $format) {
            $response = $this->actingAs($this->staff)->get(route('sales.print', [$sale, $format]));

            $response->assertOk();
            $response->assertSee('12 Street 271, Phnom Penh');
            $response->assertSee('012 345 678');
            $response->assertSee('K001-901234567');
            $response->assertSee('Goods sold are not returnable.');
        }
    }

    /**
     * A brand new install has none of these filled in, and an invoice still has
     * to print rather than showing empty labels or blowing up.
     */
    public function test_the_printout_works_with_no_shop_details_configured(): void
    {
        config([
            'app.shop.address' => null,
            'app.shop.phone' => null,
            'app.shop.tax_number' => null,
            'app.shop.footer' => null,
        ]);

        $sale = $this->sale();

        foreach (['a5', 'receipt'] as $format) {
            $this->actingAs($this->staff)
                ->get(route('sales.print', [$sale, $format]))
                ->assertOk()
                ->assertSee($sale->number)
                // Falls back to the standard closing line.
                ->assertSee(__('app.sale.thank_you'));
        }
    }

    public function test_a_reversed_sale_prints_marked_as_reversed(): void
    {
        $sale = $this->sale();

        app(SaleService::class)->void($sale, User::factory()->manager()->create(), 'Customer returned it');

        foreach (['a5', 'receipt'] as $format) {
            $this->actingAs($this->staff)
                ->get(route('sales.print', [$sale->fresh(), $format]))
                ->assertOk()
                // Otherwise a reversed invoice could be handed over as if the
                // sale still stood.
                ->assertSee(__('app.sale.voided_banner'));
        }
    }

    public function test_a_guest_cannot_print_an_invoice(): void
    {
        $sale = $this->sale();

        $this->get(route('sales.print', [$sale, 'a5']))->assertRedirect(route('login'));
        $this->get(route('sales.print', [$sale, 'receipt']))->assertRedirect(route('login'));
    }

    public function test_the_printout_still_reads_after_the_product_is_deleted(): void
    {
        $sale = $this->sale();

        $this->product->delete();

        // The line snapshot is what the invoice is built from, so removing the
        // product from the catalogue must not blank out the paperwork.
        $this->actingAs($this->staff)
            ->get(route('sales.print', [$sale, 'a5']))
            ->assertOk()
            ->assertSee('Angkor Beer Can')
            ->assertSee('36.00');
    }

    public function test_the_invoice_page_links_to_both_printouts(): void
    {
        $sale = $this->sale();

        $this->actingAs($this->staff)
            ->get(route('sales.show', $sale))
            ->assertOk()
            ->assertSee(route('sales.print', [$sale, 'a5']), false)
            ->assertSee(route('sales.print', [$sale, 'receipt']), false);
    }
}
