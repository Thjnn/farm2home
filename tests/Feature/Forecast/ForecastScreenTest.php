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
            ->assertSee('Làm mượt hàm mũ');
    }

    public function test_run_button_creates_todays_forecasts(): void
    {
        $this->post('/admin/forecasts/run')->assertRedirect()->assertSessionHas('status');

        // 7 sản phẩm × 7 ngày × 3 phương pháp
        $this->assertSame(147, Forecast::whereDate('generated_on', '2026-10-02')->count());
    }

    public function test_method_can_be_switched_and_unknown_method_falls_back_to_default(): void
    {
        $this->post('/admin/forecasts/run');

        $this->get('/admin/forecasts?method=weekday_average')
            ->assertOk()
            ->assertViewHas('method', ForecastMethod::WeekdayAverage)
            ->assertSee('đang dùng cho kế hoạch')
            ->assertSee('Trợ lý dự báo · '.ForecastMethod::WeekdayAverage->label());

        $this->get('/admin/forecasts?method=bogus')
            ->assertOk()
            ->assertViewHas('method', ForecastMethod::MovingAverage);
    }

    public function test_lowest_error_method_is_marked(): void
    {
        $this->get('/admin/forecasts')
            ->assertOk()
            ->assertViewHas('bestMethod', fn ($best) => $best instanceof ForecastMethod)
            ->assertSee('Sai số thấp nhất')
            ->assertSee('đoán sát nhất')
            ->assertSee('Lệch dư/thiếu');
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

    public function test_backfill_button_fills_actuals(): void
    {
        Forecast::whereDate('target_date', '2026-10-01')->update(['actual_kg' => null]);

        $this->post('/admin/forecasts/backfill')->assertRedirect()->assertSessionHas('status');

        $this->assertSame(0, Forecast::whereDate('target_date', '2026-10-01')->whereNull('actual_kg')->count());
    }
}
