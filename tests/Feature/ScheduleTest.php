<?php

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ScheduleTest extends TestCase
{
    public function test_daily_jobs_are_scheduled_in_the_right_order(): void
    {
        // Gọi một lệnh artisan để console kernel nạp routes/console.php trước khi đọc lịch.
        Artisan::call('schedule:list');

        $events = collect(app(Schedule::class)->events())
            ->mapWithKeys(fn ($event) => [str($event->command)->after('artisan')->trim("' \"")->value() => $event->expression]);

        // Sinh đơn giỏ định kỳ trước giờ chốt 18:00, dự báo ngay sau giờ chốt, điền số thực tế cuối ngày.
        $this->assertSame('0 12 * * *', $events['subscriptions:generate']);
        $this->assertSame('15 18 * * *', $events['forecast:run']);
        $this->assertSame('0 21 * * *', $events['forecast:backfill']);
    }
}
