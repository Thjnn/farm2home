<?php

namespace Tests\Unit\Support;

use App\Support\OrderWindow;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class OrderWindowTest extends TestCase
{
    public function test_before_cutoff_tomorrow_is_still_open(): void
    {
        $now = CarbonImmutable::parse('2026-10-02 17:59:00', 'Asia/Ho_Chi_Minh');

        $this->assertSame('2026-10-03', OrderWindow::earliest($now, 18)->toDateString());
    }

    public function test_from_cutoff_the_earliest_day_moves_to_the_day_after_tomorrow(): void
    {
        $now = CarbonImmutable::parse('2026-10-02 18:00:00', 'Asia/Ho_Chi_Minh');

        $this->assertSame('2026-10-04', OrderWindow::earliest($now, 18)->toDateString());
    }

    public function test_latest_day_is_max_days_ahead(): void
    {
        $now = CarbonImmutable::parse('2026-10-02 10:00:00', 'Asia/Ho_Chi_Minh');

        $this->assertSame('2026-10-09', OrderWindow::latest($now, 7)->toDateString());
    }
}
