<?php

namespace Database\Factories;

use App\Models\Basket;
use App\Models\BasketItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BasketItem>
 */
class BasketItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'basket_id' => Basket::factory(),
            'product_id' => Product::factory(),
            'quantity' => 1,
            'allow_substitute' => true,
        ];
    }
}
