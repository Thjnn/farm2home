<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\DeliveryZone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'phone' => '09'.fake()->unique()->numerify('########'),
            'zalo_phone' => null,
            'address' => fake()->streetAddress(),
            'delivery_zone_id' => DeliveryZone::factory(),
            'source' => 'zalo',
            'data_consent' => true,
        ];
    }
}
