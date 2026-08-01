<?php

namespace App\Http\Controllers;

use App\Enums\PaymentMethod;
use App\Exceptions\InsufficientStockException;
use App\Http\Requests\SaleRequest;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Services\SaleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class PosController extends Controller
{
    /**
     * Products are handed to the page in full rather than paginated, so
     * searching and scanning happen instantly in the browser and, more
     * importantly, never reload the page and throw away a part-built basket.
     *
     * Only the handful of fields the grid needs are sent. Cost price is
     * deliberately left out: the till has no use for it and it does not belong
     * in the page source.
     */
    public function index(Request $request): View
    {
        $products = Product::query()
            ->active()
            ->with('category:id,name')
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'sku',
                'barcode',
                'image_path',
                'sale_price',
                'quantity',
                'unit',
                'category_id',
            ])
            // Every value is cast deliberately. The browser compares these with
            // === against numbers taken from the category buttons, and MySQL
            // hands integer columns back as strings, so an uncast id silently
            // matched nothing at all. SQLite returns real integers, which is
            // why the test suite never saw it.
            ->map(fn (Product $product): array => [
                'id' => (int) $product->id,
                'name' => $product->name,
                'sku' => (string) $product->sku,
                'barcode' => (string) $product->barcode,
                'price' => (float) $product->sale_price,
                'stock' => (int) $product->quantity,
                'unit' => (string) $product->unit,
                'image' => $product->image_url,
                'category_id' => $product->category_id === null ? null : (int) $product->category_id,
                'category' => $product->category?->name,
            ])
            ->values();

        return view('pos.index', [
            'products' => $products,
            'categories' => Category::query()->active()->orderBy('name')->get(['id', 'name']),
            'customers' => Customer::query()->active()->orderBy('name')->get(['id', 'name', 'phone']),
            'paymentMethods' => PaymentMethod::options(),
            'selectedCustomer' => $request->integer('customer') ?: null,
        ]);
    }

    public function store(SaleRequest $request, SaleService $saleService): RedirectResponse
    {
        try {
            $sale = $saleService->record($request->items(), $request->options(), $request->user());
        } catch (InsufficientStockException $e) {
            // The whole basket was rolled back, so the cashier can lower the
            // quantity and try again without anything having moved.
            return back()
                ->withInput()
                ->withErrors(['items' => $e->getMessage()]);
        } catch (InvalidArgumentException $e) {
            return back()
                ->withInput()
                ->withErrors(['items' => $e->getMessage()]);
        }

        return redirect()
            ->route('sales.show', $sale)
            ->with('status', __('app.sale.completed_msg', ['number' => $sale->number]));
    }
}
