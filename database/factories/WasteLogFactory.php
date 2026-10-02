<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\WasteLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WasteLog>
 */
class WasteLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'log_date' => today()->toDateString(),
            'product_id' => Product::factory(),
            'batch_id' => null,
            'quantity_kg' => 1,
            'reason' => 'surplus',
        ];
    }
}
