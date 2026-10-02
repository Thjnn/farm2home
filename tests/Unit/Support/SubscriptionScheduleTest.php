<?php

namespace Tests\Unit\Support;

use App\Enums\SubscriptionFrequency;
use App\Support\SubscriptionSchedule;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class SubscriptionScheduleTest extends TestCase
{
    private function d(string $date): CarbonImmutable
    {
        return CarbonImmutable::parse($date, 'Asia/Ho_Chi_Minh');
    }

    public function test_weekly_is_due_every_matching_weekday(): void
    {
        // 15/09/2026 là Thứ Ba (weekday 2).
        $this->assertTrue(SubscriptionSchedule::isDue(SubscriptionFrequency::Weekly, 2, $this->d('2026-09-15'), null, $this->d('2026-10-06')));
        $this->assertFalse(SubscriptionSchedule::isDue(SubscriptionFrequency::Weekly, 2, $this->d('2026-09-15'), null, $this->d('2026-10-07')));
    }

    public function test_not_due_before_start_or_after_end(): void
    {
        $this->assertFalse(SubscriptionSchedule::isDue(SubscriptionFrequency::Weekly, 2, $this->d('2026-09-15'), null, $this->d('2026-09-08')));
        $this->assertFalse(SubscriptionSchedule::isDue(SubscriptionFrequency::Weekly, 2, $this->d('2026-09-15'), $this->d('2026-09-30'), $this->d('2026-10-06')));
    }

    public function test_biweekly_counts_from_the_first_delivery(): void
    {
        // Bắt đầu Thứ Năm 10/09, giao Thứ Bảy (6): lần đầu 12/09, rồi 26/09.
        $start = $this->d('2026-09-10');

        $this->assertTrue(SubscriptionSchedule::isDue(SubscriptionFrequency::Biweekly, 6, $start, null, $this->d('2026-09-12')));
        $this->assertFalse(SubscriptionSchedule::isDue(SubscriptionFrequency::Biweekly, 6, $start, null, $this->d('2026-09-19')));
        $this->assertTrue(SubscriptionSchedule::isDue(SubscriptionFrequency::Biweekly, 6, $start, null, $this->d('2026-09-26')));
    }

    public function test_monthly_means_every_four_weeks(): void
    {
        $start = $this->d('2026-09-10');

        $this->assertFalse(SubscriptionSchedule::isDue(SubscriptionFrequency::Monthly, 6, $start, null, $this->d('2026-09-26')));
        $this->assertTrue(SubscriptionSchedule::isDue(SubscriptionFrequency::Monthly, 6, $start, null, $this->d('2026-10-10')));
    }

    public function test_sunday_is_weekday_seven(): void
    {
        $this->assertTrue(SubscriptionSchedule::isDue(SubscriptionFrequency::Weekly, 7, $this->d('2026-09-10'), null, $this->d('2026-09-13')));
    }
}
