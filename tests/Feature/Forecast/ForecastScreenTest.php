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
