<?php

namespace App\Http\Controllers;

use App\Enums\MovementType;
use App\Http\Requests\ProductRequest;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Services\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        // Admins can browse the recycle bin via ?trashed=1.
        $trashed = $request->boolean('trashed') && (bool) $request->user()?->canDelete();

        $products = Product::query()
            ->when($trashed, fn ($query) => $query->onlyTrashed())
            ->with(['category', 'supplier'])
            ->search($request->string('search')->toString())
            ->stockStatusIs($request->string('status')->toString())
            ->when($request->filled('category'), fn ($query) => $query->where('category_id', $request->integer('category')))
            ->when($request->filled('supplier'), fn ($query) => $query->where('supplier_id', $request->integer('supplier')))
            ->when($request->input('active') === '1', fn ($query) => $query->where('is_active', true))
            ->when($request->input('active') === '0', fn ($query) => $query->where('is_active', false))
            ->orderBy($this->sortColumn($request), $this->sortDirection($request))
            ->paginate(15)
            ->withQueryString();

        return view('products.index', [
            'products' => $products,
            // Both are only ever plucked into name/id pairs for the filter
            // dropdowns, so there is no reason to fetch their text columns.
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
            'suppliers' => Supplier::query()->orderBy('name')->get(['id', 'name']),
            'filters' => $request->only(['search', 'status', 'category', 'supplier', 'active', 'sort', 'direction']),
            'trashed' => $trashed,
            'trashedCount' => Product::onlyTrashed()->count(),
        ]);
    }

    public function create(): View
    {
        $this->authorizeManage();

        return view('products.create', [
            'product' => new Product(['unit' => 'pcs', 'is_active' => true]),
            'categories' => Category::query()->active()->orderBy('name')->get(['id', 'name']),
            'suppliers' => Supplier::query()->active()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(ProductRequest $request, StockService $stock): RedirectResponse
    {
        $payload = $request->payload() + ['quantity' => 0];

        if ($path = $request->storeImage()) {
            $payload['image_path'] = $path;
        }

        $product = Product::query()->create($payload);

        // Record the opening stock as a movement so history starts complete.
        if ($request->openingQuantity() > 0) {
            $stock->record(
                $product,
                MovementType::In,
                $request->openingQuantity(),
                [
                    'reference' => __('app.movement.opening_stock'),
                    'unit_cost' => $product->cost_price,
                ],
                $request->user(),
            );
        }

        return redirect()
            ->route('products.show', $product)
            ->with('status', __('app.product.created_msg', ['name' => $product->name]));
    }

    public function show(Product $product): View
    {
        $product->load(['category', 'supplier']);

        return view('products.show', [
            'product' => $product,
            'movements' => $product->movements()
                ->with('user')
                ->paginate(15),
        ]);
    }

    public function edit(Product $product): View
    {
        $this->authorizeManage();

        return view('products.edit', [
            'product' => $product,
            'categories' => Category::query()->active()->orderBy('name')->get(['id', 'name']),
            'suppliers' => Supplier::query()->active()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(ProductRequest $request, Product $product): RedirectResponse
    {
        $payload = $request->payload();

        if ($request->boolean('remove_image')) {
            $this->deleteImage($product);
            $payload['image_path'] = null;
        }

        if ($path = $request->storeImage()) {
            // Replacing an image should not leave the old file behind.
            $this->deleteImage($product);
            $payload['image_path'] = $path;
        }

        $product->update($payload);

        return redirect()
            ->route('products.show', $product)
            ->with('status', __('app.product.updated_msg', ['name' => $product->name]));
    }

    /**
     * Soft delete, so the stock history survives and the record can come back.
     */
    public function destroy(Request $request, Product $product): RedirectResponse
    {
        abort_unless((bool) $request->user()?->canDelete(), 403);

        $name = $product->name;
        $product->delete();

        return redirect()
            ->route('products.index')
            ->with('status', __('app.product.deleted_msg', ['name' => $name]));
    }

    public function restore(Request $request, int $product): RedirectResponse
    {
        abort_unless((bool) $request->user()?->canDelete(), 403);

        $model = Product::onlyTrashed()->findOrFail($product);
        $model->restore();

        return redirect()
            ->route('products.index', ['trashed' => 1])
            ->with('status', __('app.product.restored_msg', ['name' => $model->name]));
    }

    /**
     * Permanently remove the product, its image and its stock history.
     */
    public function forceDelete(Request $request, int $product): RedirectResponse
    {
        abort_unless((bool) $request->user()?->canDelete(), 403);

        $model = Product::onlyTrashed()->findOrFail($product);
        $name = $model->name;

        // Purchase orders and invoices must keep naming a real product, so a
        // traded item can only ever stay in the recycle bin.
        if ($model->hasTradingHistory()) {
            return redirect()
                ->route('products.index', ['trashed' => 1])
                ->withErrors(['product' => __('app.product.purge_blocked', ['name' => $name])]);
        }

        $this->deleteImage($model);
        $model->forceDelete();

        return redirect()
            ->route('products.index', ['trashed' => 1])
            ->with('status', __('app.product.purged_msg', ['name' => $name]));
    }

    protected function deleteImage(Product $product): void
    {
        if ($product->image_path) {
            Storage::disk(config('filesystems.images'))->delete($product->image_path);
        }
    }

    protected function authorizeManage(): void
    {
        abort_unless((bool) request()->user()?->canManageCatalog(), 403);
    }

    protected function sortColumn(Request $request): string
    {
        $allowed = ['name', 'sku', 'quantity', 'cost_price', 'sale_price', 'created_at'];
        $sort = $request->string('sort')->toString();

        return in_array($sort, $allowed, true) ? $sort : 'name';
    }

    protected function sortDirection(Request $request): string
    {
        return $request->string('direction')->toString() === 'desc' ? 'desc' : 'asc';
    }
}
