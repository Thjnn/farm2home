<?php

namespace Database\Factories;

use App\Models\Forecast;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Forecast>
 */
class ForecastFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'target_date' => today()->addDay()->toDateString(),
            'generated_on' => today()->toDateString(),
            'method' => 'moving_average',
            'confirmed_kg' => 0,
            'predicted_extra_kg' => 0,
            'predicted_total_kg' => 0,
            'lower_kg' => 0,
            'upper_kg' => 0,
            'actual_kg' => null,
        ];
    }
}
