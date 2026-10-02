<?php

namespace Database\Factories;

use App\Models\DeliveryZone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeliveryZone>
 */
class DeliveryZoneFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Cụm '.fake()->unique()->numberBetween(1, 9999),
            'description' => null,
            'shipping_fee' => 10000,
            'free_ship_from' => 150000,
            'pickup_point' => null,
            'is_active' => true,
        ];
    }
}
