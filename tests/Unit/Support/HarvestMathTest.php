<?php

namespace Tests\Unit\Support;

use App\Support\HarvestMath;
use PHPUnit\Framework\TestCase;

class HarvestMathTest extends TestCase
{
    public function test_ceil_to_half_kilogram(): void
    {
        $this->assertSame(2.0, HarvestMath::ceilToHalf(1.804));
        $this->assertSame(2.0, HarvestMath::ceilToHalf(2.0));
        $this->assertSame(2.5, HarvestMath::ceilToHalf(2.01));
        $this->assertSame(0.0, HarvestMath::ceilToHalf(0));
        $this->assertSame(0.0, HarvestMath::ceilToHalf(-1));
    }

    public function test_ceil_is_not_fooled_by_float_noise(): void
    {
        // 5 × 1.1 = 5.500000000000001 trong số thực; không được nhảy lên 6.0.
        $this->assertSame(5.5, HarvestMath::ceilToHalf(5 * 1.1));
    }

    public function test_floor_to_half_kilogram(): void
    {
        $this->assertSame(6.0, HarvestMath::floorToHalf(6.3));
        $this->assertSame(8.0, HarvestMath::floorToHalf(8.4));
        $this->assertSame(0.0, HarvestMath::floorToHalf(0.4));
    }

    public function test_suggested_kg_matches_rows_in_the_sample_data(): void
    {
        // harvest_plan_items id 1: confirmed 1.61, extra 0.03, margin 10% → 2.00
        $this->assertSame(2.0, HarvestMath::suggestedKg(1.61, 0.03, 0, 10));
        // harvest_plan_items id 119: confirmed 2.00 → 2.50
        $this->assertSame(2.5, HarvestMath::suggestedKg(2.0, 0, 0, 10));
        // harvest_plan_items id 113: confirmed 7.50 → 8.50
        $this->assertSame(8.5, HarvestMath::suggestedKg(7.5, 0, 0, 10));
    }

    public function test_stock_reduces_the_suggestion_and_never_goes_negative(): void
    {
        $this->assertSame(6.5, HarvestMath::suggestedKg(7.5, 0, 2.0, 10));
        $this->assertSame(0.0, HarvestMath::suggestedKg(2.0, 1.0, 5.0, 10));
    }

    public function test_one_farmer_covers_the_need(): void
    {
        $this->assertSame([['farmer_id' => 1, 'kg' => 3.0]], HarvestMath::allocate(3.0, [1 => 6.3, 3 => 4.0]));
    }

    public function test_largest_supplier_first_then_split(): void
    {
        $this->assertSame(
            [['farmer_id' => 3, 'kg' => 6.0], ['farmer_id' => 1, 'kg' => 2.0]],
            HarvestMath::allocate(8.0, [1 => 4.2, 3 => 6.3]),
        );
    }

    public function test_shortage_becomes_an_unassigned_row(): void
    {
        $this->assertSame(
            [['farmer_id' => 1, 'kg' => 2.0], ['farmer_id' => null, 'kg' => 3.0]],
            HarvestMath::allocate(5.0, [1 => 2.4]),
        );
        $this->assertSame([['farmer_id' => null, 'kg' => 2.5]], HarvestMath::allocate(2.5, []));
    }

    public function test_nothing_needed_means_no_rows(): void
    {
        $this->assertSame([], HarvestMath::allocate(0, [1 => 5]));
    }
}
