<?php

namespace App\Http\Controllers;

use App\Enums\MovementType;
use App\Models\Product;
use App\Models\StockMovement;
use App\Services\StockService;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(StockService $stock): View
    {
        $since = Carbon::today()->subDays(29);

        return view('dashboard', [
            'summary' => $stock->summary(),

            'lowStockProducts' => Product::query()
                ->with(['category', 'supplier'])
                ->needsReorder()
                ->orderBy('quantity')
                ->limit(8)
                ->get(),

            'recentMovements' => StockMovement::query()
                ->with(['product', 'user'])
                ->latest()
                ->limit(10)
                ->get(),

            'topValueProducts' => Product::query()
                ->select('*')
                ->selectRaw('(quantity * cost_price) as computed_value')
                ->orderByDesc('computed_value')
                ->limit(5)
                ->get(),

            'movementTotals' => [
                'in' => (int) StockMovement::query()
                    ->where('type', MovementType::In)
                    ->where('created_at', '>=', $since)
                    ->sum('quantity_change'),
                'out' => (int) abs(StockMovement::query()
                    ->where('type', MovementType::Out)
                    ->where('created_at', '>=', $since)
                    ->sum('quantity_change')),
                'adjustments' => StockMovement::query()
                    ->where('type', MovementType::Adjustment)
                    ->where('created_at', '>=', $since)
                    ->count(),
            ],

            'dailyActivity' => $this->dailyActivity(14),
        ]);
    }

    /**
     * Units received and issued per day, with empty days filled in so the
     * chart keeps an even horizontal scale.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function dailyActivity(int $days): array
    {
        $from = Carbon::today()->subDays($days - 1);

        $rows = StockMovement::query()
            ->where('created_at', '>=', $from)
            ->whereIn('type', [MovementType::In, MovementType::Out])
            ->selectRaw('DATE(created_at) as day')
            ->selectRaw('SUM(CASE WHEN type = ? THEN quantity_change ELSE 0 END) as units_in', [MovementType::In->value])
            ->selectRaw('SUM(CASE WHEN type = ? THEN -quantity_change ELSE 0 END) as units_out', [MovementType::Out->value])
            ->groupByRaw('DATE(created_at)')
            ->get()
            ->keyBy(fn ($row) => (string) $row->day);

        return collect(range(0, $days - 1))
            ->map(function (int $offset) use ($from, $rows): array {
                $date = $from->copy()->addDays($offset);
                $row = $rows->get($date->toDateString());

                return [
                    'date' => $date,
                    'in' => (int) ($row->units_in ?? 0),
                    'out' => (int) ($row->units_out ?? 0),
                ];
            })
            ->all();
    }
}
