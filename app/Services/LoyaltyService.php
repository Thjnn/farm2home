<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PointReason;
use App\Models\Customer;
use App\Models\Order;
use App\Models\PackagingReturn;
use App\Models\PointTransaction;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Nơi duy nhất ghi vào point_transactions. Số dư = tổng các dòng của khách (D19).
 */
class LoyaltyService
{
    public function balance(Customer $customer): int
    {
        return (int) $customer->pointTransactions()->sum('points');
    }

    /**
     * Cộng điểm một lần cho đơn đã giao có tiền hàng đạt ngưỡng (D27).
     */
    public function awardForDeliveredOrder(Order $order): ?PointTransaction
    {
        if ($order->status !== OrderStatus::Delivered || $order->subtotal < (int) config('farm2home.loyalty.min_order_subtotal')) {
            return null;
        }

        if ($this->orderHas($order->id, PointReason::OrderDelivered)) {
            return null;
        }

        return $this->recordOnce(
            $order->customer_id,
            (int) config('farm2home.loyalty.order_points'),
            PointReason::OrderDelivered,
            ['order_id' => $order->id],
            $order->delivered_at,
        );
    }

    /**
     * Cộng điểm cho khách ghi trên lượt trả bao bì: một lần mỗi đơn; không có mã đơn thì một lần mỗi khách mỗi ngày.
     */
    public function awardForPackagingReturn(PackagingReturn $return): ?PointTransaction
    {
        if ($return->customer_id === null || $return->quantity_returned < 1) {
            return null;
        }

        return DB::transaction(function () use ($return) {
            // Khóa dòng khách để hai lần ghi nhận cùng lúc không cộng trùng.
            Customer::whereKey($return->customer_id)->lockForUpdate()->first();

            if ($this->packagingAlreadyCounted($return)) {
                return null;
            }

            return $this->recordOnce(
                $return->customer_id,
                (int) config('farm2home.loyalty.packaging_points'),
                PointReason::PackagingReturned,
                ['order_id' => $return->order_id, 'packaging_return_id' => $return->id],
                $return->created_at,
            );
        });
    }

    private function packagingAlreadyCounted(PackagingReturn $return): bool
    {
        if ($return->order_id !== null) {
            return $this->orderHas($return->order_id, PointReason::PackagingReturned);
        }

        return PointTransaction::where('customer_id', $return->customer_id)
            ->where('reason', PointReason::PackagingReturned)
            ->whereNull('order_id')
            ->whereHas('packagingReturn', fn ($query) => $query->whereDate('return_date', $return->return_date))
            ->exists();
    }

    private function orderHas(int $orderId, PointReason $reason): bool
    {
        return PointTransaction::where('order_id', $orderId)->where('reason', $reason)->exists();
    }

    /**
     * Như record(), nhưng trả null khi khóa unique (order_id, reason) báo đã có dòng (D20).
     *
     * @param  array<string, mixed>  $attributes
     */
    private function recordOnce(int $customerId, int $points, PointReason $reason, array $attributes, ?CarbonInterface $at = null): ?PointTransaction
    {
        try {
            return $this->record($customerId, $points, $reason, $attributes, $at);
        } catch (UniqueConstraintViolationException) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function record(int $customerId, int $points, PointReason $reason, array $attributes = [], ?CarbonInterface $at = null): PointTransaction
    {
        $transaction = new PointTransaction(['customer_id' => $customerId, 'points' => $points, 'reason' => $reason] + $attributes);
        // Thời điểm của dòng điểm = thời điểm của sự kiện gốc, để KPI theo kỳ vẫn đúng khi backfill.
        $transaction->created_at = $at ?? now();
        $transaction->updated_at = $transaction->created_at;
        $transaction->save();

        return $transaction;
    }
}
