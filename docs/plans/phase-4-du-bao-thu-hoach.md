# Phase 4 — Dự báo và kế hoạch thu hoạch: Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Từ đơn đã đặt và sản lượng nông hộ báo, hệ thống dự báo nhu cầu 7 ngày tới, gợi ý số kg cần thu hoạch cho từng nông hộ, operator chỉnh và chốt, rồi gửi danh sách cho nông hộ qua Zalo.

**Architecture:** Phần toán (trung bình trượt, làm mượt hàm mũ, sai số, làm tròn 0,5 kg, chia cho nông hộ) là hàm thuần trong `app/Support`, test không cần DB. `ForecastService` và `HarvestPlanService` chỉ lo đọc/ghi DB và gọi các hàm thuần đó. Đây là phần lõi của sản phẩm: AI chỉ gợi ý, operator luôn là người chốt.

**Tech Stack:** Laravel 13 · MySQL 8.4 · PHPUnit 12.

**Spec:** [`docs/CLAUDE.md`](../CLAUDE.md) mục 1, 5 · [`docs/PLAN.md`](../PLAN.md) quyết định D3, D4, D5, D6, D7.

## Global Constraints

Xem `docs/PLAN.md`. Riêng phase này:

- Công thức: `suggested_kg = ceil_to_0.5( max(0, confirmed_order_kg + forecast_extra_kg − available_stock_kg) × (1 + safety_margin_pct/100) )`.
- Luôn lưu cả `suggested_kg` và `final_kg`; `final_kg ≠ suggested_kg` thì bắt buộc có `adjust_reason`.
- Trung bình trượt 7 ngày, làm mượt hàm mũ α = 0,3; không thêm thư viện ML.
- Không có Zalo OA API: chỉ sinh tin nhắn để sao chép và link `https://zalo.me/{phone}`.

---

### Task 4.1: `ForecastCalculator` (hàm thuần)

**Files:**
- Create: `app/Support/ForecastCalculator.php`
- Test: `tests/Unit/Support/ForecastCalculatorTest.php`

**Interfaces:**
- Produces:
  - `ForecastCalculator::movingAverage(array $history, int $window = 7): array{predicted: float, lower: float, upper: float}` — `$history` là list kg/ngày, cũ → mới, đã gồm ngày 0 kg.
  - `ForecastCalculator::expSmoothing(array $history, float $alpha = 0.3): array{predicted: float, lower: float, upper: float}`
  - `ForecastCalculator::errors(array $pairs): array{n: int, mae: ?float, mape: ?float, wape: ?float}` — `$pairs` là list `[predicted, actual]`; `mape` và `wape` tính bằng %.

- [ ] **Step 1: Viết test (sẽ fail)**

`tests/Unit/Support/ForecastCalculatorTest.php`:

```php
<?php

namespace Tests\Unit\Support;

use App\Support\ForecastCalculator;
use PHPUnit\Framework\TestCase;

class ForecastCalculatorTest extends TestCase
{
    public function test_moving_average_with_standard_deviation_band(): void
    {
        $this->assertSame(
            ['predicted' => 8.0, 'lower' => 4.0, 'upper' => 12.0],
            ForecastCalculator::movingAverage([2, 4, 6, 8, 10, 12, 14]),
        );
    }

    public function test_moving_average_only_uses_the_last_window(): void
    {
        $this->assertSame(
            ['predicted' => 8.0, 'lower' => 4.0, 'upper' => 12.0],
            ForecastCalculator::movingAverage([100, 2, 4, 6, 8, 10, 12, 14]),
        );
    }

    public function test_moving_average_with_short_history_and_zero_days(): void
    {
        $this->assertSame(['predicted' => 4.0, 'lower' => 3.0, 'upper' => 5.0], ForecastCalculator::movingAverage([3, 5]));
        $this->assertSame(['predicted' => 1.0, 'lower' => 0.18, 'upper' => 1.82], ForecastCalculator::movingAverage([2, 0, 1]));
        $this->assertSame(['predicted' => 0.0, 'lower' => 0.0, 'upper' => 0.0], ForecastCalculator::movingAverage([]));
    }

    public function test_exponential_smoothing_level_and_rmse_band(): void
    {
        $this->assertSame(['predicted' => 13.0, 'lower' => 3.0, 'upper' => 23.0], ForecastCalculator::expSmoothing([10, 20]));
        $this->assertSame(['predicted' => 12.1, 'lower' => 4.72, 'upper' => 19.48], ForecastCalculator::expSmoothing([10, 20, 10]));
        $this->assertSame(['predicted' => 1.28, 'lower' => 0.0, 'upper' => 2.72], ForecastCalculator::expSmoothing([2, 0, 1]));
    }

    public function test_exponential_smoothing_edge_cases(): void
    {
        $this->assertSame(['predicted' => 7.0, 'lower' => 7.0, 'upper' => 7.0], ForecastCalculator::expSmoothing([7]));
        $this->assertSame(['predicted' => 0.0, 'lower' => 0.0, 'upper' => 0.0], ForecastCalculator::expSmoothing([]));
    }

    public function test_error_metrics(): void
    {
        // |2-4|=2, |3-2|=1, |1-0|=1 → MAE 4/3; MAPE bỏ qua actual = 0: (0.5 + 0.5)/2; WAPE 4/6.
        $this->assertSame(
            ['n' => 3, 'mae' => 1.33, 'mape' => 50.0, 'wape' => 66.7],
            ForecastCalculator::errors([[2.0, 4.0], [3.0, 2.0], [1.0, 0.0]]),
        );
    }

    public function test_error_metrics_with_no_data_or_zero_actuals(): void
    {
        $this->assertSame(['n' => 0, 'mae' => null, 'mape' => null, 'wape' => null], ForecastCalculator::errors([]));
        $this->assertSame(['n' => 1, 'mae' => 1.0, 'mape' => null, 'wape' => null], ForecastCalculator::errors([[1.0, 0.0]]));
    }
}
```

- [ ] **Step 2: Chạy test, xác nhận fail**

Run: `php artisan test --filter=ForecastCalculatorTest`
Expected: FAIL (class not found).

- [ ] **Step 3: Viết `app/Support/ForecastCalculator.php`**

```php
<?php

namespace App\Support;

final class ForecastCalculator
{
    /**
     * @param  list<float|int>  $history  kg mỗi ngày, cũ → mới, ngày không có đơn là 0
     * @return array{predicted: float, lower: float, upper: float}
     */
    public static function movingAverage(array $history, int $window = 7): array
    {
        $slice = array_slice(array_values($history), -$window);

        if ($slice === []) {
            return ['predicted' => 0.0, 'lower' => 0.0, 'upper' => 0.0];
        }

        $mean = array_sum($slice) / count($slice);
        $sd = sqrt(array_sum(array_map(fn ($x) => ($x - $mean) ** 2, $slice)) / count($slice));

        return self::band($mean, $sd);
    }

    /**
     * @param  list<float|int>  $history
     * @return array{predicted: float, lower: float, upper: float}
     */
    public static function expSmoothing(array $history, float $alpha = 0.3): array
    {
        $values = array_values($history);

        if ($values === []) {
            return ['predicted' => 0.0, 'lower' => 0.0, 'upper' => 0.0];
        }

        $level = (float) $values[0];
        $squaredErrors = [];

        foreach (array_slice($values, 1) as $x) {
            $squaredErrors[] = ($x - $level) ** 2;
            $level = $alpha * $x + (1 - $alpha) * $level;
        }

        // Dải ± = RMSE của sai số dự báo một bước.
        $sd = $squaredErrors === [] ? 0.0 : sqrt(array_sum($squaredErrors) / count($squaredErrors));

        return self::band($level, $sd);
    }

    /**
     * @param  list<array{0: float, 1: float}>  $pairs  [predicted, actual]
     * @return array{n: int, mae: ?float, mape: ?float, wape: ?float}
     */
    public static function errors(array $pairs): array
    {
        $n = count($pairs);

        if ($n === 0) {
            return ['n' => 0, 'mae' => null, 'mape' => null, 'wape' => null];
        }

        $absolute = array_map(fn ($pair) => abs($pair[0] - $pair[1]), $pairs);
        $sumActual = array_sum(array_map(fn ($pair) => $pair[1], $pairs));

        $percentages = [];
        foreach ($pairs as $i => $pair) {
            if ($pair[1] > 0) {
                $percentages[] = $absolute[$i] / $pair[1];
            }
        }

        return [
            'n' => $n,
            'mae' => round(array_sum($absolute) / $n, 2),
            'mape' => $percentages === [] ? null : round(100 * array_sum($percentages) / count($percentages), 1),
            'wape' => $sumActual > 0 ? round(100 * array_sum($absolute) / $sumActual, 1) : null,
        ];
    }

    /** @return array{predicted: float, lower: float, upper: float} */
    private static function band(float $mean, float $sd): array
    {
        return [
            'predicted' => round($mean, 2),
            'lower' => round(max(0.0, $mean - $sd), 2),
            'upper' => round($mean + $sd, 2),
        ];
    }
}
```

- [ ] **Step 4: Chạy test, xác nhận pass, commit**

Run: `php artisan test --filter=ForecastCalculatorTest`
Expected: PASS (7 tests).

```bash
vendor/bin/pint --dirty
git add -A
git commit -m "feat: pure forecast calculators (moving average, exponential smoothing, error metrics)"
```

---

### Task 4.2: `ForecastService` và lệnh artisan

**Files:**
- Create: `app/Services/ForecastService.php`, `app/Console/Commands/ForecastRun.php`, `app/Console/Commands/ForecastBackfill.php`
- Test: `tests/Feature/Forecast/ForecastServiceTest.php`, `tests/Feature/Forecast/ForecastAccuracyOnSampleDataTest.php`

