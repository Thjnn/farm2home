<?php

namespace Database\Factories;

use App\Models\HarvestPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HarvestPlan>
 */
class HarvestPlanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'harvest_date' => today()->addDay()->toDateString(),
            'delivery_date' => today()->addDay()->toDateString(),
            'safety_margin_pct' => 10,
            'status' => 'draft',
        ];
    }
}
