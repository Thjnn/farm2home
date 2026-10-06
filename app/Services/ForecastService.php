<?php

namespace App\Services;

use App\Enums\ForecastMethod;
use App\Enums\OrderStatus;
use App\Models\Forecast;
use App\Models\Order;
use App\Models\Product;
use App\Support\ForecastCalculator;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class ForecastService
{
    /**
     * Nhu cầu theo ngày giao, liên tục từng ngày; ngày không có đơn tính là 0.
     *
     * @return array<string, float> ['Y-m-d' => kg]
     */
    public function demandHistory(Product $product, CarbonInterface $from, CarbonInterface $to): array
    {
        $rows = $this->demandQuery($product)
            ->whereBetween('orders.delivery_date', [$from->toDateString(), $to->toDateString()])
            ->groupBy('orders.delivery_date')
            ->selectRaw('orders.delivery_date as d, SUM(order_items.weight_kg) as kg')
            ->pluck('kg', 'd');

        $history = [];
        foreach (CarbonPeriod::create($from->toDateString(), $to->toDateString()) as $day) {
            $key = $day->toDateString();
            $history[$key] = round((float) ($rows[$key] ?? 0), 2);
        }

        return $history;
    }

    /**
     * Quyết định D3: mọi đơn chưa hủy của ngày giao đều tính là nhu cầu đã có.
     * $knownAt: chỉ đếm đơn đã tạo tới thời điểm đó (khi chạy lại dự báo cho ngày đã qua).
     */
    public function confirmedKg(Product $product, CarbonInterface $date, ?CarbonInterface $knownAt = null): float
    {
        return round((float) $this->demandQuery($product)
            ->where('orders.delivery_date', $date->toDateString())
            ->when($knownAt, fn (Builder $query) => $query->where('orders.created_at', '<=', $knownAt))
            ->sum('order_items.weight_kg'), 2);
    }

    /**
     * Ghi dự báo của mọi phương pháp cho các ngày giao kế tiếp. Chạy lại cùng ngày thì cập nhật.
     * $knownAt bỏ trống = đếm mọi đơn đang có; có giá trị = chỉ đơn đã tạo tới lúc đó (replay).
     */
    public function run(CarbonInterface $generatedOn, ?int $horizonDays = null, ?CarbonInterface $knownAt = null): int
    {
        $horizonDays ??= (int) config('farm2home.forecast.horizon_days');
        $generatedOn = CarbonImmutable::parse($generatedOn)->startOfDay();

        $firstOrderDate = Order::min('delivery_date');
        if ($firstOrderDate === null) {
            return 0;
        }

        // Các ngày trước khi có đơn đầu tiên không được tính là 0, nếu không trung bình bị kéo thấp.
        $windowStart = $generatedOn->subDays((int) config('farm2home.forecast.history_days') - 1);
        $first = CarbonImmutable::parse($firstOrderDate)->startOfDay();
        $from = $first->gt($windowStart) ? $first : $windowStart;

        $rows = [];

        foreach (Product::where('is_active', true)->orderBy('id')->get() as $product) {
            $historyByDate = $from->gt($generatedOn) ? [] : $this->demandHistory($product, $from, $generatedOn);
            $history = array_values($historyByDate);

            $models = [
                ForecastMethod::MovingAverage->value => ForecastCalculator::movingAverage($history, (int) config('farm2home.forecast.window_days')),
                ForecastMethod::ExpSmoothing->value => ForecastCalculator::expSmoothing($history, (float) config('farm2home.forecast.alpha')),
            ];

            for ($offset = 1; $offset <= $horizonDays; $offset++) {
                $target = $generatedOn->addDays($offset);
                $confirmed = $this->confirmedKg($product, $target, $knownAt);

                $perTarget = $models + [
                    ForecastMethod::WeekdayAverage->value => ForecastCalculator::weekdayAverage($historyByDate, $target),
                ];

                foreach ($perTarget as $method => $model) {
                    // Quyết định D6: dự báo không bao giờ thấp hơn đơn đã đặt.
                    $total = max($model['predicted'], $confirmed);

                    $rows[] = [
                        'product_id' => $product->id,
                        'target_date' => $target->toDateString(),
                        'generated_on' => $generatedOn->toDateString(),
                        'method' => $method,
                        'confirmed_kg' => $confirmed,
                        'predicted_extra_kg' => round($total - $confirmed, 2),
                        'predicted_total_kg' => round($total, 2),
                        'lower_kg' => round(max($model['lower'], $confirmed), 2),
                        'upper_kg' => round(max($model['upper'], $total), 2),
                    ];
                }
            }
        }

        Forecast::upsert(
            $rows,
            ['product_id', 'target_date', 'generated_on', 'method'],
            ['confirmed_kg', 'predicted_extra_kg', 'predicted_total_kg', 'lower_kg', 'upper_kg'],
        );

        return count($rows);
    }

    public function latestFor(Product $product, CarbonInterface $targetDate, ForecastMethod $method): ?Forecast
    {
        return Forecast::where('product_id', $product->id)
            ->whereDate('target_date', $targetDate->toDateString())
            ->where('method', $method)
            ->orderByDesc('generated_on')
            ->first();
    }

    /**
     * Điền actual_kg cho các dự báo có ngày giao không muộn hơn $upTo.
     */
    public function backfillActuals(CarbonInterface $upTo): int
    {
        $pending = Forecast::with('product')
            ->whereNull('actual_kg')
            ->whereDate('target_date', '<=', $upTo->toDateString())
            ->get()
            ->groupBy(fn (Forecast $forecast) => $forecast->product_id.'|'.$forecast->target_date->toDateString());

        $updated = 0;

        foreach ($pending as $group) {
            $first = $group->first();
            $actual = $this->confirmedKg($first->product, $first->target_date);
            $updated += Forecast::whereIn('id', $group->pluck('id'))->update(['actual_kg' => $actual]);
        }

        return $updated;
    }

    /**
     * Sai số của từng phương pháp cho các dự báo được tạo trước ngày giao $leadDays ngày.
     *
     * @return array<string, array{n: int, mae: ?float, mape: ?float, wape: ?float}>
     */
    public function accuracy(CarbonInterface $from, CarbonInterface $to, int $leadDays = 1): array
    {
        return Forecast::whereNotNull('actual_kg')
            ->whereBetween('target_date', [$from->toDateString(), $to->toDateString()])
            ->whereRaw('DATEDIFF(target_date, generated_on) = ?', [$leadDays])
            ->get(['method', 'predicted_total_kg', 'actual_kg'])
            ->groupBy(fn (Forecast $forecast) => $forecast->method->value)
            ->map(fn ($group) => ForecastCalculator::errors(
                $group->map(fn (Forecast $forecast) => [(float) $forecast->predicted_total_kg, (float) $forecast->actual_kg])->values()->all()
            ))
            ->all();
    }

    private function demandQuery(Product $product): Builder
    {
        return DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('order_items.product_id', $product->id)
            ->where('orders.status', '!=', OrderStatus::Cancelled->value);
    }
}
