<?php

namespace App\Services;

use App\Enums\FarmerStatus;
use App\Enums\FeedbackStatus;
use App\Enums\OrderStatus;
use App\Enums\PointReason;
use App\Models\Batch;
use App\Models\Farmer;
use App\Models\Feedback;
use App\Models\HarvestPlanItem;
use App\Models\PackagingReturn;
use App\Models\PointTransaction;
use App\Models\WasteLog;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Pilot KPIs (spec section 7). Every method is read-only and filters by delivery date unless noted.
 * Ratios are percentages rounded to one decimal, or null when the denominator is zero.
 */
class KpiService
{
    public function __construct(private readonly ForecastService $forecasts) {}

    /**
     * @return array{accuracy: array<string, array{n: int, mae: ?float, mape: ?float, wape: ?float, bias: ?float}>, plan_items: int, adjusted_items: int}
     */
    public function forecast(CarbonInterface $from, CarbonInterface $to): array
    {
        $items = $this->planItems($from, $to);

        return [
            'accuracy' => $this->forecasts->accuracy($from, $to),
            'plan_items' => (clone $items)->count(),
            // Số lần operator chốt khác số gợi ý.
            'adjusted_items' => (clone $items)->whereColumn('final_kg', '!=', 'suggested_kg')->count(),
        ];
    }

    /**
     * @return array{fill_rate_pct: ?float, customers: int, repeat_customers: int, repeat_rate_pct: ?float, feedback_total: int, feedback_resolved: int, avg_rating: ?float}
     */
    public function customers(CarbonInterface $from, CarbonInterface $to): array
    {
        $dates = $this->dates($from, $to);

        $fill = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereBetween('orders.delivery_date', $dates)
            ->whereNotNull('order_items.fulfilled_quantity')
            ->selectRaw('COALESCE(SUM(order_items.fulfilled_quantity), 0) as fulfilled, COALESCE(SUM(order_items.quantity), 0) as ordered')
            ->first();

        $ordersPerCustomer = DB::table('orders')
            ->whereBetween('delivery_date', $dates)
            ->where('status', '!=', OrderStatus::Cancelled->value)
            ->groupBy('customer_id')
            ->selectRaw('customer_id, COUNT(*) as orders')
            ->pluck('orders', 'customer_id');

        $repeat = $ordersPerCustomer->filter(fn ($orders) => $orders >= 2)->count();

        $feedbacks = Feedback::whereBetween('created_at', $this->moments($from, $to));
        $averageRating = (clone $feedbacks)->avg('rating');

        return [
            'fill_rate_pct' => self::pct((float) $fill->fulfilled, (float) $fill->ordered),
            'customers' => $ordersPerCustomer->count(),
            'repeat_customers' => $repeat,
            'repeat_rate_pct' => self::pct($repeat, $ordersPerCustomer->count()),
            'feedback_total' => (clone $feedbacks)->count(),
            'feedback_resolved' => (clone $feedbacks)->where('status', FeedbackStatus::Resolved)->count(),
            'avg_rating' => $averageRating === null ? null : round((float) $averageRating, 2),
        ];
    }

    /**
     * Quyết định D16: giá vốn tính trên toàn bộ hàng đã nhận, nên rau dư làm giảm biên gộp.
     *
     * @return array{delivered_orders: int, revenue: int, avg_order_value: ?int, goods_revenue: int, purchase_cost: int, gross_margin_pct: ?float, shipping_per_order: ?int, revenue_by_day: array<string, int>}
     */
    public function economics(CarbonInterface $from, CarbonInterface $to): array
    {
        $delivered = DB::table('orders')
            ->whereBetween('delivery_date', $this->dates($from, $to))
            ->where('status', OrderStatus::Delivered->value);

        $count = (clone $delivered)->count();
        $revenue = (int) (clone $delivered)->sum('total');
        $goods = (int) (clone $delivered)->sum('subtotal') - (int) (clone $delivered)->sum('discount');
        $shipping = (int) (clone $delivered)->sum('shipping_fee');

        $cost = (int) round((float) DB::table('batches')
            ->join('products', 'products.id', '=', 'batches.product_id')
            ->whereBetween('batches.harvested_at', $this->moments($from, $to))
            ->sum(DB::raw('batches.quantity_kg * products.cost_price_per_kg')));

        return [
            'delivered_orders' => $count,
            'revenue' => $revenue,
            'avg_order_value' => $count > 0 ? (int) round($revenue / $count) : null,
            'goods_revenue' => $goods,
            'purchase_cost' => $cost,
            'gross_margin_pct' => self::pct($goods - $cost, $goods),
            'shipping_per_order' => $count > 0 ? (int) round($shipping / $count) : null,
            'revenue_by_day' => (clone $delivered)
                ->groupBy('delivery_date')
                ->orderBy('delivery_date')
                ->selectRaw('delivery_date as day, SUM(total) as total')
                ->pluck('total', 'day')
                ->map(fn ($total) => (int) $total)
                ->all(),
        ];
    }

