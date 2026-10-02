<?php

namespace Database\Factories;

use App\Models\Farmer;
use App\Models\Product;
use App\Models\SupplyReport;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupplyReport>
 */
class SupplyReportFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'farmer_id' => Farmer::factory(),
            'product_id' => Product::factory(),
            'available_date' => today()->addDay()->toDateString(),
            'quantity_kg' => 5,
            'source' => 'form',
        ];
    }
}
