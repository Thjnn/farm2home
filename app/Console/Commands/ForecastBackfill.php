<?php

namespace App\Console\Commands;

use App\Services\ForecastService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('forecast:backfill {date? : Điền số thực tế đến hết ngày này (Y-m-d), mặc định hôm nay}')]
#[Description('Điền actual_kg cho các dự báo đã qua ngày giao')]
class ForecastBackfill extends Command
{
    public function handle(ForecastService $forecasts): int
    {
        $date = CarbonImmutable::parse($this->argument('date') ?? today());
        $count = $forecasts->backfillActuals($date);

        $this->info("Đã cập nhật số thực tế cho {$count} dòng dự báo.");

        return self::SUCCESS;
    }
}
