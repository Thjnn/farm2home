<?php

namespace Tests\Feature;

use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DemoShiftDatesTest extends TestCase
{
    use RefreshDatabase;

    public function test_everything_moves_by_whole_weeks_and_codes_follow(): void
    {
        $this->seed(DemoSeeder::class);
        // Ngày giao muộn nhất trong mẫu: 03/10/2026. Ngày mai = 21/11/2026 → dịch đúng 49 ngày (7 tuần).
        $this->travelTo('2026-11-20 09:00:00');

        $this->artisan('demo:shift-dates')->expectsOutputToContain('49')->assertSuccessful();

        $this->assertSame('2026-11-21', DB::table('orders')->max('delivery_date'));
        $this->assertSame('2026-10-29', DB::table('orders')->min('delivery_date'));
        $this->assertSame(155, DB::table('orders')->count());
        $this->assertDatabaseHas('orders', ['code' => 'DH261121-013', 'delivery_date' => '2026-11-21']);
        $this->assertDatabaseMissing('orders', ['code' => 'DH261003-013']);

        // Lô 17/09 → 05/11, mã lô và hạn dùng đi theo.
        $this->assertDatabaseHas('batches', ['code' => 'F2H-261105-RM-NH01', 'harvested_at' => '2026-11-05 05:30:00', 'best_before' => '2026-11-07']);
        $this->assertSame(146, DB::table('batches')->count());

        $this->assertSame('2026-11-21', DB::table('harvest_plans')->max('delivery_date'));
        $this->assertSame(17, DB::table('harvest_plans')->count());
        $this->assertSame(308, DB::table('forecasts')->count());
        $this->assertSame('2026-11-19', DB::table('forecasts')->max('generated_on'));
        $this->assertSame(377, DB::table('supply_reports')->count());
        $this->assertSame('2026-11-19', DB::table('waste_logs')->max('log_date'));
        $this->assertSame('2026-11-03', DB::table('subscriptions')->where('id', 1)->value('start_date'));
    }

    public function test_nothing_moves_when_less_than_a_week_has_passed(): void
    {
        $this->seed(DemoSeeder::class);
        $this->travelTo('2026-10-08 09:00:00');

        $this->artisan('demo:shift-dates')->expectsOutputToContain('Không cần dịch')->assertSuccessful();

        $this->assertSame('2026-10-03', DB::table('orders')->max('delivery_date'));
    }

    public function test_refuses_to_run_in_production_without_force(): void
    {
        $this->seed(DemoSeeder::class);
        $this->travelTo('2026-11-20 09:00:00');
        $this->app->detectEnvironment(fn () => 'production');

        $this->artisan('demo:shift-dates')->assertFailed();

        $this->assertSame('2026-10-03', DB::table('orders')->max('delivery_date'));
    }

    public function test_point_transactions_move_with_the_rest(): void
    {
        $this->seed(DemoSeeder::class);
        $this->artisan('loyalty:backfill')->assertSuccessful();
        $this->travelTo('2026-11-20 09:00:00');

        $this->artisan('demo:shift-dates')->assertSuccessful();

        // Bao bì trả ngày 19/09 → 07/11 (dịch 49 ngày), dòng điểm đi theo.
        $this->assertSame('2026-11-07 18:00:00', DB::table('point_transactions')->where('packaging_return_id', 1)->value('created_at'));
        $this->assertSame(89, DB::table('point_transactions')->count());
    }
}
