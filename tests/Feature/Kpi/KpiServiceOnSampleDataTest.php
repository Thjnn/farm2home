<?php

namespace Tests\Feature\Kpi;

use App\Services\KpiService;
use Carbon\CarbonImmutable;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Mọi con số kỳ vọng được tính độc lập từ database/farm2home.sql cho khoảng 10/09–03/10/2026.
 */
class KpiServiceOnSampleDataTest extends TestCase
{
    use RefreshDatabase;

    private KpiService $kpi;

    private CarbonImmutable $from;

    private CarbonImmutable $to;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-03 21:00:00');
        $this->seed(DemoSeeder::class);
        $this->kpi = app(KpiService::class);
        $this->from = CarbonImmutable::parse('2026-09-10');
        $this->to = CarbonImmutable::parse('2026-10-03');
    }

    public function test_forecast_kpis(): void
    {
        $kpi = $this->kpi->forecast($this->from, $this->to);

        $this->assertSame(['n' => 105, 'mae' => 0.65, 'mape' => 51.4, 'wape' => 39.1], $kpi['accuracy']['moving_average']);
        $this->assertSame(112, $kpi['plan_items']);
        $this->assertSame(38, $kpi['adjusted_items']);
    }

    public function test_customer_kpis(): void
    {
        $this->assertSame([
            'fill_rate_pct' => 98.3,
            'customers' => 25,
            'repeat_customers' => 24,
            'repeat_rate_pct' => 96.0,
            'feedback_total' => 45,
            'feedback_resolved' => 38,
            'avg_rating' => 4.64,
        ], $this->kpi->customers($this->from, $this->to));
    }

    public function test_economic_kpis(): void
    {
        $kpi = $this->kpi->economics($this->from, $this->to);

        $this->assertSame(131, $kpi['delivered_orders']);
        $this->assertSame(7773000, $kpi['revenue']);
        $this->assertSame(59336, $kpi['avg_order_value']);
        $this->assertSame(6453000, $kpi['goods_revenue']);
        $this->assertSame(5634200, $kpi['purchase_cost']);
        $this->assertSame(12.7, $kpi['gross_margin_pct']);
        $this->assertSame(10076, $kpi['shipping_per_order']);
        $this->assertCount(22, $kpi['revenue_by_day']);
        $this->assertSame(7773000, array_sum($kpi['revenue_by_day']));
    }

    public function test_farmer_kpis(): void
    {
        $this->assertSame(['active_farmers' => 3, 'advance_confirmed_pct' => 100.0], $this->kpi->farmers($this->from, $this->to));
    }

    public function test_environment_kpis(): void
    {
        $kpi = $this->kpi->environment($this->from, $this->to);

        $this->assertSame(['2026-W37' => 2.6, '2026-W38' => 20.7, '2026-W39' => 36.2, '2026-W40' => 28.2], $kpi['waste_by_week']);
        $this->assertSame(['2026-W37', '2026-W38', '2026-W39', '2026-W40'], array_keys($kpi['received_by_week']));
        $this->assertSame(87.7, $kpi['waste_kg']);
        $this->assertSame(322.9, $kpi['received_kg']);
        $this->assertSame(27.2, $kpi['waste_pct']);
        $this->assertSame([12, 10, 83.3], [$kpi['packaging_returned'], $kpi['packaging_reused'], $kpi['packaging_reuse_pct']]);
    }

    public function test_range_is_respected(): void
    {
        $kpi = $this->kpi->economics(CarbonImmutable::parse('2026-09-10'), CarbonImmutable::parse('2026-09-10'));

        // 10/09 có 3 đơn đã giao: 34.000 + 35.000 + 59.000.
        $this->assertSame([3, 128000], [$kpi['delivered_orders'], $kpi['revenue']]);
    }
}
