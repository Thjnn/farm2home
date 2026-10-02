<?php

namespace App\Console\Commands;

use App\Services\SubscriptionService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('subscriptions:generate {date? : Ngày giao (Y-m-d), mặc định ngày mai}')]
#[Description('Sinh đơn cho các giỏ định kỳ đến lịch giao')]
class SubscriptionsGenerate extends Command
{
    public function handle(SubscriptionService $subscriptions): int
    {
        $date = CarbonImmutable::parse($this->argument('date') ?? today()->addDay());
        $count = $subscriptions->generateOrders($date);

        $this->info("Đã tạo {$count} đơn giỏ định kỳ cho ngày {$date->toDateString()}.");

        return self::SUCCESS;
    }
}