**Interfaces:**
- Consumes: `ForecastCalculator`, `OrderService::place` (để dựng dữ liệu test), `config('farm2home.forecast.*')`.
- Produces:
  - `ForecastService::demandHistory(Product $product, CarbonInterface $from, CarbonInterface $to): array` — `['Y-m-d' => float kg]`, liên tục từng ngày, ngày không có đơn = `0.0`.
  - `ForecastService::confirmedKg(Product $product, CarbonInterface $date): float` — tổng kg đơn chưa hủy của ngày giao đó (quyết định D3).
  - `ForecastService::run(CarbonInterface $generatedOn, ?int $horizonDays = null): int` — ghi (upsert) `forecasts` cho 2 phương pháp × mọi sản phẩm đang bán × `horizonDays` ngày kế tiếp; trả số dòng.
  - `ForecastService::latestFor(Product $product, CarbonInterface $targetDate, ForecastMethod $method): ?Forecast`
  - `ForecastService::backfillActuals(CarbonInterface $upTo): int` — điền `actual_kg` cho các dòng có `target_date <= $upTo`; trả số dòng cập nhật.
  - `ForecastService::accuracy(CarbonInterface $from, CarbonInterface $to, int $leadDays = 1): array` — `[method_value => ['n','mae','mape','wape']]`.
  - Lệnh `php artisan forecast:run {date?}` và `php artisan forecast:backfill {date?}`.

- [ ] **Step 1: Viết test (sẽ fail)**

`tests/Feature/Forecast/ForecastServiceTest.php`:

```php
<?php

namespace Tests\Feature\Forecast;

use App\Enums\ForecastMethod;
use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Models\Forecast;
use App\Models\Order;
use App\Services\ForecastService;
use App\Services\OrderService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsCatalog;
use Tests\TestCase;

class ForecastServiceTest extends TestCase
{
    use BuildsCatalog, RefreshDatabase;

    private ForecastService $forecasts;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-01 18:30:00');
        $this->buildCatalog();
        $this->forecasts = app(ForecastService::class);

        // Rau muống: 29/09 = 2 kg, 30/09 = không có đơn, 01/10 = 1 kg, 02/10 = 3 kg (đơn tương lai).
        $this->order('2026-09-29', 4);
        $this->order('2026-10-01', 2);
        $this->order('2026-10-02', 6);
    }

    private function order(string $date, int $rmPacks): Order
    {
        return app(OrderService::class)->place([
            'name' => 'Khách', 'phone' => '0988000001', 'address' => '1 Lê Lợi',
            'delivery_zone_id' => $this->zone->id, 'delivery_date' => $date, 'time_slot' => '17:00-19:00',
            'order_type' => 'single', 'items' => [['product_id' => $this->rm->id, 'quantity' => $rmPacks]],
            'payment_method' => 'cod', 'data_consent' => true,
        ], OrderChannel::Zalo);
    }

    private function d(string $date): CarbonImmutable
    {
        return CarbonImmutable::parse($date);
    }

    public function test_days_without_orders_count_as_zero(): void
    {
        $this->assertSame(
            ['2026-09-29' => 2.0, '2026-09-30' => 0.0, '2026-10-01' => 1.0],
            $this->forecasts->demandHistory($this->rm, $this->d('2026-09-29'), $this->d('2026-10-01')),
        );
    }

    public function test_cancelled_orders_are_excluded_from_demand(): void
    {
        $extra = $this->order('2026-10-01', 10);
        app(OrderService::class)->transition($extra, OrderStatus::Cancelled, 'Khách hủy');

        $this->assertSame(1.0, $this->forecasts->demandHistory($this->rm, $this->d('2026-10-01'), $this->d('2026-10-01'))['2026-10-01']);
        $this->assertSame(3.0, $this->forecasts->confirmedKg($this->rm, $this->d('2026-10-02')));
    }

    public function test_run_writes_both_methods_for_every_product_and_day(): void
    {
        $count = $this->forecasts->run($this->d('2026-10-01'));

        // 3 sản phẩm × 7 ngày × 2 phương pháp
        $this->assertSame(42, $count);
        $this->assertSame(42, Forecast::count());
        $this->assertSame(['2026-10-02', '2026-10-08'], [Forecast::min('target_date'), Forecast::max('target_date')]);
    }

    public function test_forecast_for_a_day_without_orders_is_the_model_prediction(): void
    {
        $this->forecasts->run($this->d('2026-10-01'));

        $ma = $this->forecasts->latestFor($this->rm, $this->d('2026-10-03'), ForecastMethod::MovingAverage);
        $this->assertSame(['0.00', '1.00', '1.00', '0.18', '1.82'], [$ma->confirmed_kg, $ma->predicted_extra_kg, $ma->predicted_total_kg, $ma->lower_kg, $ma->upper_kg]);

        $es = $this->forecasts->latestFor($this->rm, $this->d('2026-10-03'), ForecastMethod::ExpSmoothing);
        $this->assertSame(['1.28', '1.28', '0.00', '2.72'], [$es->predicted_extra_kg, $es->predicted_total_kg, $es->lower_kg, $es->upper_kg]);
    }

    public function test_forecast_never_goes_below_orders_already_placed(): void
    {
        $this->forecasts->run($this->d('2026-10-01'));

        $ma = $this->forecasts->latestFor($this->rm, $this->d('2026-10-02'), ForecastMethod::MovingAverage);

        $this->assertSame(['3.00', '0.00', '3.00', '3.00', '3.00'], [$ma->confirmed_kg, $ma->predicted_extra_kg, $ma->predicted_total_kg, $ma->lower_kg, $ma->upper_kg]);
    }

    public function test_product_without_history_forecasts_zero(): void
    {
        $this->forecasts->run($this->d('2026-10-01'));

        $this->assertSame('0.00', $this->forecasts->latestFor($this->cn, $this->d('2026-10-03'), ForecastMethod::MovingAverage)->predicted_total_kg);
    }

    public function test_running_twice_on_the_same_day_updates_instead_of_duplicating(): void
    {
        $this->forecasts->run($this->d('2026-10-01'));
        $this->order('2026-10-03', 4);
        $this->forecasts->run($this->d('2026-10-01'));

        $this->assertSame(42, Forecast::count());
        $this->assertSame('2.00', $this->forecasts->latestFor($this->rm, $this->d('2026-10-03'), ForecastMethod::MovingAverage)->confirmed_kg);
    }

    public function test_latest_for_prefers_the_most_recent_run(): void
    {
        $this->forecasts->run($this->d('2026-09-30'));
        $this->forecasts->run($this->d('2026-10-01'));

        $forecast = $this->forecasts->latestFor($this->rm, $this->d('2026-10-03'), ForecastMethod::MovingAverage);

        $this->assertSame('2026-10-01', $forecast->generated_on->toDateString());
    }

    public function test_backfill_fills_actuals_only_up_to_the_given_date(): void
    {
        $this->forecasts->run($this->d('2026-10-01'));

        $updated = $this->forecasts->backfillActuals($this->d('2026-10-02'));

        // 3 sản phẩm × 2 phương pháp cho ngày 02/10
        $this->assertSame(6, $updated);
        $this->assertSame('3.00', $this->forecasts->latestFor($this->rm, $this->d('2026-10-02'), ForecastMethod::MovingAverage)->actual_kg);
        $this->assertSame('0.00', $this->forecasts->latestFor($this->cn, $this->d('2026-10-02'), ForecastMethod::MovingAverage)->actual_kg);
        $this->assertNull($this->forecasts->latestFor($this->rm, $this->d('2026-10-03'), ForecastMethod::MovingAverage)->actual_kg);
    }

    public function test_run_without_any_order_writes_nothing(): void
    {
        Order::query()->delete();

        $this->assertSame(0, $this->forecasts->run($this->d('2026-10-01')));
    }

    public function test_artisan_commands(): void
    {
        $this->artisan('forecast:run', ['date' => '2026-10-01'])->assertSuccessful();
        $this->assertSame(42, Forecast::count());

        $this->artisan('forecast:backfill', ['date' => '2026-10-02'])->assertSuccessful();
        $this->assertSame(6, Forecast::whereNotNull('actual_kg')->count());
    }
}
```

`tests/Feature/Forecast/ForecastAccuracyOnSampleDataTest.php`:

```php
<?php

namespace Tests\Feature\Forecast;

use App\Services\ForecastService;
use Carbon\CarbonImmutable;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ForecastAccuracyOnSampleDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_accuracy_matches_values_computed_from_the_sample_file(): void
    {
        $this->seed(DemoSeeder::class);

        $accuracy = app(ForecastService::class)->accuracy(CarbonImmutable::parse('2026-09-17'), CarbonImmutable::parse('2026-10-01'));

        $this->assertSame(['n' => 105, 'mae' => 0.65, 'mape' => 51.4, 'wape' => 39.1], $accuracy['moving_average']);
        $this->assertSame(['n' => 105, 'mae' => 0.7, 'mape' => 56.6, 'wape' => 42.1], $accuracy['exp_smoothing']);
    }

    public function test_demand_on_sample_data(): void
    {
        $this->seed(DemoSeeder::class);
        $service = app(ForecastService::class);
        $rauMuong = \App\Models\Product::where('code', 'RM')->firstOrFail();

        $this->assertSame(7.5, $service->confirmedKg($rauMuong, CarbonImmutable::parse('2026-10-03')));
        $this->assertSame(
            ['2026-09-17' => 2.0, '2026-09-18' => 0.5],
            $service->demandHistory($rauMuong, CarbonImmutable::parse('2026-09-17'), CarbonImmutable::parse('2026-09-18')),
        );
    }
}
```

- [ ] **Step 2: Chạy test, xác nhận fail**

Run: `php artisan test --filter="ForecastServiceTest|ForecastAccuracyOnSampleDataTest"`
Expected: FAIL (`Class "App\Services\ForecastService" not found`).

- [ ] **Step 3: Viết `app/Services/ForecastService.php`**

