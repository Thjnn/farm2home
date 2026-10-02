<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Sinh đơn giỏ định kỳ trước giờ chốt 18:00, dự báo ngay sau giờ chốt, điền số thực tế cuối ngày.
Schedule::command('subscriptions:generate')->dailyAt('12:00');
Schedule::command('forecast:run')->dailyAt('18:15');
Schedule::command('forecast:backfill')->dailyAt('21:00');
