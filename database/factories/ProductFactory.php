<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $cost = fake()->randomFloat(2, 1, 400);

        return [
            'sku' => strtoupper(fake()->unique()->bothify('???-####')),
            'name' => ucfirst(fake()->words(2, true)),
            'description' => fake()->optional()->sentence(),
            'category_id' => Category::factory(),
            'supplier_id' => Supplier::factory(),
            'unit' => fake()->randomElement(['pcs', 'box', 'kg', 'litre', 'pack']),
            'cost_price' => $cost,
            // Sell at a 20% to 70% markup on cost.
            'sale_price' => round($cost * fake()->randomFloat(2, 1.2, 1.7), 2),
            'quantity' => fake()->numberBetween(0, 250),
            'reorder_level' => fake()->numberBetween(5, 40),
            'is_active' => true,
        ];
    }

    public function outOfStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'quantity' => 0,
        ]);
    }

    /**
     * On hand, but at or below the reorder level.
     */
    public function lowStock(): static
    {
        return $this->state(function (array $attributes) {
            $reorderLevel = fake()->numberBetween(10, 30);

            return [
                'reorder_level' => $reorderLevel,
                'quantity' => fake()->numberBetween(1, $reorderLevel),
            ];
        });
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
