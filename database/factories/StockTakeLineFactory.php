<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\StockTake;
use App\Models\StockTakeLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockTakeLine>
 */
class StockTakeLineFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'stock_take_id' => StockTake::factory(),
            'product_id' => Product::factory(),
            'expected_quantity' => fake()->numberBetween(0, 200),
            'counted_quantity' => null,
        ];
    }

    /**
     * Counted, and the count agrees with the system.
     */
    public function matching(): static
    {
        return $this->state(fn (array $attributes) => [
            'counted_quantity' => $attributes['expected_quantity'] ?? 0,
        ]);
    }

    /**
     * Counted, and the count differs from the system.
     */
    public function withVariance(int $difference = 5): static
    {
        return $this->state(fn (array $attributes) => [
            'counted_quantity' => max(0, ($attributes['expected_quantity'] ?? 0) + $difference),
        ]);
    }
}