```php
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
    /** @return array<string, float> ['Y-m-d' => kg], từng ngày liên tục, ngày không có đơn = 0 */
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

    // Quyết định D3: mọi đơn chưa hủy của ngày giao đều tính là nhu cầu đã có.
    public function confirmedKg(Product $product, CarbonInterface $date): float
    {
        return round((float) $this->demandQuery($product)
            ->where('orders.delivery_date', $date->toDateString())
            ->sum('order_items.weight_kg'), 2);
    }

    public function run(CarbonInterface $generatedOn, ?int $horizonDays = null): int
    {
        $horizonDays ??= (int) config('farm2home.forecast.horizon_days');
        $generatedOn = CarbonImmutable::parse($generatedOn)->startOfDay();

        $firstOrderDate = Order::min('delivery_date');
        if ($firstOrderDate === null) {
            return 0;
        }

        // Không tính các ngày trước khi có đơn đầu tiên là 0, nếu không trung bình bị kéo thấp.
        $windowStart = $generatedOn->subDays((int) config('farm2home.forecast.history_days') - 1);
        $first = CarbonImmutable::parse($firstOrderDate)->startOfDay();
        $from = $first->gt($windowStart) ? $first : $windowStart;

        $rows = [];

        foreach (Product::where('is_active', true)->orderBy('id')->get() as $product) {
            $history = $from->gt($generatedOn) ? [] : array_values($this->demandHistory($product, $from, $generatedOn));

            $models = [
                ForecastMethod::MovingAverage->value => ForecastCalculator::movingAverage($history, (int) config('farm2home.forecast.window_days')),
                ForecastMethod::ExpSmoothing->value => ForecastCalculator::expSmoothing($history, (float) config('farm2home.forecast.alpha')),
            ];

            for ($offset = 1; $offset <= $horizonDays; $offset++) {
                $target = $generatedOn->addDays($offset);
                $confirmed = $this->confirmedKg($product, $target);

                foreach ($models as $method => $model) {
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

    /** @return array<string, array{n: int, mae: ?float, mape: ?float, wape: ?float}> */
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
```

Trong `app/Models/Forecast.php`, đảm bảo `casts()` có: `'target_date' => 'date'`, `'generated_on' => 'date'`, `'method' => ForecastMethod::class`, và `'decimal:2'` cho `confirmed_kg`, `predicted_extra_kg`, `predicted_total_kg`, `lower_kg`, `upper_kg`, `actual_kg`.

- [ ] **Step 4: Viết hai lệnh artisan**

`app/Console/Commands/ForecastRun.php`:

```php
<?php

namespace App\Console\Commands;

use App\Services\ForecastService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class ForecastRun extends Command
{
    protected $signature = 'forecast:run {date? : Ngày chạy dự báo (Y-m-d), mặc định hôm nay}';

    protected $description = 'Chạy dự báo nhu cầu cho các ngày giao sắp tới';

    public function handle(ForecastService $forecasts): int
    {
        $date = CarbonImmutable::parse($this->argument('date') ?? today());
        $count = $forecasts->run($date);

        $this->info("Đã ghi {$count} dòng dự báo (ngày chạy {$date->toDateString()}).");

        return self::SUCCESS;
    }
}
```

`app/Console/Commands/ForecastBackfill.php`:

```php
<?php

namespace App\Console\Commands;

use App\Services\ForecastService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class ForecastBackfill extends Command
{
    protected $signature = 'forecast:backfill {date? : Điền số thực tế đến hết ngày này (Y-m-d), mặc định hôm nay}';

    protected $description = 'Điền actual_kg cho các dự báo đã qua ngày giao';

    public function handle(ForecastService $forecasts): int
    {
        $date = CarbonImmutable::parse($this->argument('date') ?? today());
        $count = $forecasts->backfillActuals($date);

        $this->info("Đã cập nhật số thực tế cho {$count} dòng dự báo.");

        return self::SUCCESS;
    }
}
```

- [ ] **Step 5: Chạy test, xác nhận pass, commit**

Run: `php artisan test --filter="ForecastServiceTest|ForecastAccuracyOnSampleDataTest"`
Expected: PASS (13 tests).

```bash
vendor/bin/pint --dirty
git add -A
git commit -m "feat: forecast service with demand history, runs, actual backfill and accuracy metrics"
```

---

### Task 4.3: `HarvestMath` (hàm thuần)

**Files:**
- Create: `app/Support/HarvestMath.php`
- Test: `tests/Unit/Support/HarvestMathTest.php`

**Interfaces:**
- Produces:
  - `HarvestMath::ceilToHalf(float $kg): float`, `HarvestMath::floorToHalf(float $kg): float`
  - `HarvestMath::suggestedKg(float $confirmedKg, float $forecastExtraKg, float $stockKg, float $marginPct): float`
  - `HarvestMath::allocate(float $neededKg, array $supplyByFarmer): array` — `$supplyByFarmer` là `[farmer_id => kg báo]`; trả list `['farmer_id' => ?int, 'kg' => float]`, nông hộ báo nhiều nhất trước; phần thiếu là dòng `farmer_id = null`.

- [ ] **Step 1: Viết test (sẽ fail)**

`tests/Unit/Support/HarvestMathTest.php`:

```php
<?php

namespace Tests\Unit\Support;

use App\Support\HarvestMath;
use PHPUnit\Framework\TestCase;

class HarvestMathTest extends TestCase
{
    public function test_ceil_to_half_kilogram(): void
    {
        $this->assertSame(2.0, HarvestMath::ceilToHalf(1.804));
        $this->assertSame(2.0, HarvestMath::ceilToHalf(2.0));
        $this->assertSame(2.5, HarvestMath::ceilToHalf(2.01));
        $this->assertSame(0.0, HarvestMath::ceilToHalf(0));
        $this->assertSame(0.0, HarvestMath::ceilToHalf(-1));
    }

    public function test_ceil_is_not_fooled_by_float_noise(): void
    {
        // 5 × 1.1 = 5.500000000000001 trong số thực; không được nhảy lên 6.0.
        $this->assertSame(5.5, HarvestMath::ceilToHalf(5 * 1.1));
    }

    public function test_floor_to_half_kilogram(): void
    {
        $this->assertSame(6.0, HarvestMath::floorToHalf(6.3));
        $this->assertSame(8.0, HarvestMath::floorToHalf(8.4));
        $this->assertSame(0.0, HarvestMath::floorToHalf(0.4));
    }

    public function test_suggested_kg_matches_rows_in_the_sample_data(): void
    {
        // harvest_plan_items id 1: confirmed 1.61, extra 0.03, margin 10% → 2.00
        $this->assertSame(2.0, HarvestMath::suggestedKg(1.61, 0.03, 0, 10));
        // harvest_plan_items id 119: confirmed 2.00 → 2.50
        $this->assertSame(2.5, HarvestMath::suggestedKg(2.0, 0, 0, 10));
        // harvest_plan_items id 113: confirmed 7.50 → 8.50
        $this->assertSame(8.5, HarvestMath::suggestedKg(7.5, 0, 0, 10));
    }

    public function test_stock_reduces_the_suggestion_and_never_goes_negative(): void
    {
        $this->assertSame(6.5, HarvestMath::suggestedKg(7.5, 0, 2.0, 10));
        $this->assertSame(0.0, HarvestMath::suggestedKg(2.0, 1.0, 5.0, 10));
    }

    public function test_one_farmer_covers_the_need(): void
    {
        $this->assertSame([['farmer_id' => 1, 'kg' => 3.0]], HarvestMath::allocate(3.0, [1 => 6.3, 3 => 4.0]));
    }

    public function test_largest_supplier_first_then_split(): void
    {
        $this->assertSame(
            [['farmer_id' => 3, 'kg' => 6.0], ['farmer_id' => 1, 'kg' => 2.0]],
            HarvestMath::allocate(8.0, [1 => 4.2, 3 => 6.3]),
        );
    }

    public function test_shortage_becomes_an_unassigned_row(): void
    {
        $this->assertSame(
            [['farmer_id' => 1, 'kg' => 2.0], ['farmer_id' => null, 'kg' => 3.0]],
            HarvestMath::allocate(5.0, [1 => 2.4]),
        );
        $this->assertSame([['farmer_id' => null, 'kg' => 2.5]], HarvestMath::allocate(2.5, []));
    }

    public function test_nothing_needed_means_no_rows(): void
    {
        $this->assertSame([], HarvestMath::allocate(0, [1 => 5]));
    }
}
```

- [ ] **Step 2: Chạy test, xác nhận fail**

Run: `php artisan test --filter=HarvestMathTest`
Expected: FAIL (class not found).

- [ ] **Step 3: Viết `app/Support/HarvestMath.php`**

```php
<?php

namespace App\Support;

final class HarvestMath
{
    public static function ceilToHalf(float $kg): float
    {
        return ceil(round(max(0.0, $kg) * 2, 6)) / 2;
    }

    public static function floorToHalf(float $kg): float
    {
        return floor(round(max(0.0, $kg) * 2, 6)) / 2;
    }

    public static function suggestedKg(float $confirmedKg, float $forecastExtraKg, float $stockKg, float $marginPct): float
    {
        return self::ceilToHalf(max(0.0, $confirmedKg + $forecastExtraKg - $stockKg) * (1 + $marginPct / 100));
    }

    /**
     * Quyết định D5: nông hộ báo nhiều nhất trước, mỗi phần làm tròn xuống 0,5 kg.
     *
     * @param  array<int, float>  $supplyByFarmer  farmer_id => kg đã báo
     * @return list<array{farmer_id: ?int, kg: float}>
     */
    public static function allocate(float $neededKg, array $supplyByFarmer): array
    {
        if ($neededKg <= 0) {
            return [];
        }

        arsort($supplyByFarmer);

        $rows = [];
        $remaining = $neededKg;

        foreach ($supplyByFarmer as $farmerId => $availableKg) {
            if ($remaining <= 0) {
                break;
            }

            $take = min($remaining, self::floorToHalf((float) $availableKg));

            if ($take <= 0) {
                continue;
            }

            $rows[] = ['farmer_id' => $farmerId, 'kg' => $take];
            $remaining = round($remaining - $take, 2);
        }

        if ($remaining > 0) {
            $rows[] = ['farmer_id' => null, 'kg' => $remaining];
        }

        return $rows;
    }
}
```

