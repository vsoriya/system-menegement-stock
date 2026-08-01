<?php

namespace Database\Factories;

use App\Enums\StockTakeStatus;
use App\Models\StockTake;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockTake>
 */
class StockTakeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reference' => 'Count '.fake()->unique()->numerify('###'),
            'created_by' => User::factory()->manager(),
            'category_id' => null,
            'status' => StockTakeStatus::Open,
            'counted_at' => fake()->dateTimeBetween('-1 month', 'now'),
            'posted_at' => null,
            'notes' => fake()->optional()->sentence(),
        ];
    }

    public function posted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => StockTakeStatus::Posted,
            'posted_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => StockTakeStatus::Cancelled,
        ]);
    }
}
