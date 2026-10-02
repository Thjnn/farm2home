<?php

namespace App\Services;

use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Enums\SubscriptionStatus;
use App\Models\Order;
use App\Models\Subscription;
use App\Support\SubscriptionSchedule;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class SubscriptionService
{
    public function __construct(private readonly OrderService $orders) {}

    /**
     * Tạo đơn (đã xác nhận) cho các giỏ định kỳ đến lịch giao. Chạy lại cùng ngày không tạo trùng.
     *
     * @return int số đơn vừa tạo
     */
    public function generateOrders(CarbonInterface $deliveryDate): int
    {
        $date = CarbonImmutable::parse($deliveryDate)->startOfDay();
        $created = 0;

        $subscriptions = Subscription::with('customer', 'basket')
            ->where('status', SubscriptionStatus::Active)
            ->where('delivery_weekday', $date->dayOfWeekIso)
            ->orderBy('id')
            ->get();

        foreach ($subscriptions as $subscription) {
            $due = SubscriptionSchedule::isDue(
                $subscription->frequency,
                (int) $subscription->delivery_weekday,
                CarbonImmutable::parse($subscription->start_date),
                $subscription->end_date === null ? null : CarbonImmutable::parse($subscription->end_date),
                $date,
            );

            if (! $due || ! $subscription->basket->is_active || $subscription->customer->delivery_zone_id === null) {
                continue;
            }

            if (Order::where('subscription_id', $subscription->id)->whereDate('delivery_date', $date->toDateString())->exists()) {
                continue;
            }

            $order = $this->orders->place([
                'name' => $subscription->customer->name,
                'phone' => $subscription->customer->phone,
                'address' => $subscription->customer->address,
                'delivery_zone_id' => $subscription->customer->delivery_zone_id,
                'delivery_date' => $date->toDateString(),
                'time_slot' => $subscription->time_slot,
                'order_type' => OrderType::Basket->value,
                'basket_id' => $subscription->basket_id,
                'payment_method' => PaymentMethod::Cod->value,
                'subscription_id' => $subscription->id,
            ], OrderChannel::Website);

            // Giỏ định kỳ là cam kết trước nên xác nhận luôn.
            $this->orders->transition($order, OrderStatus::Confirmed);
            $created++;
        }

        return $created;
    }
}
