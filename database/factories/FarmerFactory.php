<?php

namespace Database\Factories;

use App\Models\Farmer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Farmer>
 */
class FarmerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'NH'.fake()->unique()->numerify('###'),
            'name' => fake()->name(),
            'phone' => '09'.fake()->unique()->numerify('########'),
            'zalo_phone' => null,
            'growing_area' => 'Xã Thân Cửu Nghĩa, Đồng Tháp',
            'access_token' => bin2hex(random_bytes(20)),
            'status' => 'active',
        ];
    }
}
