<?php

namespace Database\Factories;

use App\Models\Farmer;
use App\Models\HarvestPlan;
use App\Models\HarvestPlanItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HarvestPlanItem>
 */
class HarvestPlanItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'harvest_plan_id' => HarvestPlan::factory(),
            'product_id' => Product::factory(),
            'farmer_id' => Farmer::factory(),
            'confirmed_order_kg' => 0,
            'forecast_extra_kg' => 0,
            'available_stock_kg' => 0,
            'suggested_kg' => 1,
        ];
    }
}
