<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sale>
 */
class SaleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subtotal = fake()->randomFloat(2, 5, 500);

        return [
            'number' => 'INV-'.now()->year.'-'.fake()->unique()->numerify('######'),
            'customer_id' => null,
            'user_id' => User::factory(),
            'status' => SaleStatus::Completed,
            'payment_method' => PaymentMethod::Cash,
            'subtotal' => $subtotal,
            'discount' => 0,
            'total' => $subtotal,
            'paid' => $subtotal,
            'change_due' => 0,
            'sold_at' => fake()->dateTimeBetween('-1 month', 'now'),
            'voided_at' => null,
            'note' => fake()->optional()->sentence(),
        ];
    }

    public function voided(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SaleStatus::Voided,
            'voided_at' => now(),
        ]);
    }
}
