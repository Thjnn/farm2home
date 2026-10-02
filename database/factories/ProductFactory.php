<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->lexify('???')),
            'name' => 'Rau '.fake()->unique()->word(),
            'slug' => fake()->unique()->slug(2),
            'pack_weight_kg' => 0.5,
            'price' => 12000,
            'cost_price_per_kg' => 14000,
            'shelf_life_days' => 3,
            'storage_guide' => 'Bảo quản ngăn mát 4–8°C.',
            'is_active' => true,
        ];
    }
}
