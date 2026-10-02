<?php

namespace Database\Factories;

use App\Models\PackagingReturn;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PackagingReturn>
 */
class PackagingReturnFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'return_date' => today()->toDateString(),
            'quantity_returned' => 1,
            'quantity_reused' => 1,
        ];
    }
}
