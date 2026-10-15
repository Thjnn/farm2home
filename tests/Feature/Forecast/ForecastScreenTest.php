<?php

namespace Tests\Feature\Forecast;

use App\Enums\ForecastMethod;
use App\Models\Forecast;
use App\Models\Product;
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
            ->assertSee('Làm mượt hàm mũ')
            ->assertSee('Khách đã đặt')
            ->assertSee('đoán đúng');
    }

    public function test_run_button_creates_todays_forecasts(): void
    {
        $this->post('/admin/forecasts/run')->assertRedirect()->assertSessionHas('status');

        // 5 sản phẩm đang bán × 7 ngày × 3 phương pháp
        $this->assertSame(105, Forecast::whereDate('generated_on', '2026-10-02')->count());
    }

    public function test_method_can_be_switched_and_unknown_method_falls_back_to_default(): void
    {
        $this->post('/admin/forecasts/run');

        $this->get('/admin/forecasts?method=weekday_average')
            ->assertOk()
            ->assertViewHas('method', ForecastMethod::WeekdayAverage)
            ->assertSee('đang dùng cho kế hoạch')
            ->assertSee(ForecastMethod::WeekdayAverage->plainExplanation());

        $this->get('/admin/forecasts?method=bogus')
            ->assertOk()
            ->assertViewHas('method', ForecastMethod::MovingAverage);
    }

    public function test_lowest_error_method_is_marked(): void
    {
        $this->get('/admin/forecasts')
            ->assertOk()
            ->assertViewHas('bestMethod', fn ($best) => $best instanceof ForecastMethod)
            ->assertSee('Đoán sát nhất')
            ->assertSee('Máy đoán có đúng không?')
            ->assertSee('Lệch dư/thiếu');
    }

    public function test_warning_lists_the_worst_day_when_hit_rate_is_below_target(): void
    {
        config(['farm2home.forecast.target_hit_rate' => 101]);
        Forecast::whereDate('target_date', '2026-09-30')->update(['actual_kg' => 0]);

        $this->get('/admin/forecasts')
            ->assertOk()
            ->assertSee('Đoán đúng dưới 101%')
            ->assertSee('Thứ tư 30/09')
            ->assertSee('Khách mua ít hơn mọi khi');
    }

    public function test_no_warning_when_hit_rate_meets_target(): void
    {
        config(['farm2home.forecast.target_hit_rate' => 0]);

        $this->get('/admin/forecasts')->assertOk()->assertViewHas('worstDays', [])->assertDontSee('Đoán đúng dưới');
    }

    public function test_chart_has_28_days_of_actuals_and_the_latest_run_ahead(): void
    {
        $product = Product::where('code', 'RM')->firstOrFail();

        $this->get('/admin/forecasts')->assertViewHas('chart', function (array $chart) {
            $this->assertCount(35, $chart['labels']);
            $this->assertSame('01/10', $chart['labels'][27]);
            $this->assertNotNull($chart['actual'][27]);
            $this->assertNull($chart['actual'][28]);
            $this->assertNull($chart['forecast'][27]);
            $this->assertNotNull($chart['forecast'][28]);

            return true;
        });

        $this->get("/admin/forecasts?product={$product->id}")->assertViewHas('chart', function (array $chart) use ($product) {
            $latest = Forecast::where('product_id', $product->id)->whereDate('generated_on', '2026-10-01')
                ->whereDate('target_date', '2026-10-02')->where('method', 'moving_average')->firstOrFail();
            $this->assertEqualsWithDelta((float) $latest->predicted_total_kg, $chart['forecast'][28], 0.001);

            return true;
        });
    }

    public function test_calendar_uses_the_latest_forecast_before_each_day(): void
    {
        $this->get('/admin/forecasts?day=2026-09-30')
            ->assertOk()
            ->assertSee('Tháng 9, 2026')
            ->assertSee('Thứ tư, 30/09')
            ->assertViewHas('calendar', function (array $calendar) {
                // 30/09 đã giao: số đoán trước 1 ngày của rau đang bán, có thực tế.
                $past = $calendar['2026-09-30'];
                $expected = Forecast::where('method', 'moving_average')->whereDate('target_date', '2026-09-30')
                    ->whereDate('generated_on', '2026-09-29')->whereHas('product', fn ($query) => $query->where('is_active', true))->sum('predicted_total_kg');
                $this->assertEqualsWithDelta((float) $expected, $past['predicted'], 0.001);
                $this->assertNotNull($past['actual']);

                // 03/10 chưa giao: lấy lần chạy mới nhất (01/10).
                $this->assertSame('2026-10-01', $calendar['2026-10-03']['items']->first()->generated_on->toDateString());
                $this->assertNull($calendar['2026-10-03']['actual']);

                return true;
            });

        $this->get('/admin/forecasts?month=2026-11')->assertOk()->assertSee('Tháng 11, 2026');
        $this->get('/admin/forecasts?month=bogus&day=xx')->assertOk();
    }

    public function test_backfill_button_fills_actuals(): void
    {
        Forecast::whereDate('target_date', '2026-10-01')->update(['actual_kg' => null]);

        $this->post('/admin/forecasts/backfill')->assertRedirect()->assertSessionHas('status');

        $this->assertSame(0, Forecast::whereDate('target_date', '2026-10-01')->whereNull('actual_kg')->count());
    }
}
