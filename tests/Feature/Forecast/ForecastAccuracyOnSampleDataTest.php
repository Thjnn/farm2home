<?php

namespace Tests\Feature\Forecast;

use App\Models\Product;
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
        $rauMuong = Product::where('code', 'RM')->firstOrFail();

        $this->assertSame(7.5, $service->confirmedKg($rauMuong, CarbonImmutable::parse('2026-10-03')));
        $this->assertSame(
            ['2026-09-17' => 2.0, '2026-09-18' => 0.5],
            $service->demandHistory($rauMuong, CarbonImmutable::parse('2026-09-17'), CarbonImmutable::parse('2026-09-18')),
        );
    }
}
