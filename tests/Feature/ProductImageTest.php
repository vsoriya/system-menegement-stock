<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Product images and barcodes.
 *
 * Both were fully supported by the model, the request and the till, and both
 * were missing from the product form, so neither could actually be entered. The
 * till grid showed initials for every item and barcode scanning had nothing to
 * match against. These tests cover the round trip now that the inputs exist.
 */
class ProductImageTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private string $disk;

    protected function setUp(): void
    {
        parent::setUp();

        // Whichever disk is configured, not a hardcoded name, so these tests
        // keep testing the real thing if the default ever changes.
        $this->disk = config('filesystems.images');

        Storage::fake($this->disk);

        $this->manager = User::factory()->manager()->create();
    }

    /**
     * Built with an explicit mime type rather than a generated image, so the
     * suite does not depend on the GD extension being installed.
     */
    private function file(string $name = 'photo.jpg', int $kilobytes = 120, string $mime = 'image/jpeg'): UploadedFile
    {
        return UploadedFile::fake()->create($name, $kilobytes, $mime);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'sku' => 'MSE-1001',
            'name' => 'Wireless mouse',
            'unit' => 'pcs',
            'cost_price' => '5.00',
            'sale_price' => '9.00',
            'quantity' => 10,
            'reorder_level' => 3,
            'is_active' => 1,
            ...$overrides,
        ];
    }

    public function test_a_product_can_be_created_with_an_image(): void
    {
        $this->actingAs($this->manager)
            ->post(route('products.store'), $this->payload(['image' => $this->file()]))
            ->assertRedirect();

        $product = Product::query()->sole();

        $this->assertNotNull($product->image_path);
        Storage::disk($this->disk)->assertExists($product->image_path);

        // The accessor is what the till and the listings read.
        $this->assertNotNull($product->image_url);
    }

    public function test_a_product_can_be_created_without_an_image(): void
    {
        $this->actingAs($this->manager)
            ->post(route('products.store'), $this->payload())
            ->assertRedirect();

        $product = Product::query()->sole();

        $this->assertNull($product->image_path);
        $this->assertNull($product->image_url);
    }

    public function test_an_image_can_be_added_to_an_existing_product(): void
    {
        $product = Product::factory()->create(['image_path' => null]);

        $this->actingAs($this->manager)
            ->put(route('products.update', $product), $this->payload([
                'sku' => $product->sku,
                'name' => $product->name,
                'image' => $this->file(),
            ]))
            ->assertRedirect(route('products.show', $product));

        $product->refresh();

        $this->assertNotNull($product->image_path);
        Storage::disk($this->disk)->assertExists($product->image_path);
    }

    public function test_replacing_an_image_deletes_the_old_file(): void
    {
        $this->actingAs($this->manager)
            ->post(route('products.store'), $this->payload(['image' => $this->file('first.jpg')]));

        $product = Product::query()->sole();
        $first = $product->image_path;

        $this->actingAs($this->manager)
            ->put(route('products.update', $product), $this->payload([
                'sku' => $product->sku,
                'name' => $product->name,
                'image' => $this->file('second.jpg'),
            ]));

        $product->refresh();

        $this->assertNotSame($first, $product->image_path);

        // Otherwise every edit leaves another orphaned file on the disk.
        Storage::disk($this->disk)->assertMissing($first);
        Storage::disk($this->disk)->assertExists($product->image_path);
    }

    public function test_an_image_can_be_removed(): void
    {
        $this->actingAs($this->manager)
            ->post(route('products.store'), $this->payload(['image' => $this->file()]));

        $product = Product::query()->sole();
        $path = $product->image_path;

        $this->actingAs($this->manager)
            ->put(route('products.update', $product), $this->payload([
                'sku' => $product->sku,
                'name' => $product->name,
                'remove_image' => 1,
            ]));

        $product->refresh();

        $this->assertNull($product->image_path);
        Storage::disk($this->disk)->assertMissing($path);
    }

    public function test_a_file_that_is_not_an_image_is_refused(): void
    {
        $this->actingAs($this->manager)
            ->post(route('products.store'), $this->payload([
                'image' => $this->file('notes.pdf', 100, 'application/pdf'),
            ]))
            ->assertSessionHasErrors('image');

        $this->assertDatabaseCount('products', 0);
    }

    public function test_an_oversized_image_is_refused(): void
    {
        // The rule caps uploads at 2 MB.
        $this->actingAs($this->manager)
            ->post(route('products.store'), $this->payload([
                'image' => $this->file('huge.jpg', 3000),
            ]))
            ->assertSessionHasErrors('image');

        $this->assertDatabaseCount('products', 0);
    }

    public function test_the_till_grid_carries_the_image_url(): void
    {
        $this->actingAs($this->manager)
            ->post(route('products.store'), $this->payload(['image' => $this->file()]));

        $tile = $this->actingAs($this->manager)
            ->get(route('pos.index'))
            ->viewData('products')
            ->firstWhere('name', 'Wireless mouse');

        $this->assertNotNull($tile);
        $this->assertNotNull($tile['image'], 'The till tile should carry a picture once one is uploaded.');
    }

    public function test_a_product_without_an_image_still_reaches_the_till(): void
    {
        $this->actingAs($this->manager)->post(route('products.store'), $this->payload());

        $tile = $this->actingAs($this->manager)
            ->get(route('pos.index'))
            ->viewData('products')
            ->firstWhere('name', 'Wireless mouse');

        // Null rather than missing, because the grid falls back to initials.
        $this->assertNotNull($tile);
        $this->assertNull($tile['image']);
    }

    /**
     * The picture link must not depend on APP_URL.
     *
     * It used to be built as APP_URL . '/storage', so opening the app on
     * 127.0.0.1:8000 while APP_URL said localhost produced links to a host with
     * nothing listening, and every picture broke with no error anywhere.
     */
    public function test_the_image_link_does_not_depend_on_the_configured_app_url(): void
    {
        // Rebuilt with the real disk settings, because a plain fake ignores the
        // url option and would test the framework's fallback instead of ours.
        Storage::fake($this->disk, config('filesystems.disks.'.$this->disk));

        config(['app.url' => 'http://a-completely-different-host:9999']);

        $product = Product::factory()->create(['image_path' => 'products/example.jpg']);

        $this->assertSame('/storage/products/example.jpg', $product->image_url);
    }

    // -----------------------------------------------------------------
    // Barcodes
    // -----------------------------------------------------------------

    public function test_a_barcode_can_be_saved_and_reaches_the_till(): void
    {
        $this->actingAs($this->manager)
            ->post(route('products.store'), $this->payload(['barcode' => '8850123456789']))
            ->assertRedirect();

        $this->assertSame('8850123456789', Product::query()->sole()->barcode);

        $tile = $this->actingAs($this->manager)
            ->get(route('pos.index'))
            ->viewData('products')
            ->firstWhere('name', 'Wireless mouse');

        // The till matches a scanned code against this value, so it has to be
        // in the payload handed to the page.
        $this->assertSame('8850123456789', $tile['barcode']);
    }

    public function test_a_product_can_be_found_by_barcode_in_the_listing(): void
    {
        Product::factory()->create(['name' => 'Scanned item', 'barcode' => '8850123456789']);
        Product::factory()->create(['name' => 'Other item', 'barcode' => '1111111111111']);

        $names = $this->actingAs($this->manager)
            ->get(route('products.index', ['search' => '8850123456789']))
            ->viewData('products')
            ->pluck('name')
            ->all();

        $this->assertSame(['Scanned item'], $names);
    }

    public function test_two_products_cannot_share_a_barcode(): void
    {
        Product::factory()->create(['barcode' => '8850123456789']);

        $this->actingAs($this->manager)
            ->post(route('products.store'), $this->payload(['barcode' => '8850123456789']))
            ->assertSessionHasErrors('barcode');
    }

    public function test_a_blank_barcode_is_stored_as_nothing_not_an_empty_string(): void
    {
        // Two products with an empty string would collide on the unique index.
        foreach (['A-1', 'A-2'] as $sku) {
            $this->actingAs($this->manager)
                ->post(route('products.store'), $this->payload([
                    'sku' => $sku,
                    'barcode' => '',
                ]))
                ->assertRedirect();
        }

        $this->assertDatabaseCount('products', 2);
        $this->assertSame(0, Product::query()->whereNotNull('barcode')->count());
    }

    public function test_a_barcode_with_spaces_or_symbols_is_refused(): void
    {
        $this->actingAs($this->manager)
            ->post(route('products.store'), $this->payload(['barcode' => '885 012 3456']))
            ->assertSessionHasErrors('barcode');
    }

    public function test_the_product_form_offers_both_fields(): void
    {
        $response = $this->actingAs($this->manager)->get(route('products.create'));

        $response->assertOk();

        // The whole point: without these inputs neither value can be entered.
        $response->assertSee('name="barcode"', false);
        $response->assertSee('name="image"', false);

        // And without the encoding the browser posts a file name, not a file.
        $response->assertSee('enctype="multipart/form-data"', false);
    }

    public function test_the_edit_form_offers_the_remove_image_option_only_when_there_is_one(): void
    {
        $withImage = Product::factory()->create(['image_path' => 'products/example.jpg']);
        $without = Product::factory()->create(['image_path' => null]);

        $this->actingAs($this->manager)
            ->get(route('products.edit', $withImage))
            ->assertOk()
            ->assertSee('name="remove_image"', false);

        $this->actingAs($this->manager)
            ->get(route('products.edit', $without))
            ->assertOk()
            ->assertDontSee('name="remove_image"', false);
    }
}