- [ ] **Step 4: Chạy test, xác nhận pass, commit**

Run: `php artisan test --filter=HarvestMathTest`
Expected: PASS (9 tests).

```bash
vendor/bin/pint --dirty
git add -A
git commit -m "feat: pure harvest math (half-kg rounding, suggestion formula, farmer allocation)"
```

---

### Task 4.4: `HarvestPlanService` (và phần tồn kho của `BatchService`)

**Files:**
- Create: `app/Services/BatchService.php` (hai hàm tồn kho; Phase 5 bổ sung phần còn lại), `app/Services/HarvestPlanService.php`
- Test: `tests/Feature/Harvest/HarvestPlanServiceTest.php`, `tests/Feature/Harvest/HarvestPlanOnSampleDataTest.php`

**Interfaces:**
- Consumes: `ForecastService::confirmedKg`, `ForecastService::latestFor`, `HarvestMath`, `Format::kg`, route `farmer.show`.
- Produces:
  - `BatchService::remainingKg(Batch $batch): float` — `quantity_kg − kg đã gán cho đơn − kg trong waste_logs`, không âm.
  - `BatchService::availableStockKg(Product $product, CarbonInterface $forDate): float` (quyết định D4).
  - `HarvestPlanService::generateDraft(CarbonInterface $deliveryDate, ?User $by = null): HarvestPlan`
  - `HarvestPlanService::updateItem(HarvestPlanItem $item, float $finalKg, ?string $reason = null, ?int $farmerId = null): HarvestPlanItem`
  - `HarvestPlanService::confirm(HarvestPlan $plan, User $by): HarvestPlan`
  - `HarvestPlanService::markSent(HarvestPlan $plan): HarvestPlan`
  - `HarvestPlanService::farmerConfirm(HarvestPlan $plan, Farmer $farmer): int`
  - `HarvestPlanService::zaloMessage(HarvestPlan $plan, Farmer $farmer): string`
  - `HarvestPlanService::complete(HarvestPlan $plan): HarvestPlan`
  - Mọi vi phạm quy tắc ném `DomainException` với thông báo tiếng Việt.

- [ ] **Step 1: Viết test (sẽ fail)**

`tests/Feature/Harvest/HarvestPlanServiceTest.php`:

```php
<?php

namespace Tests\Feature\Harvest;

use App\Enums\ForecastMethod;
use App\Enums\HarvestPlanStatus;
use App\Enums\OrderChannel;
use App\Models\Batch;
use App\Models\Forecast;
use App\Models\HarvestPlan;
use App\Models\Product;
use App\Models\SupplyReport;
use App\Services\HarvestPlanService;
use App\Services\OrderService;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsCatalog;
use Tests\TestCase;

class HarvestPlanServiceTest extends TestCase
{
    use BuildsCatalog, RefreshDatabase;

    private HarvestPlanService $plans;

    private CarbonImmutable $date;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-02 19:00:00');
        $this->buildCatalog();
        $this->plans = app(HarvestPlanService::class);
        $this->date = CarbonImmutable::parse('2026-10-03');

        // Nhu cầu 03/10: rau muống 15 gói = 7,5 kg; cải ngọt 4 gói = 2,0 kg.
        $this->order([['product_id' => $this->rm->id, 'quantity' => 15], ['product_id' => $this->cn->id, 'quantity' => 4]]);

        // Nguồn cung 03/10.
        $this->supply($this->nh01->id, $this->rm, 8.4);
        $this->supply($this->nh02->id, $this->rm, 7.5);
        $this->supply($this->nh02->id, $this->cn, 7.5);
    }

    private function order(array $items): void
    {
        app(OrderService::class)->place([
            'name' => 'Khách', 'phone' => '0988000001', 'address' => '1 Lê Lợi',
            'delivery_zone_id' => $this->zone->id, 'delivery_date' => '2026-10-03', 'time_slot' => '17:00-19:00',
            'order_type' => 'single', 'items' => $items, 'payment_method' => 'cod', 'data_consent' => true,
        ], OrderChannel::Zalo);
    }

    private function supply(int $farmerId, Product $product, float $kg): void
    {
        SupplyReport::create(['farmer_id' => $farmerId, 'product_id' => $product->id, 'available_date' => '2026-10-03', 'quantity_kg' => $kg, 'source' => 'form']);
    }

    private function rows(HarvestPlan $plan, Product $product): array
    {
        return $plan->items->where('product_id', $product->id)->values()
            ->map(fn ($item) => [$item->farmer_id, (float) $item->suggested_kg, (float) $item->confirmed_order_kg])->all();
    }

    public function test_draft_applies_the_formula_and_allocates_to_farmers(): void
    {
        $plan = $this->plans->generateDraft($this->date, $this->operator);

        $this->assertSame(HarvestPlanStatus::Draft, $plan->status);
        $this->assertSame('2026-10-03', $plan->delivery_date->toDateString());
        $this->assertSame('10.00', $plan->safety_margin_pct);
        $this->assertSame($this->operator->id, $plan->created_by);

        // Rau muống: 7,5 × 1,1 = 8,25 → 8,5. NH01 báo 8,4 → lấy 8,0; còn 0,5 cho NH02.
        $this->assertSame([[$this->nh01->id, 8.0, 7.5], [$this->nh02->id, 0.5, 0.0]], $this->rows($plan, $this->rm));
        // Cải ngọt: 2,0 × 1,1 = 2,2 → 2,5.
        $this->assertSame([[$this->nh02->id, 2.5, 2.0]], $this->rows($plan, $this->cn));
        // Hành lá không có nhu cầu → không có dòng.
        $this->assertSame([], $this->rows($plan, $this->hl));
        $this->assertNull($plan->items->first()->final_kg);
    }

    public function test_forecast_extra_is_added_on_top_of_placed_orders(): void
    {
        Forecast::create([
            'product_id' => $this->rm->id, 'target_date' => '2026-10-03', 'generated_on' => '2026-10-02', 'method' => ForecastMethod::MovingAverage,
            'confirmed_kg' => 7.5, 'predicted_extra_kg' => 1.5, 'predicted_total_kg' => 9.0, 'lower_kg' => 7.5, 'upper_kg' => 10,
        ]);

        $plan = $this->plans->generateDraft($this->date);

        // (7,5 + 1,5) × 1,1 = 9,9 → 10,0
        $this->assertSame(10.0, (float) $plan->items->where('product_id', $this->rm->id)->sum('suggested_kg'));
        $this->assertSame('1.50', $plan->items->firstWhere('product_id', $this->rm->id)->forecast_extra_kg);
    }

    public function test_leftover_stock_from_a_previous_batch_reduces_the_suggestion(): void
    {
        Batch::create([
            'code' => 'F2H-261002-RM-NH01', 'farmer_id' => $this->nh01->id, 'product_id' => $this->rm->id, 'quantity_kg' => 2.0,
            'harvested_at' => '2026-10-02 05:30:00', 'best_before' => '2026-10-04', 'quality_status' => 'passed',
        ]);

        $plan = $this->plans->generateDraft($this->date);

        // (7,5 − 2,0) × 1,1 = 6,05 → 6,5
        $this->assertSame(6.5, (float) $plan->items->where('product_id', $this->rm->id)->sum('suggested_kg'));
        $this->assertSame('2.00', $plan->items->firstWhere('product_id', $this->rm->id)->available_stock_kg);
    }

    public function test_expired_or_rejected_batches_are_not_stock(): void
    {
        Batch::create(['code' => 'F2H-261001-RM-NH01', 'farmer_id' => $this->nh01->id, 'product_id' => $this->rm->id, 'quantity_kg' => 2.0, 'harvested_at' => '2026-10-01 05:30:00', 'best_before' => '2026-10-02', 'quality_status' => 'passed']);
        Batch::create(['code' => 'F2H-261002-RM-NH02', 'farmer_id' => $this->nh02->id, 'product_id' => $this->rm->id, 'quantity_kg' => 2.0, 'harvested_at' => '2026-10-02 05:30:00', 'best_before' => '2026-10-04', 'quality_status' => 'rejected']);

        $plan = $this->plans->generateDraft($this->date);

        $this->assertSame(8.5, (float) $plan->items->where('product_id', $this->rm->id)->sum('suggested_kg'));
    }

    public function test_regenerating_a_draft_replaces_its_rows(): void
    {
        $first = $this->plans->generateDraft($this->date);
        $this->order([['product_id' => $this->hl->id, 'quantity' => 5]]);
        $second = $this->plans->generateDraft($this->date);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, HarvestPlan::count());
        $this->assertCount(4, $second->items);
    }

    public function test_confirm_is_blocked_while_a_row_has_no_farmer(): void
    {
        // Hành lá: 5 gói = 1,0 kg → 1,1 → 1,5 kg, không ai báo sản lượng.
        $this->order([['product_id' => $this->hl->id, 'quantity' => 5]]);
        $plan = $this->plans->generateDraft($this->date);
        $orphan = $plan->items->firstWhere('product_id', $this->hl->id);

        $this->assertNull($orphan->farmer_id);
        $this->assertSame('1.50', $orphan->suggested_kg);

        try {
            $this->plans->confirm($plan, $this->operator);
            $this->fail('Expected DomainException');
        } catch (DomainException $e) {
            $this->assertSame('Còn dòng chưa có nông hộ. Hãy chọn nông hộ hoặc đặt số chốt về 0.', $e->getMessage());
        }

        $this->plans->updateItem($orphan, 1.5, null, $this->nh01->id);
        $this->assertSame(HarvestPlanStatus::Confirmed, $this->plans->confirm($plan->refresh(), $this->operator)->status);
    }

    public function test_changing_the_suggestion_requires_a_reason(): void
    {
        $item = $this->plans->generateDraft($this->date)->items->firstWhere('product_id', $this->cn->id);

        try {
            $this->plans->updateItem($item, 3.0);
            $this->fail('Expected DomainException');
        } catch (DomainException $e) {
            $this->assertSame('Cần ghi lý do khi số chốt khác số gợi ý.', $e->getMessage());
        }

        $this->plans->updateItem($item, 3.0, 'Cuối tuần, tăng dự phòng');
        $this->assertSame(['3.00', '2.50', 'Cuối tuần, tăng dự phòng'], [$item->final_kg, $item->suggested_kg, $item->adjust_reason]);

        // Trả về đúng số gợi ý thì không cần lý do và lý do cũ bị xóa.
        $this->plans->updateItem($item, 2.5);
        $this->assertNull($item->refresh()->adjust_reason);
    }

    public function test_negative_final_kg_is_rejected(): void
    {
        $item = $this->plans->generateDraft($this->date)->items->first();

        $this->expectException(DomainException::class);

        $this->plans->updateItem($item, -1, 'lỗi nhập');
    }

    public function test_confirm_keeps_suggested_and_fills_final_for_untouched_rows(): void
    {
        $plan = $this->plans->generateDraft($this->date);
        $this->plans->updateItem($plan->items->firstWhere('product_id', $this->cn->id), 2.0, 'Dự báo mưa, giảm lượng thu hoạch');

        $plan = $this->plans->confirm($plan, $this->operator);

        $this->assertSame(HarvestPlanStatus::Confirmed, $plan->status);
        $this->assertSame($this->operator->id, $plan->confirmed_by);
        $this->assertNotNull($plan->confirmed_at);
        $this->assertSame(['8.00', '0.50', '2.00'], $plan->items()->orderBy('id')->pluck('final_kg')->all());
        $this->assertSame(['8.00', '0.50', '2.50'], $plan->items()->orderBy('id')->pluck('suggested_kg')->all());
    }

    public function test_confirmed_plan_cannot_be_regenerated_or_edited(): void
    {
        $plan = $this->plans->confirm($this->plans->generateDraft($this->date), $this->operator);

        try {
            $this->plans->generateDraft($this->date);
            $this->fail('Expected DomainException');
        } catch (DomainException $e) {
            $this->assertSame('Kế hoạch ngày này đã chốt, không thể tạo lại gợi ý.', $e->getMessage());
        }

        $this->expectException(DomainException::class);
        $this->plans->updateItem($plan->items()->first(), 1.0, 'sửa sau chốt');
    }

    public function test_zalo_message_lists_only_that_farmers_rows(): void
    {
        $plan = $this->plans->confirm($this->plans->generateDraft($this->date), $this->operator);

        $message = $this->plans->zaloMessage($plan, $this->nh02);

        $this->assertStringContainsString('Trần Thị Hồng', $message);
        $this->assertStringContainsString('03/10/2026', $message);
        $this->assertStringContainsString('- Rau muống: 0,5 kg', $message);
        $this->assertStringContainsString('- Cải ngọt: 2,5 kg', $message);
        $this->assertStringNotContainsString('8 kg', $message);
        $this->assertStringContainsString('/nong-ho/'.$this->nh02->access_token, $message);
    }

    public function test_sent_farmer_confirmation_and_completion(): void
    {
        $plan = $this->plans->generateDraft($this->date);

        try {
            $this->plans->markSent($plan);
            $this->fail('Draft plans cannot be sent');
        } catch (DomainException) {
        }

        $plan = $this->plans->markSent($this->plans->confirm($plan, $this->operator));
        $this->assertSame(HarvestPlanStatus::Sent, $plan->status);

        $this->assertSame(2, $this->plans->farmerConfirm($plan, $this->nh02));
        $this->assertSame(0, $this->plans->farmerConfirm($plan, $this->nh02));
        $this->assertSame(2, $plan->items()->whereNotNull('farmer_confirmed_at')->count());

        try {
            $this->plans->complete($plan);
            $this->fail('Cannot complete before everything is received');
        } catch (DomainException $e) {
            $this->assertSame('Còn dòng chưa nhập số kg thực nhận.', $e->getMessage());
        }

        $plan->items()->update(['received_kg' => 1]);
        $this->assertSame(HarvestPlanStatus::Completed, $this->plans->complete($plan)->status);
    }

    public function test_farmer_cannot_confirm_a_draft(): void
    {
        $plan = $this->plans->generateDraft($this->date);

        $this->expectException(DomainException::class);

        $this->plans->farmerConfirm($plan, $this->nh01);
    }

    public function test_paused_farmers_are_not_allocated(): void
    {
        $this->nh01->update(['status' => 'paused']);

        $plan = $this->plans->generateDraft($this->date);

        // NH02 báo 7,5 kg rau muống → 7,5; thiếu 1,0 kg thành dòng chưa có nông hộ.
        $this->assertSame([[$this->nh02->id, 7.5, 7.5], [null, 1.0, 0.0]], $this->rows($plan, $this->rm));
    }
}
```

