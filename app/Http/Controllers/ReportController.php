<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ReportController extends Controller
{
    /**
     * Everything at or below its reorder level.
     */
    public function lowStock(Request $request): View
    {
        $products = Product::query()
            ->with(['category', 'supplier'])
            ->needsReorder()
            ->when($request->filled('category'), fn ($query) => $query->where('category_id', $request->integer('category')))
            // Both columns are unsigned, and MySQL errors on an unsigned
            // underflow, which is exactly what "below the reorder level" is.
            // Adding 0.0 promotes them to a signed type first. CAST(.. AS
            // SIGNED) would also work but is MySQL only, and that would stop
            // the test suite running on SQLite.
            ->orderByRaw('(quantity + 0.0) - (reorder_level + 0.0) ASC')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('reports.low-stock', [
            'products' => $products,
            'categories' => Category::query()->orderBy('name')->get(),
            'filters' => $request->only('category'),
            'outOfStockCount' => Product::query()->outOfStock()->count(),
            'lowStockCount' => Product::query()->lowStock()->count(),
        ]);
    }

    /**
     * Stock value grouped by category.
     */
    public function valuation(StockService $stock): View
    {
        // toBase() for the same reason as StockService::summary(). Hydrating
        // these rows into Products would let the stock_value and retail_value
        // accessors shadow the aggregates and report zero for every category.
        $byCategory = Product::query()
            ->toBase()
            ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
            ->groupBy('categories.id', 'categories.name')
            ->select([
                // Left as null so the view can translate the fallback label.
                DB::raw('categories.name as category_name'),
                DB::raw('COUNT(products.id) as products_count'),
                DB::raw('COALESCE(SUM(products.quantity), 0) as units_on_hand'),
                DB::raw('COALESCE(SUM(products.quantity * products.cost_price), 0) as stock_value'),
                DB::raw('COALESCE(SUM(products.quantity * products.sale_price), 0) as retail_value'),
            ])
            ->orderByDesc('stock_value')
            ->get();

        return view('reports.valuation', [
            'summary' => $stock->summary(),
            'byCategory' => $byCategory,
        ]);
    }
}
