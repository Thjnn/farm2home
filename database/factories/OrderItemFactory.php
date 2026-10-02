<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'product_id' => Product::factory(),
            'basket_id' => null,
            'quantity' => 2,
            'weight_kg' => 1.0,
            'unit_price' => 12000,
            'line_total' => 24000,
            'fulfilled_quantity' => null,
        ];
    }
}
