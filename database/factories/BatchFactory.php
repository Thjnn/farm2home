<?php

namespace Database\Factories;

use App\Models\Batch;
use App\Models\Farmer;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Batch>
 */
class BatchFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'F2H-'.now()->format('ymd').'-'.strtoupper(fake()->unique()->lexify('??')).'-NH'.fake()->numerify('##'),
            'farmer_id' => Farmer::factory(),
            'product_id' => Product::factory(),
            'quantity_kg' => 5,
            'harvested_at' => now(),
            'best_before' => today()->addDays(2)->toDateString(),
            'quality_status' => 'pending',
        ];
    }
}
