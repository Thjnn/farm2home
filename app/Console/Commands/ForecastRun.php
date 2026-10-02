<?php

namespace App\Console\Commands;

use App\Services\ForecastService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('forecast:run {date? : Ngày chạy dự báo (Y-m-d), mặc định hôm nay}')]
#[Description('Chạy dự báo nhu cầu cho các ngày giao sắp tới')]
class ForecastRun extends Command
{
    public function handle(ForecastService $forecasts): int
    {
        $date = CarbonImmutable::parse($this->argument('date') ?? today());
        $count = $forecasts->run($date);

        $this->info("Đã ghi {$count} dòng dự báo (ngày chạy {$date->toDateString()}).");

        return self::SUCCESS;
    }
}
