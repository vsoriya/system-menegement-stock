<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'phone' => fake()->numerify('0## ### ###'),
            // Not chained into unique(): optional() returns a ChanceGenerator
            // that yields null about half the time, and null->safeEmail() is a
            // fatal error. The customers table has no unique index on email
            // anyway, so a repeat is harmless.
            'email' => fake()->optional()->safeEmail(),
            'address' => fake()->optional()->streetAddress(),
            'notes' => fake()->optional()->sentence(),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
