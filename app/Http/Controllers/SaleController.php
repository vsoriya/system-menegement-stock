<?php

namespace App\Http\Controllers;

use App\Enums\SaleStatus;
use App\Models\Sale;
use App\Services\SaleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class SaleController extends Controller
{
    /**
     * The service is named $saleService, not $sales, on purpose: $sales is the
     * paginated listing below, and reusing the name silently overwrote the
     * injected service.
     */
    public function index(Request $request, SaleService $saleService): View
    {
        $search = $request->string('search')->toString();
        $status = $request->string('status')->toString();
        $from = $request->string('from')->toString();
        $to = $request->string('to')->toString();

        // Built twice from scratch rather than cloned. The listing needs a
        // withSum subquery in its select list, and appending COUNT(*) to that
        // would produce an aggregate mixed with plain columns, which MySQL
        // rejects outright and SQLite answers with nonsense.
        $filtered = fn () => Sale::query()
            ->search($search)
            ->withStatus($status)
            ->betweenDates($from, $to);

        $sales = $filtered()
            ->with(['customer', 'cashier'])
            ->withSum('lines as units_sold', 'quantity')
            ->latest('sold_at')
            ->paginate(20)
            ->withQueryString();

        // Totals cover the whole filtered set, not just the page on screen, so
        // the figures answer "what did we take this week".
        $totals = $filtered()
            ->completed()
            ->toBase()
            ->selectRaw('COUNT(*) as sales_count')
            ->selectRaw('COALESCE(SUM(total), 0) as revenue')
            ->first();

        return view('sales.index', [
            'sales' => $sales,
            'filters' => $request->only(['search', 'status', 'from', 'to']),
            'statuses' => SaleStatus::options(),
            'salesCount' => (int) ($totals->sales_count ?? 0),
            'revenue' => round((float) ($totals->revenue ?? 0), 2),
            'today' => $saleService->dailyTotals(),
        ]);
    }

    public function show(Sale $sale): View
    {
        $sale->load(['customer', 'cashier', 'lines.product']);

        return view('sales.show', [
            'sale' => $sale,
        ]);
    }

    /**
     * The printable invoice, in two shapes.
     *
     * a5 is a paper invoice to hand over or file. receipt is sized for an 80mm
     * thermal roll, which is what most counters in Cambodia actually have.
     *
     * Both render standalone pages with their own stylesheet rather than the app
     * layout. The app layout carries a sidebar, a theme and colour tokens that
     * all fight a printer, and a thermal roll is 72mm of printable width.
     */
    public function print(Sale $sale, string $format = 'a5'): View
    {
        $sale->load(['customer', 'cashier', 'lines.product']);

        return view($format === 'receipt' ? 'sales.print.receipt' : 'sales.print.a5', [
            'sale' => $sale,
        ]);
    }

    /**
     * Reversing a sale moves money and stock, so it is kept to managers and
     * admins. Staff can sell but not undo.
     */
    public function void(Request $request, Sale $sale, SaleService $saleService): RedirectResponse
    {
        abort_unless((bool) $request->user()?->canManageCatalog(), 403);

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $saleService->void($sale, $request->user(), $validated['reason'] ?? null);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['reason' => $e->getMessage()]);
        }

        return redirect()
            ->route('sales.show', $sale)
            ->with('status', __('app.sale.voided_msg', ['number' => $sale->number]));
    }
}
