<?php

namespace Tests\Feature\Kpi;

use App\Services\KpiService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KpiServiceEmptyTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_database_gives_nulls_and_zeros_not_division_errors(): void
    {
        $kpi = app(KpiService::class);
        $from = CarbonImmutable::parse('2026-10-01');
        $to = CarbonImmutable::parse('2026-10-07');

        $this->assertSame(['accuracy' => [], 'plan_items' => 0, 'adjusted_items' => 0], $kpi->forecast($from, $to));
        $this->assertNull($kpi->customers($from, $to)['fill_rate_pct']);
        $this->assertNull($kpi->customers($from, $to)['repeat_rate_pct']);
        $this->assertNull($kpi->customers($from, $to)['avg_rating']);
        $this->assertSame(0, $kpi->economics($from, $to)['revenue']);
        $this->assertNull($kpi->economics($from, $to)['avg_order_value']);
        $this->assertNull($kpi->economics($from, $to)['gross_margin_pct']);
        $this->assertNull($kpi->farmers($from, $to)['advance_confirmed_pct']);
        $this->assertSame([], $kpi->environment($from, $to)['waste_by_week']);
        $this->assertNull($kpi->environment($from, $to)['waste_pct']);
        $this->assertNull($kpi->environment($from, $to)['packaging_reuse_pct']);
    }
}
