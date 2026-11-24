<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\UnmetDemand;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UnmetDemand>
 */
class UnmetDemandFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'delivery_date' => today()->toDateString(),
            'product_id' => Product::factory(),
            'quantity_kg' => 1,
        ];
    }
}