    /**
     * @return array{active_farmers: int, advance_confirmed_pct: ?float}
     */
    public function farmers(CarbonInterface $from, CarbonInterface $to): array
    {
        $items = $this->planItems($from, $to);
        $finalKg = (float) (clone $items)->sum('final_kg');
        $confirmedKg = (float) (clone $items)->whereNotNull('farmer_confirmed_at')->sum('final_kg');

        return [
            'active_farmers' => Farmer::where('status', FarmerStatus::Active)->count(),
            // % sản lượng nông hộ đã xác nhận trước khi thu hoạch.
            'advance_confirmed_pct' => self::pct($confirmedKg, $finalKg),
        ];
    }

    /**
     * Rau dư và hàng nhận theo tuần ISO (kg), cùng tỷ lệ thu hồi/tái sử dụng bao bì. Không có chỉ số CO₂.
     *
     * @return array{waste_by_week: array<string, float>, received_by_week: array<string, float>, waste_kg: float, received_kg: float, waste_pct: ?float, packaging_returned: int, packaging_reused: int, packaging_reuse_pct: ?float}
     */
    public function environment(CarbonInterface $from, CarbonInterface $to): array
    {
        $waste = WasteLog::whereBetween('log_date', $this->dates($from, $to))->get(['log_date', 'quantity_kg']);
        $batches = Batch::whereBetween('harvested_at', $this->moments($from, $to))->get(['harvested_at', 'quantity_kg']);
        $packaging = PackagingReturn::whereBetween('return_date', $this->dates($from, $to));

        $wasteKg = round($waste->sum(fn ($row) => (float) $row->quantity_kg), 2);
        $receivedKg = round($batches->sum(fn ($row) => (float) $row->quantity_kg), 2);
        $returned = (int) (clone $packaging)->sum('quantity_returned');
        $reused = (int) (clone $packaging)->sum('quantity_reused');

        return [
            'waste_by_week' => $this->sumByWeek($waste, 'log_date'),
            'received_by_week' => $this->sumByWeek($batches, 'harvested_at'),
            'waste_kg' => $wasteKg,
            'received_kg' => $receivedKg,
            'waste_pct' => self::pct($wasteKg, $receivedKg),
            'packaging_returned' => $returned,
            'packaging_reused' => $reused,
            'packaging_reuse_pct' => self::pct($reused, $returned),
        ];
    }

    /**
     * Điểm thành viên trong kỳ, lọc theo thời điểm ghi sổ. Số đã đổi và số lượt đổi là số ròng sau hoàn điểm.
     *
     * @return array{points_earned: int, points_redeemed: int, redemptions: int}
     */
    public function loyalty(CarbonInterface $from, CarbonInterface $to): array
    {
        $rows = PointTransaction::whereBetween('created_at', $this->moments($from, $to));
        $spent = [PointReason::Redeemed, PointReason::Refunded];

        return [
            'points_earned' => (int) (clone $rows)->whereNotIn('reason', $spent)->sum('points'),
            'points_redeemed' => -(int) (clone $rows)->whereIn('reason', $spent)->sum('points'),
            'redemptions' => (clone $rows)->where('reason', PointReason::Redeemed)->count()
                - (clone $rows)->where('reason', PointReason::Refunded)->count(),
        ];
    }

    /**
     * @param  Collection<int, WasteLog|Batch>  $rows
     * @return array<string, float> ['2026-W38' => kg]
     */
    private function sumByWeek(Collection $rows, string $dateColumn): array
    {
        return $rows
            ->groupBy(fn ($row) => $row->{$dateColumn}->format('o-\WW'))
            ->map(fn ($group) => round($group->sum(fn ($row) => (float) $row->quantity_kg), 2))
            ->sortKeys()
            ->all();
    }

    /**
     * @return Builder<HarvestPlanItem>
     */
    private function planItems(CarbonInterface $from, CarbonInterface $to): Builder
    {
        return HarvestPlanItem::whereNotNull('final_kg')
            ->whereHas('plan', fn ($query) => $query->whereBetween('delivery_date', $this->dates($from, $to)));
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function dates(CarbonInterface $from, CarbonInterface $to): array
    {
        return [$from->toDateString(), $to->toDateString()];
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function moments(CarbonInterface $from, CarbonInterface $to): array
    {
        return [CarbonImmutable::parse($from)->startOfDay(), CarbonImmutable::parse($to)->endOfDay()];
    }

    private static function pct(float|int $part, float|int $whole): ?float
    {
        return $whole > 0 ? round(100 * $part / $whole, 1) : null;
    }
}
