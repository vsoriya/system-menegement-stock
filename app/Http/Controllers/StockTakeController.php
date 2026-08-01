<?php

namespace App\Http\Controllers;

use App\Enums\StockTakeStatus;
use App\Http\Requests\StockTakeCountRequest;
use App\Http\Requests\StockTakeRequest;
use App\Models\Category;
use App\Models\Product;
use App\Models\StockTake;
use App\Services\StockTakeService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StockTakeController extends Controller
{
    /**
     * Rows shown per page of a count sheet.
     */
    protected const SHEET_PER_PAGE = 50;

    public function index(Request $request): View
    {
        $takes = StockTake::query()
            ->with(['category', 'creator'])
            ->withCount([
                'lines',
                'lines as counted_count' => fn (Builder $query) => $query->whereNotNull('counted_quantity'),
                'lines as variance_count' => fn (Builder $query) => $query
                    ->whereNotNull('counted_quantity')
                    ->whereColumn('counted_quantity', '!=', 'expected_quantity'),
            ])
            ->search($request->string('search')->toString())
            ->withStatus($request->string('status')->toString())
            ->orderByDesc('counted_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('stock-takes.index', [
            'takes' => $takes,
            'statuses' => StockTakeStatus::options(),
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    public function create(): View
    {
        $this->authorizeManage();

        return view('stock-takes.create', [
            'take' => new StockTake(['counted_at' => today()]),
            'categories' => Category::query()
                ->active()
                ->orderBy('name')
                ->pluck('name', 'id')
                ->all(),
        ]);
    }

    public function store(StockTakeRequest $request, StockTakeService $service): RedirectResponse
    {
        $take = $service->start($request->payload(), $request->user());

        return redirect()
            ->route('stock-takes.show', $take)
            ->with('status', __('app.stocktake.created_msg', [
                'reference' => $take->reference,
                'count' => $take->lines()->count(),
            ]));
    }

    public function show(StockTake $stockTake): View
    {
        $stockTake->load(['category', 'creator']);

        return view('stock-takes.show', [
            'take' => $stockTake,
            'lines' => $this->sheet($stockTake),
            'totalLines' => $stockTake->lines()->count(),
            'countedLines' => $stockTake->lines()->whereNotNull('counted_quantity')->count(),
            'varianceLines' => $stockTake->lines()
                ->whereNotNull('counted_quantity')
                ->whereColumn('counted_quantity', '!=', 'expected_quantity')
                ->count(),
        ]);
    }

    /**
     * Save the counted quantities, and post the differences when asked to.
     */
    public function update(
        StockTakeCountRequest $request,
        StockTake $stockTake,
        StockTakeService $service,
    ): RedirectResponse {
        if (! $stockTake->status->isEditable()) {
            return redirect()
                ->route('stock-takes.show', $stockTake)
                ->with('error', __('app.stocktake.only_open_editable'));
        }

        $service->saveCounts($stockTake, $request->counts());

        if (! $request->shouldPost()) {
            return back()->with('status', __('app.stocktake.saved_msg'));
        }

        // Reloaded so the check below sees what was just saved.
        $stockTake->load('lines');

        if ($stockTake->variance_lines < 1) {
            return back()->with('error', __('app.stocktake.nothing_to_post'));
        }

        $adjusted = $service->post($stockTake, $request->user());

        return redirect()
            ->route('stock-takes.show', $stockTake)
            ->with('status', __('app.stocktake.posted_msg', [
                'reference' => $stockTake->reference,
                'count' => $adjusted,
            ]));
    }

    public function cancel(StockTake $stockTake, StockTakeService $service): RedirectResponse
    {
        $this->authorizeManage();

        if (! $stockTake->status->isEditable()) {
            return redirect()
                ->route('stock-takes.show', $stockTake)
                ->with('error', __('app.stocktake.only_open_editable'));
        }

        $service->cancel($stockTake);

        return redirect()
            ->route('stock-takes.index')
            ->with('status', __('app.stocktake.cancelled_msg', ['reference' => $stockTake->reference]));
    }

    public function destroy(Request $request, StockTake $stockTake): RedirectResponse
    {
        abort_unless((bool) $request->user()?->canDelete(), 403);

        // A posted count is the paper trail behind real adjustments.
        if ($stockTake->status === StockTakeStatus::Posted) {
            return redirect()
                ->route('stock-takes.show', $stockTake)
                ->with('error', __('app.stocktake.cannot_delete'));
        }

        $reference = $stockTake->reference;

        // Lines are removed by the cascade on the foreign key.
        $stockTake->delete();

        return redirect()
            ->route('stock-takes.index')
            ->with('status', __('app.stocktake.deleted_msg', ['reference' => $reference]));
    }

    /**
     * One page of the count sheet, ordered by product name.
     *
     * @return LengthAwarePaginator<int, \App\Models\StockTakeLine>
     */
    protected function sheet(StockTake $take): LengthAwarePaginator
    {
        return $take->lines()
            ->with('product')
            ->orderBy(
                Product::withTrashed()
                    ->select('name')
                    ->whereColumn('products.id', 'stock_take_lines.product_id')
            )
            ->paginate(self::SHEET_PER_PAGE)
            ->withQueryString();
    }

    protected function authorizeManage(): void
    {
        abort_unless((bool) request()->user()?->canManageCatalog(), 403);
    }
}
