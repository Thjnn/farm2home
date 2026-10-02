<?php

namespace Database\Factories;

use App\Models\Basket;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Basket>
 */
class BasketFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Giỏ '.fake()->unique()->word(),
            'slug' => fake()->unique()->slug(2),
            'description' => null,
            'household_min' => 1,
            'household_max' => 2,
            'price' => 45000,
            'is_active' => true,
        ];
    }
}
