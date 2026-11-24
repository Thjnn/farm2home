<?php

namespace App\Services;

use App\Enums\FarmerStatus;
use App\Enums\FeedbackStatus;
use App\Enums\HarvestPlanStatus;
use App\Enums\OrderStatus;
use App\Models\Farmer;
use App\Models\Feedback;
use App\Models\HarvestPlan;
use App\Models\Order;
use Carbon\CarbonImmutable;

/**
 * Khối "Hôm nay" đầu trang Tổng quan: những việc người vận hành cần xử lý, mỗi việc dẫn tới đúng trang.
 */
class TodayTasksService
{
    /**
     * @return list<array{key: string, label: string, count: int, detail: string, url: string}>
     */
    public function tasks(CarbonImmutable $today): array
    {
        $tomorrow = $today->addDay();

        $pending = Order::where('status', OrderStatus::Pending)->whereDate('delivery_date', '>=', $today);
        $nearestPendingDate = (clone $pending)->min('delivery_date');

        $deliveringToday = Order::whereDate('delivery_date', $today)
            ->whereNotIn('status', [OrderStatus::Delivered, OrderStatus::Cancelled])
            ->count();

        $drafts = HarvestPlan::where('status', HarvestPlanStatus::Draft)->whereDate('delivery_date', '>=', $today)->orderBy('delivery_date');
        $firstDraft = (clone $drafts)->first();

        $farmersWithoutSupply = Farmer::where('status', FarmerStatus::Active)
            ->whereDoesntHave('supplyReports', fn ($query) => $query->whereDate('available_date', $tomorrow))
            ->count();

        return [
            [
                'key' => 'pending',
                'label' => 'Đơn chờ xác nhận',
                'count' => $pending->count(),
                'detail' => $nearestPendingDate ? 'Gần nhất giao '.CarbonImmutable::parse($nearestPendingDate)->format('d/m') : 'Không còn đơn chờ',
                'url' => route('admin.orders.index', array_filter([
                    'delivery_date' => $nearestPendingDate ? CarbonImmutable::parse($nearestPendingDate)->toDateString() : null,
                    'status' => OrderStatus::Pending->value,
                ])),
            ],
            [
                'key' => 'today',
                'label' => 'Đơn giao hôm nay',
                'count' => $deliveringToday,
                'detail' => 'Chưa giao xong',
                'url' => route('admin.orders.index', ['delivery_date' => $today->toDateString()]),
            ],
            [
                'key' => 'drafts',
                'label' => 'Kế hoạch chưa chốt',
                'count' => $drafts->count(),
                'detail' => $firstDraft ? 'Giao '.$firstDraft->delivery_date->format('d/m') : 'Đã chốt hết',
                'url' => $firstDraft ? route('admin.harvest-plans.show', $firstDraft) : route('admin.harvest-plans.index'),
            ],
            [
                'key' => 'supply',
                'label' => 'HTX chưa báo sản lượng',
                'count' => $farmersWithoutSupply,
                'detail' => 'Cho ngày '.$tomorrow->format('d/m'),
                'url' => route('admin.supply.index'),
            ],
            [
                'key' => 'feedbacks',
                'label' => 'Phản hồi mới',
                'count' => Feedback::where('status', FeedbackStatus::New)->count(),
                'detail' => 'Chưa xử lý',
                'url' => route('admin.feedbacks.index', ['status' => FeedbackStatus::New->value]),
            ],
        ];
    }
}
