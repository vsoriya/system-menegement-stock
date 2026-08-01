<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SaleLine>
 */
class SaleLineFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $cost = fake()->randomFloat(2, 1, 100);

        return [
            'sale_id' => Sale::factory(),
            'product_id' => Product::factory(),
            'quantity' => fake()->numberBetween(1, 5),
            'unit_price' => round($cost * 1.4, 2),
            'unit_cost' => $cost,
        ];
    }
}