`tests/Feature/Harvest/HarvestPlanOnSampleDataTest.php`:

```php
<?php

namespace Tests\Feature\Harvest;

use App\Models\Batch;
use App\Models\Product;
use App\Services\BatchService;
use App\Services\HarvestPlanService;
use Carbon\CarbonImmutable;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HarvestPlanOnSampleDataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-02 19:00:00');
        $this->seed(DemoSeeder::class);
    }

    public function test_remaining_and_stock_on_sample_batches(): void
    {
        $batches = app(BatchService::class);

        // 2,90 kg − 4 gói × 0,5 kg − 0,9 kg rau dư = 0
        $this->assertSame(0.0, $batches->remainingKg(Batch::where('code', 'F2H-260917-RM-NH01')->firstOrFail()));
        // Tồn còn hạn cho ngày 03/10: hành lá 0,2 kg, xà lách 0,1 kg, rau muống 0.
        $date = CarbonImmutable::parse('2026-10-03');
        $this->assertSame(0.2, $batches->availableStockKg(Product::where('code', 'HL')->firstOrFail(), $date));
        $this->assertSame(0.1, $batches->availableStockKg(Product::where('code', 'XL')->firstOrFail(), $date));
        $this->assertSame(0.0, $batches->availableStockKg(Product::where('code', 'RM')->firstOrFail(), $date));
    }

    public function test_regenerating_the_draft_for_3_october(): void
    {
        $plan = app(HarvestPlanService::class)->generateDraft(CarbonImmutable::parse('2026-10-03'));

        $byCode = $plan->items->groupBy(fn ($item) => $item->product->code)
            ->map(fn ($rows) => (float) $rows->sum('suggested_kg'))->all();

        // Khớp kế hoạch mẫu, trừ hành lá: mẫu ghi 2,5 kg vì không trừ 0,2 kg tồn.
        $this->assertSame(['RM' => 8.5, 'CN' => 7.0, 'CX' => 3.0, 'MT' => 5.5, 'RD' => 3.5, 'XL' => 1.0, 'HL' => 2.0], $byCode);

        // Rau muống: NH01 báo 8,4 → 8,0; NH03 nhận 0,5 còn lại.
        $rauMuong = $plan->items->filter(fn ($item) => $item->product->code === 'RM')->values();
        $this->assertSame([['NH01', 8.0], ['NH03', 0.5]], $rauMuong->map(fn ($item) => [$item->farmer->code, (float) $item->suggested_kg])->all());
        $this->assertSame(17, $plan->id);
    }
}
```

- [ ] **Step 2: Chạy test, xác nhận fail**

Run: `php artisan test --filter="HarvestPlanServiceTest|HarvestPlanOnSampleDataTest"`
Expected: FAIL (`Class "App\Services\HarvestPlanService" not found`).

- [ ] **Step 3: Viết `app/Services/BatchService.php` (phần tồn kho)**

```php
<?php

namespace App\Services;

use App\Enums\QualityStatus;
use App\Models\Batch;
use App\Models\Product;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class BatchService
{
    public function remainingKg(Batch $batch): float
    {
        $allocatedKg = (float) DB::table('order_item_batches')
            ->join('order_items', 'order_items.id', '=', 'order_item_batches.order_item_id')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->where('order_item_batches.batch_id', $batch->id)
            ->sum(DB::raw('order_item_batches.quantity * products.pack_weight_kg'));

        $wasteKg = (float) $batch->wasteLogs()->sum('quantity_kg');

        return max(0.0, round((float) $batch->quantity_kg - $allocatedKg - $wasteKg, 2));
    }

    // Quyết định D4: tồn của lô đạt chất lượng, thu hoạch trước ngày giao và còn hạn dùng.
    public function availableStockKg(Product $product, CarbonInterface $forDate): float
    {
        $date = CarbonImmutable::parse($forDate)->startOfDay();

        $batches = Batch::where('product_id', $product->id)
            ->where('quality_status', QualityStatus::Passed)
            ->where('harvested_at', '<', $date)
            ->whereDate('best_before', '>=', $date->toDateString())
            ->get();

        return round($batches->sum(fn (Batch $batch) => $this->remainingKg($batch)), 2);
    }
}
```

- [ ] **Step 4: Viết `app/Services/HarvestPlanService.php`**

