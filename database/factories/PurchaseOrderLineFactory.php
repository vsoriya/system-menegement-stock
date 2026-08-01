<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseOrderLine>
 */
class PurchaseOrderLineFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'purchase_order_id' => PurchaseOrder::factory(),
            'product_id' => Product::factory(),
            'quantity_ordered' => fake()->numberBetween(1, 100),
            'quantity_received' => 0,
            'unit_cost' => fake()->randomFloat(2, 1, 300),
        ];
    }

    /**
     * Everything ordered has already arrived.
     */
    public function fullyReceived(): static
    {
        return $this->state(fn (array $attributes) => [
            'quantity_received' => $attributes['quantity_ordered'] ?? 1,
        ]);
    }
}
