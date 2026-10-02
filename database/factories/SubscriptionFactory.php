<?php

namespace Database\Factories;

use App\Models\Basket;
use App\Models\Customer;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
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
            'basket_id' => Basket::factory(),
            'frequency' => 'weekly',
            'delivery_weekday' => 6,
            'time_slot' => '17:00-19:00',
            'start_date' => today()->toDateString(),
            'end_date' => null,
            'status' => 'active',
        ];
    }
}
