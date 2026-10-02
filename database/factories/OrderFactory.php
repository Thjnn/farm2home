<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\DeliveryZone;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'DH'.now()->format('ymd').'-'.fake()->unique()->numerify('###'),
            'customer_id' => Customer::factory(),
            'delivery_zone_id' => DeliveryZone::factory(),
            'order_type' => 'single',
            'channel' => 'zalo',
            'delivery_date' => today()->addDay()->toDateString(),
            'time_slot' => '17:00-19:00',
            'delivery_method' => 'home',
            'delivery_address' => fake()->streetAddress(),
            'subtotal' => 24000,
            'shipping_fee' => 10000,
            'discount' => 0,
            'total' => 34000,
            'payment_method' => 'cod',
            'payment_status' => 'unpaid',
            'status' => 'pending',
        ];
    }
}
