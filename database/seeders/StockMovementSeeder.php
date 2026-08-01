<?php

namespace Database\Seeders;

use App\Enums\MovementType;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\StockService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class StockMovementSeeder extends Seeder
{
    /**
     * Products that should end up needing attention, so the low stock report
     * and dashboard warnings have something realistic to show.
     *
     * @var array<string, string>
     */
    protected array $targetStatus = [
        'TON-6001' => 'out',
        'NET-5002' => 'out',
        'MSE-3002' => 'low',
        'PAP-6002' => 'low',
        'CAM-3004' => 'low',
        'MON-2002' => 'low',
    ];

    public function __construct(protected StockService $stock) {}

    public function run(): void
    {
        $users = User::query()->orderBy('id')->get();

        if ($users->isEmpty()) {
            $this->command?->warn('No users found, skipping stock movements.');

            return;
        }

        // Start clean so re-running the seeder does not stack up history.
        StockMovement::query()->delete();
        Product::query()->update(['quantity' => 0]);

        try {
            Product::query()->orderBy('id')->each(
                fn (Product $product) => $this->seedProduct($product, $users)
            );
        } finally {
            // Always release the frozen clock, even if seeding fails.
            Carbon::setTestNow();
        }
    }

    /**
     * @param  Collection<int, User>  $users
     */
    protected function seedProduct(Product $product, Collection $users): void
    {
        $openingQuantity = max(10, $product->reorder_level * 4 + random_int(0, 25));

        // Opening stock, roughly three months back.
        $this->recordAt(
            Carbon::today()->subDays(92)->setTime(9, 0),
            fn () => $this->stock->record(
                $product,
                MovementType::In,
                $openingQuantity,
                [
                    'reference' => 'Opening stock',
                    'unit_cost' => $product->cost_price,
                    'note' => 'Initial balance loaded when the system went live.',
                ],
                $users->first(),
            ),
        );

        // A run of receipts and issues spread over the last three months.
        $date = Carbon::today()->subDays(85);
        $today = Carbon::today();

        while ($date->lessThan($today)) {
            $date = $date->copy()->addDays(random_int(3, 11));

            if ($date->greaterThanOrEqualTo($today)) {
                break;
            }

            $product->refresh();
            $onHand = $product->quantity;
            $user = $users->random();
            $moment = $date->copy()->setTime(random_int(8, 17), random_int(0, 59));

            // Mostly issues, with the occasional restock.
            $wantsRestock = $onHand <= $product->reorder_level || random_int(1, 100) <= 30;

            if ($wantsRestock) {
                $quantity = random_int(max(5, (int) ($product->reorder_level * 1.5)), max(12, $product->reorder_level * 4));

                $this->recordAt($moment, fn () => $this->stock->record(
                    $product,
                    MovementType::In,
                    $quantity,
                    [
                        'reference' => 'PO-'.random_int(1000, 9999),
                        'unit_cost' => $product->cost_price,
                    ],
                    $user,
                ));

                continue;
            }

            if ($onHand < 1) {
                continue;
            }

            $quantity = random_int(1, max(1, (int) ceil($onHand / 3)));

            $this->recordAt($moment, fn () => $this->stock->record(
                $product,
                MovementType::Out,
                $quantity,
                ['reference' => 'INV-'.random_int(10000, 99999)],
                $user,
            ));
        }

        $this->applyTargetStatus($product, $users);
    }

    /**
     * Nudge selected products to a low or zero balance with a stock count
     * adjustment, which also demonstrates the adjustment movement type.
     *
     * @param  Collection<int, User>  $users
     */
    protected function applyTargetStatus(Product $product, Collection $users): void
    {
        $target = $this->targetStatus[$product->sku] ?? null;

        if ($target === null) {
            return;
        }

        $product->refresh();

        $counted = $target === 'out'
            ? 0
            : random_int(1, max(1, $product->reorder_level));

        if ($counted === $product->quantity) {
            return;
        }

        $this->recordAt(
            Carbon::today()->subDays(random_int(0, 2))->setTime(16, 30),
            fn () => $this->stock->record(
                $product,
                MovementType::Adjustment,
                $counted,
                [
                    'reference' => 'Stock count',
                    'note' => $target === 'out'
                        ? 'Physical count found no units left on the shelf.'
                        : 'Physical count came in below the system balance.',
                ],
                $users->random(),
            ),
        );
    }

    /**
     * Run a callback with the clock frozen, so created_at lands in the past.
     */
    protected function recordAt(Carbon $moment, callable $callback): void
    {
        Carbon::setTestNow($moment);

        try {
            $callback();
        } finally {
            Carbon::setTestNow();
        }
    }
}
