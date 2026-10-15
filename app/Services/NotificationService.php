<?php

namespace App\Services;

use App\Enums\FeedbackStatus;
use App\Enums\OrderStatus;
use App\Enums\SupplySource;
use App\Models\Feedback;
use App\Models\HarvestPlanItem;
use App\Models\Order;
use App\Models\SupplyReport;
use Carbon\CarbonInterface;

/**
 * Thông báo cho trang quản trị: những gì xảy ra trong khoảng ($since, $until], trang hỏi lại mỗi vài giây.
 */
class NotificationService
{
    private const MAX_EVENTS = 10;

    /**
     * Số hiện trên menu: đơn chờ xác nhận và phản hồi chưa xử lý.
     *
     * @return array{orders: int, feedbacks: int}
     */
    public function badges(): array
    {
        return [
            'orders' => Order::where('status', OrderStatus::Pending)->count(),
            'feedbacks' => Feedback::where('status', FeedbackStatus::New)->count(),
        ];
    }

    /**
     * @return list<array{kind: string, text: string, url: string}>
     */
    public function events(CarbonInterface $since, CarbonInterface $until): array
    {
        $window = fn (string $column) => fn ($query) => $query->where($column, '>', $since)->where($column, '<=', $until);

        $newOrders = Order::with('customer')->where($window('created_at'))->latest()->limit(self::MAX_EVENTS)->get()
            ->map(fn (Order $order) => [
                'kind' => 'order',
                'text' => "Đơn mới {$order->code} · {$order->customer->name} · giao {$order->delivery_date->format('d/m')}",
                'url' => route('admin.orders.show', $order),
            ]);

        // Đơn cũ vừa đổi trạng thái (người khác bấm, hoặc giỏ định kỳ tự sinh).
        $changedOrders = Order::where($window('updated_at'))->where('created_at', '<=', $since)->latest('updated_at')->limit(self::MAX_EVENTS)->get()
            ->map(fn (Order $order) => [
                'kind' => 'status',
                'text' => "Đơn {$order->code} → {$order->status->label()}",
                'url' => route('admin.orders.show', $order),
            ]);

        $supply = SupplyReport::with('farmer')->where($window('updated_at'))->where('source', '!=', SupplySource::Operator)->get()
            ->groupBy('farmer_id')
            ->map(fn ($reports) => [
                'kind' => 'supply',
                'text' => "Nông hộ {$reports->first()->farmer->name} vừa báo sản lượng ({$reports->count()} dòng)",
                'url' => route('admin.supply.index'),
            ])->values();

        $confirmations = HarvestPlanItem::with(['farmer', 'plan'])->where($window('farmer_confirmed_at'))->get()
            ->groupBy(fn (HarvestPlanItem $item) => $item->harvest_plan_id.'#'.$item->farmer_id)
            ->map(fn ($items) => [
                'kind' => 'harvest',
                'text' => "Nông hộ {$items->first()->farmer->name} đã xác nhận thu hoạch cho ngày giao {$items->first()->plan->delivery_date->format('d/m')}",
                'url' => route('admin.harvest-plans.show', $items->first()->harvest_plan_id),
            ])->values();

        $feedbacks = Feedback::where($window('created_at'))->latest()->limit(self::MAX_EVENTS)->get()
            ->map(fn (Feedback $feedback) => [
                'kind' => 'feedback',
                'text' => "Phản hồi mới: {$feedback->rating}★".($feedback->comment ? ' · '.str($feedback->comment)->limit(60) : ''),
                'url' => route('admin.feedbacks.index'),
            ]);

        return $newOrders->concat($changedOrders)->concat($supply)->concat($confirmations)->concat($feedbacks)
            ->take(self::MAX_EVENTS)->values()->all();
    }
}
