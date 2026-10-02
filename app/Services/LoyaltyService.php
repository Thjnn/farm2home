<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PointReason;
use App\Models\Customer;
use App\Models\Order;
use App\Models\PackagingReturn;
use App\Models\PointTransaction;
use App\Models\Product;
use App\Models\User;
use App\Support\Reward;
use Carbon\CarbonInterface;
use DomainException;
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

    /**
     * Chỉ đổi quà trước khi đóng gói và phân lô.
     */
    public function canRedeemOn(Order $order): bool
    {
        return in_array($order->status, [OrderStatus::Pending, OrderStatus::Confirmed], true);
    }

    public function redemptionFor(Order $order): ?PointTransaction
    {
        return PointTransaction::where('order_id', $order->id)->where('reason', PointReason::Redeemed)->first();
    }

    /**
     * Người điều phối duyệt một quà cho đơn: trừ điểm và sửa đơn trong cùng một transaction.
     *
     * @param  array<int, int|string|null>  $productIds  mỗi phần tử là một gói rau tặng; phần tử rỗng bị bỏ qua
     *
     * @throws DomainException khi đơn sai trạng thái, đã đổi quà, thiếu điểm hoặc quà không áp dụng được
     */
    public function redeem(Order $order, Reward $reward, array $productIds, User $by): PointTransaction
    {
        return DB::transaction(function () use ($order, $reward, $productIds, $by) {
            // Khóa dòng khách trước khi đọc số dư: hai lần duyệt cùng lúc không tiêu quá số điểm.
            $customer = Customer::whereKey($order->customer_id)->lockForUpdate()->firstOrFail();
            $order->refresh();

            if (! $this->canRedeemOn($order)) {
                throw new DomainException('Chỉ đổi quà được khi đơn đang chờ xác nhận hoặc đã xác nhận.');
            }

            if ($this->orderHas($order->id, PointReason::Redeemed)) {
                throw new DomainException('Đơn này đã đổi quà rồi.');
            }

            $balance = $this->balance($customer);

            if ($balance < $reward->points) {
                throw new DomainException("Khách chưa đủ điểm: có {$balance}, cần {$reward->points}.");
            }

            if ($reward->type === Reward::TYPE_GIFT) {
                $this->addGiftLines($order, $reward, $productIds);
            } elseif ($reward->type === Reward::TYPE_FREE_SHIPPING) {
                if ($order->shipping_fee === 0) {
                    throw new DomainException('Đơn này không có phí giao nên không cần đổi điểm miễn phí giao.');
                }

                // D22: miễn phí giao ghi vào shipping_fee, không ghi vào discount.
                $order->shipping_fee = 0;
            } elseif ($reward->type === Reward::TYPE_DISCOUNT) {
                $order->discount = min($order->subtotal, $order->discount + $reward->amount);
            } else {
                throw new DomainException('Loại quà không hợp lệ.');
            }

            $order->total = $order->subtotal + $order->shipping_fee - $order->discount;
            $order->save();

            return $this->record($customer->id, -$reward->points, PointReason::Redeemed, [
                'order_id' => $order->id,
                'reward' => $reward->key,
                'created_by' => $by->id,
            ]);
        });
    }

    /**
     * Hoàn đúng số điểm đã trừ khi đơn đã đổi quà bị hủy; gọi lại không hoàn lần hai.
     */
    public function refundForCancelledOrder(Order $order): ?PointTransaction
    {
        $redeemed = $this->redemptionFor($order);

        if ($redeemed === null || $this->orderHas($order->id, PointReason::Refunded)) {
            return null;
        }

        return $this->recordOnce($redeemed->customer_id, -$redeemed->points, PointReason::Refunded, [
            'order_id' => $order->id,
            'reward' => $redeemed->reward,
        ]);
    }

    /**
     * Gói rau tặng là dòng order_items giá 0đ, vẫn tính vào nhu cầu thu hoạch và phân lô (D23).
     *
     * @param  array<int, int|string|null>  $productIds
     */
    private function addGiftLines(Order $order, Reward $reward, array $productIds): void
    {
        $packs = collect($productIds)->filter()->map(fn ($id) => (int) $id)->countBy();

        if ($packs->sum() !== $reward->packs) {
            throw new DomainException("Quà này gồm đúng {$reward->packs} gói rau; vui lòng chọn đủ.");
        }

        $products = Product::where('is_active', true)->findMany($packs->keys());

        if ($products->count() !== $packs->count()) {
            throw new DomainException('Có loại rau tặng không còn bán.');
        }

        foreach ($products as $product) {
            $quantity = $packs[$product->id];
            $order->items()->create([
                'product_id' => $product->id,
                'basket_id' => null,
                'quantity' => $quantity,
                'weight_kg' => round($quantity * (float) $product->pack_weight_kg, 3),
                'unit_price' => 0,
                'line_total' => 0,
                'is_gift' => true,
            ]);
        }
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
