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
