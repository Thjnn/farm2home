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
use Illuminate\Support\Collection;
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
     * @return array<string, array{n: int, mae: ?float, mape: ?float, wape: ?float, bias: ?float}>
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

    /**
     * Nhu cầu dự kiến các ngày giao sắp tới của một loại rau, theo lần chạy gần nhất của phương pháp đang dùng cho kế hoạch.
     * Dùng cho chatbot và trang nông hộ; null = chưa có dự báo cho các ngày tới.
     *
     * @return array{from: CarbonImmutable, to: CarbonImmutable, total: float, average: float, peak: array{date: CarbonImmutable, kg: float, confirmed: float}|null}|null
     */
    public function outlook(Product $product, CarbonInterface $today): ?array
    {
        $method = ForecastMethod::from(config('farm2home.forecast.default_method'));
        $latestRun = Forecast::where('product_id', $product->id)->where('method', $method)
            ->whereDate('generated_on', '<=', $today->toDateString())
            ->max('generated_on');
        if ($latestRun === null) {
            return null;
        }

        $days = Forecast::where('product_id', $product->id)->where('method', $method)
            ->whereDate('generated_on', $latestRun)
            ->whereDate('target_date', '>', $today->toDateString())
            ->orderBy('target_date')
            ->get();
        if ($days->isEmpty()) {
            return null;
        }

        $peak = $days->sortByDesc('predicted_total_kg')->first();
        $average = (float) $days->avg('predicted_total_kg');

        return [
            'from' => CarbonImmutable::parse($days->first()->target_date),
            'to' => CarbonImmutable::parse($days->last()->target_date),
            'total' => round((float) $days->sum('predicted_total_kg'), 2),
            'average' => round($average, 2),
            // Ngày đông nhất chỉ đáng nói khi cao hơn trung bình rõ rệt (≥ 20%), không thì các ngày coi như đều nhau.
            'peak' => (float) $peak->predicted_total_kg < $average * 1.2 ? null : [
                'date' => CarbonImmutable::parse($peak->target_date),
                'kg' => (float) $peak->predicted_total_kg,
                'confirmed' => (float) $peak->confirmed_kg,
            ],
        ];
    }

    /**
     * Các ngày giao máy đoán lệch nhiều nhất (cộng mọi loại rau), kèm số đơn bị hủy để nêu lý do.
     *
     * @return list<array{date: CarbonImmutable, predicted: float, actual: float, cancelled: int}>
     */
    public function worstDays(CarbonInterface $from, CarbonInterface $to, ForecastMethod $method, int $limit = 3, int $leadDays = 1): array
    {
        $days = Forecast::whereNotNull('actual_kg')
            ->whereBetween('target_date', [$from->toDateString(), $to->toDateString()])
            ->whereRaw('DATEDIFF(target_date, generated_on) = ?', [$leadDays])
            ->where('method', $method)
            ->groupBy('target_date')
            ->selectRaw('target_date, SUM(predicted_total_kg) as predicted, SUM(actual_kg) as actual')
            ->get()
            ->sortByDesc(fn (Forecast $day) => abs($day->predicted - $day->actual))
            ->take($limit);

        $cancelled = Order::where('status', OrderStatus::Cancelled)
            ->whereIn('delivery_date', $days->map(fn (Forecast $day) => $day->target_date->toDateString()))
            ->groupBy('delivery_date')
            ->selectRaw('delivery_date, COUNT(*) as n')
            ->pluck('n', 'delivery_date')
            ->mapWithKeys(fn ($n, $date) => [CarbonImmutable::parse($date)->toDateString() => (int) $n]);

        return $days->map(fn (Forecast $day) => [
            'date' => CarbonImmutable::parse($day->target_date),
            'predicted' => round((float) $day->predicted, 2),
            'actual' => round((float) $day->actual, 2),
            'cancelled' => $cancelled[$day->target_date->toDateString()] ?? 0,
        ])->values()->all();
    }

    /**
     * Dữ liệu biểu đồ: nhu cầu thực tế $pastDays ngày tới hết $runDate, rồi dự báo của lần chạy $runDate.
     * $product null = cộng mọi loại rau đang bán.
     *
     * @return array{labels: list<string>, actual: list<?float>, forecast: list<?float>, lower: list<?float>, upper: list<?float>}
     */
    public function chartSeries(CarbonInterface $runDate, ForecastMethod $method, ?Product $product = null, int $pastDays = 28): array
    {
        $runDate = CarbonImmutable::parse($runDate)->startOfDay();
        $from = $runDate->subDays($pastDays - 1);

        $products = $product ? collect([$product]) : Product::where('is_active', true)->get();
        $actual = [];
        foreach ($products as $item) {
            foreach ($this->demandHistory($item, $from, $runDate) as $date => $kg) {
                $actual[$date] = ($actual[$date] ?? 0) + $kg;
            }
        }

        // ponytail: khoảng thấp–cao của "Tất cả" là tổng khoảng từng loại, rộng hơn khoảng thật của tổng.
        $ahead = Forecast::whereDate('generated_on', $runDate->toDateString())
            ->where('method', $method)
            ->whereIn('product_id', $products->pluck('id'))
            ->groupBy('target_date')
            ->orderBy('target_date')
            ->selectRaw('target_date, SUM(predicted_total_kg) as total, SUM(lower_kg) as lower, SUM(upper_kg) as upper')
            ->get();

        $series = ['labels' => [], 'actual' => [], 'forecast' => [], 'lower' => [], 'upper' => []];
        foreach ($actual as $date => $kg) {
            $series['labels'][] = CarbonImmutable::parse($date)->format('d/m');
            $series['actual'][] = round($kg, 2);
            $series['forecast'][] = $series['lower'][] = $series['upper'][] = null;
        }
        foreach ($ahead as $row) {
            $series['labels'][] = $row->target_date->format('d/m');
            $series['actual'][] = null;
            $series['forecast'][] = round((float) $row->total, 2);
            $series['lower'][] = round((float) $row->lower, 2);
            $series['upper'][] = round((float) $row->upper, 2);
        }

        return $series;
    }

    /**
     * Lịch dự báo từ $from đến $to: mỗi ngày giao lấy lần đoán gần nhất trước ngày đó (ngày đã qua là đoán trước 1 ngày, ngày tới là lần chạy mới nhất).
     *
     * @return array<string, array{predicted: float, confirmed: float, actual: ?float, items: Collection<int, Forecast>}>
     */
    public function calendar(CarbonInterface $from, CarbonInterface $to, ForecastMethod $method): array
    {
        return Forecast::with('product')
            ->where('method', $method)
            ->whereHas('product', fn ($query) => $query->where('is_active', true))
            ->whereBetween('target_date', [$from->toDateString(), $to->toDateString()])
            ->orderByDesc('generated_on')
            ->orderBy('product_id')
            ->get()
            ->unique(fn (Forecast $forecast) => $forecast->target_date->toDateString().'#'.$forecast->product_id)
            ->groupBy(fn (Forecast $forecast) => $forecast->target_date->toDateString())
            ->map(fn (Collection $items) => [
                'predicted' => round((float) $items->sum('predicted_total_kg'), 2),
                'confirmed' => round((float) $items->sum('confirmed_kg'), 2),
                'actual' => $items->every(fn (Forecast $forecast) => $forecast->actual_kg !== null)
                    ? round((float) $items->sum('actual_kg'), 2) : null,
                'items' => $items->sortBy('product_id')->values(),
            ])
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
