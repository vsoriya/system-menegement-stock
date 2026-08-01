<?php

namespace App\Http\Controllers;

use App\Enums\MovementType;
use App\Exceptions\InsufficientStockException;
use App\Http\Requests\StockMovementRequest;
use App\Models\Product;
use App\Models\StockMovement;
use App\Services\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StockMovementController extends Controller
{
    public function index(Request $request): View
    {
        $movements = StockMovement::query()
            ->with(['product', 'user'])
            ->search($request->string('search')->toString())
            ->ofType($request->string('type')->toString())
            ->betweenDates($request->string('from')->toString(), $request->string('to')->toString())
            ->when($request->filled('product'), fn ($query) => $query->where('product_id', $request->integer('product')))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('movements.index', [
            'movements' => $movements,
            'types' => MovementType::options(),
            'products' => Product::query()->orderBy('name')->get(['id', 'name', 'sku']),
            'filters' => $request->only(['search', 'type', 'from', 'to', 'product']),
        ]);
    }

    public function create(Request $request): View
    {
        $selected = $request->filled('product')
            ? Product::query()->find($request->integer('product'))
            : null;

        $types = MovementType::options();

        // Adjustment rewrites the balance outright, so it is not offered to
        // staff. Removed from the choices rather than shown and then refused.
        if (! $request->user()?->canAdjustStock()) {
            unset($types[MovementType::Adjustment->value]);
        }

        $requested = $request->string('type')->toString();

        return view('movements.create', [
            // Only the columns the dropdown actually shows. This was loading
            // every column of every active product, including the description
            // TEXT field, purely to build a list of names.
            'products' => Product::query()
                ->active()
                ->orderBy('name')
                ->get(['id', 'name', 'sku', 'quantity', 'unit']),
            'selectedProduct' => $selected,
            'types' => $types,
            // Falls back rather than preselecting a type this user cannot use,
            // which would leave the form with nothing ticked.
            'type' => array_key_exists($requested, $types) ? $requested : MovementType::In->value,
        ]);
    }

    public function store(StockMovementRequest $request, StockService $stock): RedirectResponse
    {
        $product = Product::query()->findOrFail($request->integer('product_id'));
        $type = $request->type();

        try {
            $movement = $stock->record(
                $product,
                $type,
                $request->integer('quantity'),
                $request->meta(),
                $request->user(),
            );
        } catch (InsufficientStockException $exception) {
            return back()
                ->withInput()
                ->withErrors(['quantity' => $exception->getMessage()]);
        }

        return redirect()
            ->route('products.show', $product)
            ->with('status', __('app.movement.recorded', [
                'type' => $type->label(),
                'product' => $product->name,
                'before' => $movement->quantity_before,
                'after' => $movement->quantity_after,
                'unit' => $product->unit,
            ]));
    }
}
