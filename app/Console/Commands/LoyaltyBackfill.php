<?php

namespace App\Console\Commands;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\PackagingReturn;
use App\Models\PointTransaction;
use App\Services\LoyaltyService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('loyalty:backfill')]
#[Description('Cộng điểm cho đơn đã giao và lượt trả bao bì chưa có trong sổ điểm (chạy lại không cộng trùng)')]
class LoyaltyBackfill extends Command
{
    public function handle(LoyaltyService $loyalty): int
    {
        $rows = 0;
        $points = 0;

        $count = function (?PointTransaction $transaction) use (&$rows, &$points): void {
            if ($transaction !== null) {
                $rows++;
                $points += $transaction->points;
            }
        };

        Order::where('status', OrderStatus::Delivered)->orderBy('id')
            ->each(fn (Order $order) => $count($loyalty->awardForDeliveredOrder($order)));

        PackagingReturn::whereNotNull('customer_id')->orderBy('id')
            ->each(fn (PackagingReturn $return) => $count($loyalty->awardForPackagingReturn($return)));

        $this->info("Đã cộng {$points} điểm trong {$rows} giao dịch.");

        return self::SUCCESS;
    }
}
