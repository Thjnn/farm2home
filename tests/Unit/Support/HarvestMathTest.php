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

    public function test_need_is_split_in_proportion_to_reported_supply(): void
    {
        // 30 × 40/55 = 21,8 và 30 × 15/55 = 8,2 → làm tròn 0,5 kg, phần lẻ cho nhà bị hụt nhiều nhất.
        $this->assertSame(
            [['farmer_id' => 1, 'kg' => 22.0], ['farmer_id' => 2, 'kg' => 8.0]],
            HarvestMath::allocate(30.0, [1 => 40.0, 2 => 15.0], 2.0),
        );
        // 8 × 6/10 = 4,8 và 8 × 4/10 = 3,2 (báo 6,3 và 4,2 tính là 6 và 4).
        $this->assertSame(
            [['farmer_id' => 3, 'kg' => 5.0], ['farmer_id' => 1, 'kg' => 3.0]],
            HarvestMath::allocate(8.0, [1 => 4.2, 3 => 6.3], 2.0),
        );
    }

    public function test_share_below_the_minimum_goes_to_the_other_farmers(): void
    {
        // Nhà 2 chỉ được 10 × 3/43 = 0,7 kg < 2 kg → nhà 1 nhận hết.
        $this->assertSame([['farmer_id' => 1, 'kg' => 10.0]], HarvestMath::allocate(10.0, [1 => 40.0, 2 => 3.0], 2.0));
        // Nhưng nếu thiếu nhà 2 thì không đủ rau, nhà 2 vẫn được giữ.
        $this->assertSame(
            [['farmer_id' => 1, 'kg' => 2.5], ['farmer_id' => 2, 'kg' => 2.0]],
            HarvestMath::allocate(4.5, [1 => 4.0, 2 => 3.0], 2.0),
        );
    }

    public function test_nobody_gets_more_than_they_reported(): void
    {
        foreach ([[12.5, [1 => 7.0, 2 => 6.0, 3 => 0.4]], [9.5, [1 => 9.6, 2 => 2.0]], [3.0, [1 => 1.5, 2 => 1.5, 3 => 1.5]]] as [$need, $supply]) {
            $rows = HarvestMath::allocate($need, $supply, 2.0);
            $this->assertEqualsWithDelta($need, array_sum(array_column($rows, 'kg')), 0.001);
            foreach ($rows as $row) {
                $this->assertLessThanOrEqual(HarvestMath::floorToHalf($supply[$row['farmer_id']]), $row['kg']);
            }
        }
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
