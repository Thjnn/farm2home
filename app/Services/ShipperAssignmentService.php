<?php

namespace App\Services;

use App\Enums\DeliveryMethod;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use App\Support\ShipperSplit;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Gán đơn giao tận nhà cho người giao theo cụm (quyết định 2026-11-26: cân theo ngày, cụm đã có người thì giữ).
 * Chỉ đơn còn trong kho (đã xác nhận / đang đóng gói) được dời người; đơn đang giao giữ người đang cầm hàng.
 */
class ShipperAssignmentService
{
    private const ASSIGNABLE = [OrderStatus::Confirmed, OrderStatus::Packing, OrderStatus::Delivering];

    private const IN_WAREHOUSE = [OrderStatus::Confirmed, OrderStatus::Packing];

    /**
     * @return Collection<int, User>
     */
    public function workingShippers(CarbonInterface $date): Collection
    {
        return User::shippers()
            ->where(fn ($query) => $query->whereNull('off_date')->orWhereDate('off_date', '!=', $date->toDateString()))
            ->get();
    }

    /**
     * Gán người giao cho mọi đơn chưa có người của ngày đó.
     */
    public function assign(CarbonInterface $date): void
    {
        $working = $this->workingShippers($date)->pluck('id')->all();
        if ($working === []) {
            return;
        }

        $assigned = $this->ordersOf($date)->where('status', '!=', OrderStatus::Cancelled)->whereIn('shipper_id', $working)
            ->get(['id', 'delivery_zone_id', 'shipper_id']);

        $loads = array_fill_keys($working, 0);
        foreach ($assigned->countBy('shipper_id') as $shipperId => $count) {
            $loads[$shipperId] = $count;
        }

        // Cụm đã có người: người giữ nhiều đơn nhất của cụm đó (trường hợp operator đã đổi tay một vài đơn).
        $sticky = $assigned->groupBy('delivery_zone_id')
            ->map(fn (Collection $orders) => (int) $orders->countBy('shipper_id')->sortDesc()->keys()->first())
            ->all();

        $open = $this->ordersOf($date)->whereIn('status', self::ASSIGNABLE)->whereNull('shipper_id')->get(['id', 'delivery_zone_id']);
        $zoneCounts = $open->countBy('delivery_zone_id')->all();

        $split = ShipperSplit::assign($zoneCounts, $sticky, $loads);

        DB::transaction(function () use ($open, $split) {
            foreach ($open->groupBy('delivery_zone_id') as $zoneId => $orders) {
                Order::whereIn('id', $orders->pluck('id'))->update(['shipper_id' => $split[$zoneId]]);
            }
        });
    }

    /**
     * Nút "Chia lại": gỡ người giao khỏi đơn còn trong kho rồi chia lại.
     */
    public function rebalance(CarbonInterface $date): void
    {
        DB::transaction(function () use ($date) {
            $this->ordersOf($date)->whereIn('status', self::IN_WAREHOUSE)->update(['shipper_id' => null]);
            $this->assign($date);
        });
    }

    /**
     * Đánh dấu nghỉ / đi làm lại. Nghỉ thì đơn còn trong kho của người đó chuyển sang người còn lại.
     */
    public function setDayOff(User $shipper, CarbonInterface $date, bool $off): void
    {
        DB::transaction(function () use ($shipper, $date, $off) {
            if ($off) {
                $shipper->update(['off_date' => $date->toDateString()]);
                $this->ordersOf($date)->whereIn('status', self::IN_WAREHOUSE)->where('shipper_id', $shipper->id)->update(['shipper_id' => null]);
                $this->assign($date);
            } elseif ($shipper->off_date?->isSameDay($date)) {
                $shipper->update(['off_date' => null]);
            }
        });
    }

    /**
     * Operator chọn người giao cho cả cụm (ghi đè, kể cả đơn đang giao — operator tự quyết khi bấm).
     */
    public function assignZone(CarbonInterface $date, int $zoneId, ?int $shipperId): void
    {
        $this->ordersOf($date)->where('delivery_zone_id', $zoneId)->whereIn('status', self::ASSIGNABLE)->update(['shipper_id' => $shipperId]);
    }

    /**
     * @return Builder<Order>
     */
    private function ordersOf(CarbonInterface $date): Builder
    {
        return Order::whereDate('delivery_date', $date->toDateString())->where('delivery_method', DeliveryMethod::Home);
    }
}
