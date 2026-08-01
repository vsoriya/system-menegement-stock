<?php

namespace Database\Factories;

use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseOrder>
 */
class PurchaseOrderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $orderedAt = fake()->dateTimeBetween('-2 months', 'now');

        return [
            'number' => 'PO-'.now()->year.'-'.fake()->unique()->numerify('####'),
            'supplier_id' => Supplier::factory(),
            'created_by' => User::factory(),
            'approved_by' => null,
            'status' => PurchaseOrderStatus::Draft,
            'ordered_at' => $orderedAt,
            'expected_at' => fake()->optional()->dateTimeBetween($orderedAt, '+1 month'),
            'approved_at' => null,
            'received_at' => null,
            'notes' => fake()->optional()->sentence(),
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PurchaseOrderStatus::Approved,
            'approved_by' => User::factory()->admin(),
            'approved_at' => now(),
        ]);
    }

    public function received(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PurchaseOrderStatus::Received,
            'approved_by' => User::factory()->admin(),
            'approved_at' => now()->subDay(),
            'received_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PurchaseOrderStatus::Cancelled,
        ]);
    }
}