```php
<?php

namespace App\Services;

use App\Enums\FarmerStatus;
use App\Enums\ForecastMethod;
use App\Enums\HarvestPlanStatus;
use App\Models\Farmer;
use App\Models\HarvestPlan;
use App\Models\HarvestPlanItem;
use App\Models\Product;
use App\Models\SupplyReport;
use App\Models\User;
use App\Support\Format;
use App\Support\HarvestMath;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Support\Facades\DB;

class HarvestPlanService
{
    public function __construct(
        private readonly ForecastService $forecasts,
        private readonly BatchService $batches,
    ) {}

    public function generateDraft(CarbonInterface $deliveryDate, ?User $by = null): HarvestPlan
    {
        $date = CarbonImmutable::parse($deliveryDate)->startOfDay();

        return DB::transaction(function () use ($date, $by) {
            $plan = HarvestPlan::whereDate('delivery_date', $date->toDateString())->lockForUpdate()->first();

            if ($plan !== null && $plan->status !== HarvestPlanStatus::Draft) {
                throw new DomainException('Kế hoạch ngày này đã chốt, không thể tạo lại gợi ý.');
            }

            $plan ??= HarvestPlan::create([
                'harvest_date' => $date->toDateString(),
                'delivery_date' => $date->toDateString(),
                'safety_margin_pct' => config('farm2home.safety_margin_pct'),
                'status' => HarvestPlanStatus::Draft,
                'created_by' => $by?->id,
            ]);

            $plan->items()->delete();

            $method = ForecastMethod::from(config('farm2home.forecast.default_method'));
            $margin = (float) $plan->safety_margin_pct;

            foreach (Product::where('is_active', true)->orderBy('id')->get() as $product) {
                $confirmed = $this->forecasts->confirmedKg($product, $date);
                $forecast = $this->forecasts->latestFor($product, $date, $method);
                $extra = $forecast === null ? 0.0 : max(0.0, round((float) $forecast->predicted_total_kg - $confirmed, 2));
                $stock = $this->batches->availableStockKg($product, $date);
                $suggested = HarvestMath::suggestedKg($confirmed, $extra, $stock, $margin);

                $supply = SupplyReport::where('product_id', $product->id)
                    ->whereDate('available_date', $date->toDateString())
                    ->whereHas('farmer', fn ($query) => $query->where('status', FarmerStatus::Active))
                    ->pluck('quantity_kg', 'farmer_id')
                    ->map(fn ($kg) => (float) $kg)
                    ->all();

                foreach (HarvestMath::allocate($suggested, $supply) as $index => $row) {
                    // Quyết định D5: số nhu cầu chỉ ghi ở dòng đầu để tổng theo sản phẩm không bị nhân đôi.
                    $plan->items()->create([
                        'product_id' => $product->id,
                        'farmer_id' => $row['farmer_id'],
                        'confirmed_order_kg' => $index === 0 ? $confirmed : 0,
                        'forecast_extra_kg' => $index === 0 ? $extra : 0,
                        'available_stock_kg' => $index === 0 ? $stock : 0,
                        'suggested_kg' => $row['kg'],
                    ]);
                }
            }

            return $plan->load('items.product', 'items.farmer');
        });
    }

    public function updateItem(HarvestPlanItem $item, float $finalKg, ?string $reason = null, ?int $farmerId = null): HarvestPlanItem
    {
        if ($item->plan->status !== HarvestPlanStatus::Draft) {
            throw new DomainException('Chỉ sửa được khi kế hoạch còn ở trạng thái nháp.');
        }

        if ($finalKg < 0) {
            throw new DomainException('Số kg chốt không được âm.');
        }

        $differs = abs($finalKg - (float) $item->suggested_kg) > 0.001;

        if ($differs && blank($reason)) {
            throw new DomainException('Cần ghi lý do khi số chốt khác số gợi ý.');
        }

        $item->update([
            'final_kg' => $finalKg,
            'adjust_reason' => $differs ? $reason : null,
            'farmer_id' => $farmerId ?? $item->farmer_id,
        ]);

        return $item;
    }

    public function confirm(HarvestPlan $plan, User $by): HarvestPlan
    {
        if ($plan->status !== HarvestPlanStatus::Draft) {
            throw new DomainException('Kế hoạch này đã được chốt trước đó.');
        }

        return DB::transaction(function () use ($plan, $by) {
            $plan->items()->whereNull('final_kg')->update(['final_kg' => DB::raw('suggested_kg')]);

            if ($plan->items()->whereNull('farmer_id')->where('final_kg', '>', 0)->exists()) {
                throw new DomainException('Còn dòng chưa có nông hộ. Hãy chọn nông hộ hoặc đặt số chốt về 0.');
            }

            $plan->update([
                'status' => HarvestPlanStatus::Confirmed,
                'confirmed_by' => $by->id,
                'confirmed_at' => now(),
            ]);

            return $plan->load('items.product', 'items.farmer');
        });
    }

    public function markSent(HarvestPlan $plan): HarvestPlan
    {
        if ($plan->status !== HarvestPlanStatus::Confirmed) {
            throw new DomainException('Chỉ đánh dấu đã gửi sau khi kế hoạch được chốt.');
        }

        $plan->update(['status' => HarvestPlanStatus::Sent]);

        return $plan;
    }

    public function farmerConfirm(HarvestPlan $plan, Farmer $farmer): int
    {
        if (! in_array($plan->status, [HarvestPlanStatus::Confirmed, HarvestPlanStatus::Sent], true)) {
            throw new DomainException('Kế hoạch chưa được chốt hoặc đã hoàn tất.');
        }

        return $plan->items()
            ->where('farmer_id', $farmer->id)
            ->whereNull('farmer_confirmed_at')
            ->update(['farmer_confirmed_at' => now()]);
    }

    public function zaloMessage(HarvestPlan $plan, Farmer $farmer): string
    {
        $lines = $plan->items()
            ->with('product')
            ->where('farmer_id', $farmer->id)
            ->where('final_kg', '>', 0)
            ->orderBy('product_id')
            ->get()
            ->map(fn (HarvestPlanItem $item) => '- '.$item->product->name.': '.Format::kg($item->final_kg));

        return implode("\n", [
            "FARM2HOME gửi {$farmer->name} danh sách thu hoạch ngày {$plan->harvest_date->format('d/m/Y')}:",
            ...$lines,
            'Xác nhận giúp tại: '.route('farmer.show', $farmer->access_token),
        ]);
    }

    public function complete(HarvestPlan $plan): HarvestPlan
    {
        if (! in_array($plan->status, [HarvestPlanStatus::Confirmed, HarvestPlanStatus::Sent], true)) {
            throw new DomainException('Chỉ hoàn tất được kế hoạch đã chốt.');
        }

        if ($plan->items()->where('final_kg', '>', 0)->whereNull('received_kg')->exists()) {
            throw new DomainException('Còn dòng chưa nhập số kg thực nhận.');
        }

        $plan->update(['status' => HarvestPlanStatus::Completed]);

        return $plan;
    }
}
```

Ghi chú về thứ tự trong `confirm()`: câu `update` điền `final_kg` nằm trong transaction, nên khi ném `DomainException` thì nó được rollback và kế hoạch vẫn nguyên trạng nháp.

- [ ] **Step 5: Chạy test, xác nhận pass, commit**

Run: `php artisan test --filter="HarvestPlanServiceTest|HarvestPlanOnSampleDataTest"`
Expected: PASS (16 tests).

```bash
vendor/bin/pint --dirty
git add -A
git commit -m "feat: harvest plan service with draft suggestions, operator adjustments and confirmation"
```

---

### Task 4.5: Màn hình dự báo, kế hoạch thu hoạch, và xác nhận của nông hộ

**Files:**
- Create: `app/Http/Controllers/Admin/ForecastController.php`, `app/Http/Controllers/Admin/HarvestPlanController.php`, `resources/views/admin/forecasts/index.blade.php`, `resources/views/admin/harvest-plans/{index,show}.blade.php`
- Modify: `app/Http/Controllers/Farmer/FarmerPortalController.php` (thêm `confirmHarvest`, truyền `$harvests` vào view), `resources/views/farmer/show.blade.php`, `routes/web.php`
- Test: `tests/Feature/Harvest/HarvestPlanScreensTest.php`, `tests/Feature/Forecast/ForecastScreenTest.php`

**Interfaces:**
- Consumes: toàn bộ `ForecastService`, `HarvestPlanService`.
- Produces: route `admin.forecasts.index`, `admin.forecasts.run`, `admin.forecasts.backfill`, `admin.harvest-plans.index`, `admin.harvest-plans.generate`, `admin.harvest-plans.show`, `admin.harvest-plans.confirm`, `admin.harvest-plans.sent`, `admin.harvest-plans.complete`, `admin.harvest-plan-items.update`, `farmer.harvest.confirm`.

- [ ] **Step 1: Viết test (sẽ fail)**

`tests/Feature/Harvest/HarvestPlanScreensTest.php`:

