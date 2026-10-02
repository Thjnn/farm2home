<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\PointTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PointTransaction>
 */
class PointTransactionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'points' => 5,
            'reason' => 'order_delivered',
        ];
    }
}