```php
<?php

namespace Tests\Feature\Harvest;

use App\Enums\HarvestPlanStatus;
use App\Enums\OrderChannel;
use App\Models\HarvestPlan;
use App\Models\SupplyReport;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsCatalog;
use Tests\TestCase;

class HarvestPlanScreensTest extends TestCase
{
    use BuildsCatalog, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-02 19:00:00');
        $this->buildCatalog();

        app(OrderService::class)->place([
            'name' => 'Khách', 'phone' => '0988000001', 'address' => '1 Lê Lợi',
            'delivery_zone_id' => $this->zone->id, 'delivery_date' => '2026-10-03', 'time_slot' => '17:00-19:00',
            'order_type' => 'single', 'items' => [['product_id' => $this->rm->id, 'quantity' => 14]],
            'payment_method' => 'cod', 'data_consent' => true,
        ], OrderChannel::Zalo);
        SupplyReport::create(['farmer_id' => $this->nh01->id, 'product_id' => $this->rm->id, 'available_date' => '2026-10-03', 'quantity_kg' => 9, 'source' => 'form']);
    }

    private function generate(): HarvestPlan
    {
        $this->actingAs($this->operator)->post('/admin/harvest-plans/generate', ['delivery_date' => '2026-10-03']);

        return HarvestPlan::firstOrFail();
    }

    public function test_generate_creates_a_draft_and_shows_the_suggestion(): void
    {
        $this->actingAs($this->operator)
            ->post('/admin/harvest-plans/generate', ['delivery_date' => '2026-10-03'])
            ->assertRedirect('/admin/harvest-plans/'.HarvestPlan::firstOrFail()->id);

        // 14 gói = 7,0 kg × 1,1 = 7,7 → 8,0 kg
        $this->get('/admin/harvest-plans/'.HarvestPlan::firstOrFail()->id)
            ->assertOk()
            ->assertSee('Nháp')
            ->assertSee('Rau muống')
            ->assertSee('Nguyễn Văn Tâm')
            ->assertSee('7 kg')
            ->assertSee('8 kg')
            ->assertSee('1 đơn chờ xác nhận')
            ->assertSee('Chốt kế hoạch');
    }

    public function test_generating_also_runs_the_forecast_for_today(): void
    {
        $this->generate();

        $this->assertDatabaseHas('forecasts', ['product_id' => $this->rm->id, 'target_date' => '2026-10-03', 'generated_on' => '2026-10-02']);
    }

    public function test_operator_adjusts_a_row_with_a_reason(): void
    {
        $item = $this->generate()->items()->firstOrFail();

        $this->from('/admin/harvest-plans/'.$item->harvest_plan_id)
            ->patch('/admin/harvest-plan-items/'.$item->id, ['final_kg' => '9', 'adjust_reason' => ''])
            ->assertSessionHasErrors('action');

        $this->patch('/admin/harvest-plan-items/'.$item->id, ['final_kg' => '9', 'adjust_reason' => 'Cuối tuần, tăng dự phòng'])
            ->assertRedirect('/admin/harvest-plans/'.$item->harvest_plan_id);

        $this->assertSame(['9.00', '8.00'], [$item->refresh()->final_kg, $item->suggested_kg]);
    }

    public function test_confirm_shows_the_zalo_message_and_link(): void
    {
        $plan = $this->generate();

        $this->post('/admin/harvest-plans/'.$plan->id.'/confirm')->assertRedirect('/admin/harvest-plans/'.$plan->id);

        $this->assertSame(HarvestPlanStatus::Confirmed, $plan->refresh()->status);
        $this->get('/admin/harvest-plans/'.$plan->id)
            ->assertSee('Đã chốt')
            ->assertSee('- Rau muống: 8 kg')
            ->assertSee('https://zalo.me/0911000001')
            ->assertSee('Đánh dấu đã gửi')
            ->assertDontSee('Chốt kế hoạch');
    }

    public function test_mark_sent_and_index_listing(): void
    {
        $plan = $this->generate();
        $this->post('/admin/harvest-plans/'.$plan->id.'/confirm');
        $this->post('/admin/harvest-plans/'.$plan->id.'/sent')->assertRedirect();

        $this->assertSame(HarvestPlanStatus::Sent, $plan->refresh()->status);
        $this->get('/admin/harvest-plans')->assertOk()->assertSee('03/10/2026')->assertSee('Đã gửi nông hộ');
    }

    public function test_farmer_sees_the_list_only_after_confirmation_and_can_confirm(): void
    {
        $plan = $this->generate();
        $url = '/nong-ho/'.$this->nh01->access_token;

        $this->get($url)->assertDontSee('Danh sách thu hoạch');
        $this->post($url.'/thu-hoach/'.$plan->id)->assertSessionHasErrors('action');

        $this->actingAs($this->operator)->post('/admin/harvest-plans/'.$plan->id.'/confirm');
        auth()->logout();

        $this->get($url)->assertSee('Danh sách thu hoạch')->assertSee('8 kg')->assertSee('Tôi sẽ thu hoạch');

        $this->post($url.'/thu-hoach/'.$plan->id)->assertRedirect($url);
        $this->assertNotNull($plan->items()->firstOrFail()->farmer_confirmed_at);
        $this->get($url)->assertSee('Đã xác nhận');
    }

    public function test_delivery_date_is_required_and_guests_are_blocked(): void
    {
        $this->actingAs($this->operator)->post('/admin/harvest-plans/generate', [])->assertSessionHasErrors('delivery_date');
        auth()->logout();
        $this->get('/admin/harvest-plans')->assertRedirect('/login');
    }
}
```

`tests/Feature/Forecast/ForecastScreenTest.php`:

```php
<?php

namespace Tests\Feature\Forecast;

use App\Models\Forecast;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ForecastScreenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-02 10:00:00');
        $this->seed(DemoSeeder::class);
        $this->actingAs(User::where('email', 'operator@farm2home.vn')->firstOrFail());
    }

    public function test_page_shows_the_latest_run_and_accuracy_table(): void
    {
        $this->get('/admin/forecasts')
            ->assertOk()
            ->assertSee('Trung bình trượt 7 ngày')
            ->assertSee('01/10/2026')
            ->assertSee('Rau muống')
            ->assertSee('7,5 kg')
            ->assertSee('MAE')
            ->assertSee('WAPE')
            ->assertSee('Làm mượt hàm mũ');
    }

    public function test_run_button_creates_todays_forecasts(): void
    {
        $this->post('/admin/forecasts/run')->assertRedirect()->assertSessionHas('status');

        // 7 sản phẩm × 7 ngày × 2 phương pháp
        $this->assertSame(98, Forecast::whereDate('generated_on', '2026-10-02')->count());
    }

    public function test_backfill_button_fills_actuals(): void
    {
        Forecast::whereDate('target_date', '2026-10-01')->update(['actual_kg' => null]);

        $this->post('/admin/forecasts/backfill')->assertRedirect()->assertSessionHas('status');

        $this->assertSame(0, Forecast::whereDate('target_date', '2026-10-01')->whereNull('actual_kg')->count());
    }
}
```

- [ ] **Step 2: Chạy test, xác nhận fail**

Run: `php artisan test --filter="HarvestPlanScreensTest|ForecastScreenTest"`
Expected: FAIL (404).

- [ ] **Step 3: Viết `app/Http/Controllers/Admin/ForecastController.php`**

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ForecastMethod;
use App\Http\Controllers\Controller;
use App\Models\Forecast;
use App\Services\ForecastService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ForecastController extends Controller
{
    public function index(ForecastService $forecasts): View
    {
        $method = ForecastMethod::from(config('farm2home.forecast.default_method'));
        $latestRun = Forecast::max('generated_on');

        $rows = $latestRun === null ? collect() : Forecast::with('product')
            ->whereDate('generated_on', $latestRun)
            ->where('method', $method)
            ->orderBy('target_date')
            ->orderBy('product_id')
            ->get();

        return view('admin.forecasts.index', [
            'method' => $method,
            'latestRun' => $latestRun,
            'byDate' => $rows->groupBy(fn (Forecast $forecast) => $forecast->target_date->toDateString()),
            'accuracy' => $forecasts->accuracy(today()->subDays(14), today()),
            'methods' => ForecastMethod::cases(),
        ]);
    }

    public function run(ForecastService $forecasts): RedirectResponse
    {
        $count = $forecasts->run(today());

        return back()->with('status', "Đã chạy dự báo: {$count} dòng.");
    }

    public function backfill(ForecastService $forecasts): RedirectResponse
    {
        $count = $forecasts->backfillActuals(today());

        return back()->with('status', "Đã cập nhật số thực tế cho {$count} dòng.");
    }
}
```

- [ ] **Step 4: Viết `app/Http/Controllers/Admin/HarvestPlanController.php`**

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Enums\FarmerStatus;
use App\Enums\HarvestPlanStatus;
use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Farmer;
use App\Models\HarvestPlan;
use App\Models\HarvestPlanItem;
use App\Models\Order;
use App\Services\ForecastService;
use App\Services\HarvestPlanService;
use Carbon\CarbonImmutable;
use Closure;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HarvestPlanController extends Controller
{
    public function index(): View
    {
        return view('admin.harvest-plans.index', [
            'plans' => HarvestPlan::withCount('items')->orderByDesc('delivery_date')->paginate(20),
            'defaultDate' => today()->addDay()->toDateString(),
        ]);
    }

    public function generate(Request $request, ForecastService $forecasts, HarvestPlanService $plans): RedirectResponse
    {
        $data = $request->validate(['delivery_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.today()->toDateString()]]);

        return $this->attempt(function () use ($data, $forecasts, $plans, $request) {
            $forecasts->run(today());
            $plan = $plans->generateDraft(CarbonImmutable::parse($data['delivery_date']), $request->user());

            return redirect()->route('admin.harvest-plans.show', $plan)->with('status', 'Đã tạo gợi ý thu hoạch. Hãy xem lại trước khi chốt.');
        });
    }

    public function show(HarvestPlan $plan, HarvestPlanService $plans): View
    {
        $plan->load('items.product', 'items.farmer', 'items.batch', 'creator', 'confirmer');

        $isConfirmed = $plan->status !== HarvestPlanStatus::Draft;
        $farmers = $plan->items->pluck('farmer')->filter()->unique('id')->values();

        return view('admin.harvest-plans.show', [
            'plan' => $plan,
            'pendingOrders' => Order::whereDate('delivery_date', $plan->delivery_date)->where('status', OrderStatus::Pending)->count(),
            'allFarmers' => Farmer::where('status', FarmerStatus::Active)->orderBy('code')->get(),
            'messages' => $isConfirmed
                ? $farmers->mapWithKeys(fn (Farmer $farmer) => [$farmer->id => $plans->zaloMessage($plan, $farmer)])
                : collect(),
            'farmers' => $farmers,
        ]);
    }

    public function updateItem(Request $request, HarvestPlanItem $item, HarvestPlanService $plans): RedirectResponse
    {
        $data = $request->validate([
            'final_kg' => ['required', 'numeric', 'min:0', 'max:1000'],
            'adjust_reason' => ['nullable', 'string', 'max:255'],
            'farmer_id' => ['nullable', 'exists:farmers,id'],
        ]);

        return $this->attempt(function () use ($data, $item, $plans) {
            $plans->updateItem($item, (float) $data['final_kg'], $data['adjust_reason'] ?? null, isset($data['farmer_id']) ? (int) $data['farmer_id'] : null);

            return redirect()->route('admin.harvest-plans.show', $item->harvest_plan_id)->with('status', 'Đã lưu số chốt.');
        });
    }

    public function confirm(Request $request, HarvestPlan $plan, HarvestPlanService $plans): RedirectResponse
    {
        return $this->attempt(function () use ($request, $plan, $plans) {
            $plans->confirm($plan, $request->user());

            return redirect()->route('admin.harvest-plans.show', $plan)->with('status', 'Đã chốt kế hoạch. Gửi tin Zalo cho từng nông hộ bên dưới.');
        });
    }

    public function sent(HarvestPlan $plan, HarvestPlanService $plans): RedirectResponse
    {
        return $this->attempt(function () use ($plan, $plans) {
            $plans->markSent($plan);

            return redirect()->route('admin.harvest-plans.show', $plan)->with('status', 'Đã đánh dấu là đã gửi nông hộ.');
        });
    }

    public function complete(HarvestPlan $plan, HarvestPlanService $plans): RedirectResponse
    {
        return $this->attempt(function () use ($plan, $plans) {
            $plans->complete($plan);

            return redirect()->route('admin.harvest-plans.show', $plan)->with('status', 'Kế hoạch đã hoàn tất.');
        });
    }

    private function attempt(Closure $action): RedirectResponse
    {
        try {
            return $action();
        } catch (DomainException $e) {
            return back()->withErrors(['action' => $e->getMessage()]);
        }
    }
}
```

- [ ] **Step 5: Bổ sung `FarmerPortalController`**

Thêm hàm:

```php
    public function confirmHarvest(Farmer $farmer, HarvestPlan $plan, HarvestPlanService $plans): RedirectResponse
    {
        $this->ensureActive($farmer);

        try {
            $plans->farmerConfirm($plan, $farmer);
        } catch (DomainException $e) {
            return back()->withErrors(['action' => $e->getMessage()]);
        }

        return redirect()->route('farmer.show', $farmer->access_token)->with('status', 'Đã ghi nhận. Cảm ơn cô chú!');
    }
```

Trong `show()`, thêm vào mảng truyền cho view:

```php
            'harvests' => HarvestPlan::whereIn('status', [HarvestPlanStatus::Confirmed, HarvestPlanStatus::Sent])
                ->whereDate('harvest_date', '>=', today()->toDateString())
                ->whereHas('items', fn ($query) => $query->where('farmer_id', $farmer->id)->where('final_kg', '>', 0))
                ->with(['items' => fn ($query) => $query->where('farmer_id', $farmer->id)->where('final_kg', '>', 0)->with('product')])
                ->orderBy('harvest_date')
                ->get(),
```

Thêm import: `App\Enums\HarvestPlanStatus`, `App\Models\HarvestPlan`, `App\Services\HarvestPlanService`, `DomainException`.

- [ ] **Step 6: Thêm route**

Trong nhóm `admin`:

```php
    Route::get('forecasts', [ForecastController::class, 'index'])->name('forecasts.index');
    Route::post('forecasts/run', [ForecastController::class, 'run'])->name('forecasts.run');
    Route::post('forecasts/backfill', [ForecastController::class, 'backfill'])->name('forecasts.backfill');

    Route::get('harvest-plans', [HarvestPlanController::class, 'index'])->name('harvest-plans.index');
    Route::post('harvest-plans/generate', [HarvestPlanController::class, 'generate'])->name('harvest-plans.generate');
    Route::get('harvest-plans/{plan}', [HarvestPlanController::class, 'show'])->name('harvest-plans.show');
    Route::post('harvest-plans/{plan}/confirm', [HarvestPlanController::class, 'confirm'])->name('harvest-plans.confirm');
    Route::post('harvest-plans/{plan}/sent', [HarvestPlanController::class, 'sent'])->name('harvest-plans.sent');
    Route::post('harvest-plans/{plan}/complete', [HarvestPlanController::class, 'complete'])->name('harvest-plans.complete');
    Route::patch('harvest-plan-items/{item}', [HarvestPlanController::class, 'updateItem'])->name('harvest-plan-items.update');
```

Trong nhóm `nong-ho/{farmer:access_token}`:

```php
    Route::post('/thu-hoach/{plan}', [FarmerPortalController::class, 'confirmHarvest'])->name('harvest.confirm');
```

Tham số `{plan}` bind vào `HarvestPlan $plan`, `{item}` bind vào `HarvestPlanItem $item` (Laravel bind theo tên biến và kiểu khai báo trong controller).

- [ ] **Step 7: Viết view**

`admin/forecasts/index.blade.php` (title "Dự báo nhu cầu"):
- Hai form nút bấm: "Chạy dự báo hôm nay" → `POST admin.forecasts.run`; "Cập nhật số thực tế" → `POST admin.forecasts.backfill`.
- Dòng thông tin: "Phương pháp đang dùng: {{ $method->label() }} · Lần chạy gần nhất: {{ \Carbon\Carbon::parse($latestRun)->format('d/m/Y') }}" (hoặc "Chưa chạy dự báo lần nào." khi `$latestRun` null).
- Bảng theo từng ngày giao (`$byDate`): cột Loại rau, Đã đặt (`@kg($f->confirmed_kg)`), Dự báo thêm (`@kg($f->predicted_extra_kg)`), Tổng dự báo (`@kg($f->predicted_total_kg)`), Khoảng (`@kg($f->lower_kg)` – `@kg($f->upper_kg)`).
- Bảng "Độ chính xác 14 ngày gần nhất (dự báo trước 1 ngày)": với mỗi `$methods` có trong `$accuracy`: label, số điểm `n`, `MAE` (kg), `MAPE` (%), `WAPE` (%). Ghi chú dưới bảng: "WAPE = tổng sai số tuyệt đối / tổng thực tế. MAPE bị phóng đại khi khối lượng mỗi loại rau nhỏ."

`admin/harvest-plans/index.blade.php` (title "Kế hoạch thu hoạch"):
- Form `POST admin.harvest-plans.generate`: input `date` tên `delivery_date` (mặc định `$defaultDate`), nút "Tạo gợi ý thu hoạch".
- Bảng: ngày giao `d/m/Y` (link `show`), `<x-status-badge :status="$plan->status" />`, số dòng, biên an toàn %.

`admin/harvest-plans/show.blade.php` (title "Kế hoạch thu hoạch {{ $plan->delivery_date->format('d/m/Y') }}"):
- Đầu trang: badge trạng thái; người tạo; người chốt + thời điểm (nếu có).
- Nếu `$pendingOrders > 0`: cảnh báo "Còn {{ $pendingOrders }} đơn chờ xác nhận cho ngày này. Số gợi ý đã tính cả các đơn đó."
- Bảng dòng kế hoạch, cột: Loại rau · Nông hộ (hoặc "Chưa có nguồn" tô đỏ khi `farmer_id` null) · Đã đặt `@kg` · Dự báo thêm `@kg` · Tồn `@kg` · **Gợi ý** `@kg($item->suggested_kg)` · **Chốt** · Lý do · Thực nhận.
  - Khi nháp: mỗi dòng là một form `PATCH route('admin.harvest-plan-items.update', $item)` gồm input số `final_kg` (step 0.5, giá trị `$item->final_kg ?? $item->suggested_kg`), input chữ `adjust_reason`, select `farmer_id` (từ `$allFarmers`, chọn sẵn nông hộ hiện tại), nút "Lưu".
  - Khi đã chốt: in `@kg($item->final_kg)`, `$item->adjust_reason`, thời điểm nông hộ xác nhận (`farmer_confirmed_at`) hoặc "Chưa xác nhận".
- Nút theo trạng thái: nháp → form `POST admin.harvest-plans.generate` (input ẩn `delivery_date`) nút "Tính lại gợi ý", và form `POST admin.harvest-plans.confirm` nút "Chốt kế hoạch"; đã chốt → form `POST admin.harvest-plans.sent` nút "Đánh dấu đã gửi"; đã chốt hoặc đã gửi → form `POST admin.harvest-plans.complete` nút "Hoàn tất kế hoạch".
- Khi đã chốt, khối "Tin nhắn cho nông hộ": với mỗi `$farmers`, một thẻ có tên, `<textarea readonly x-ref="msg{{ $farmer->id }}">{{ $messages[$farmer->id] }}</textarea>`, nút "Sao chép" (`@click="navigator.clipboard.writeText($refs.msg{{ $farmer->id }}.value)"`), link "Mở Zalo" → `{{ $farmer->zaloLink() }}` (`target="_blank" rel="noopener"`).

`farmer/show.blade.php` — thêm **phía trên** form báo sản lượng, chỉ khi `$harvests->isNotEmpty()`:
- Tiêu đề "Danh sách thu hoạch".
- Mỗi kế hoạch một thẻ: ngày `{{ $plan->harvest_date->isoFormat('dddd, DD/MM') }}`, danh sách `{{ $item->product->name }}: @kg($item->final_kg)`.
- Nếu còn dòng `farmer_confirmed_at` null: form `POST route('farmer.harvest.confirm', [$farmer->access_token, $plan])` với nút lớn "Tôi sẽ thu hoạch". Nếu đã xác nhận hết: nhãn "Đã xác nhận".
- Hiển thị `$errors->first('action')` nếu có.

- [ ] **Step 8: Chạy toàn bộ test, commit**

Run: `php artisan test`
Expected: PASS toàn bộ.

Kiểm tra tay với dữ liệu mẫu: đăng nhập operator → Kế hoạch thu hoạch → mở kế hoạch 03/10 (nháp) → "Tính lại gợi ý" → sửa một dòng kèm lý do → "Chốt kế hoạch" → sao chép tin Zalo → mở link nông hộ NH01 và bấm "Tôi sẽ thu hoạch".

```bash
vendor/bin/pint --dirty
git add -A
git commit -m "feat: forecast and harvest plan screens, zalo messages, farmer confirmation"
```

---

## Kết quả Phase 4

- [ ] Dự báo 2 phương pháp cho 7 ngày tới, chạy lại không sinh dòng trùng, có MAE/MAPE/WAPE.
- [ ] Gợi ý thu hoạch đúng công thức, chia theo nông hộ, có dòng "chưa có nguồn" khi thiếu.
- [ ] Operator sửa số chốt phải ghi lý do; chốt xong không sửa được.
- [ ] Tin Zalo sinh sẵn cho từng nông hộ; nông hộ xác nhận qua link.
