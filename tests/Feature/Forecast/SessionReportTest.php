<?php

namespace Tests\Feature\Forecast;

use App\Enums\ForecastMethod;
use App\Enums\OrderChannel;
use App\Models\Forecast;
use App\Models\OrderItem;
use App\Models\UnmetDemand;
use App\Models\WasteLog;
use App\Services\KpiService;
use App\Services\OrderService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsCatalog;
use Tests\TestCase;

class SessionReportTest extends TestCase
{
    use BuildsCatalog, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-09-29 10:00:00');
        $this->buildCatalog();

        // Buổi 30/09: rau muống 4 gói = 2 kg, giao được 3 gói = 1,5 kg, dư 0,5 kg.
        app(OrderService::class)->place([
            'name' => 'Khách', 'phone' => '0988000001', 'address' => '1 Lê Lợi',
            'delivery_zone_id' => $this->zone->id, 'delivery_date' => '2026-09-30', 'time_slot' => '17:00-19:00',
            'order_type' => 'single', 'items' => [['product_id' => $this->rm->id, 'quantity' => 4]],
            'payment_method' => 'cod', 'data_consent' => true,
        ], OrderChannel::Zalo);
        OrderItem::query()->update(['fulfilled_quantity' => 3]);
        WasteLog::factory()->create(['log_date' => '2026-09-30', 'product_id' => $this->rm->id, 'quantity_kg' => 0.5]);

        $this->travelTo('2026-10-02 10:00:00');
    }

    public function test_sessions_report_requested_delivered_surplus_unmet_and_money(): void
    {
        UnmetDemand::factory()->create(['delivery_date' => '2026-09-30', 'product_id' => $this->rm->id, 'quantity_kg' => 1]);

        $rows = app(KpiService::class)->sessions(CarbonImmutable::parse('2026-09-30'), CarbonImmutable::parse('2026-09-30'));

        $this->assertCount(1, $rows);
        // Rau muống: giá 12.000đ/0,5 kg = 24.000đ/kg, thu mua 14.000đ/kg → dư 0,5 kg = 7.000đ, hết 1 kg mất lãi 10.000đ.
        $this->assertSame(
            ['2026-09-30', 2.0, 1.5, 0.5, 1.0, 7000, 10000],
            [$rows[0]['date'], $rows[0]['requested_kg'], $rows[0]['delivered_kg'], $rows[0]['surplus_kg'], $rows[0]['unmet_kg'], $rows[0]['surplus_vnd'], $rows[0]['missed_margin_vnd']],
        );
    }

    public function test_page_shows_comparison_and_operator_records_unmet_demand(): void
    {
        $row = ['product_id' => $this->rm->id, 'target_date' => '2026-09-30', 'generated_on' => '2026-09-28', 'confirmed_kg' => 0.5, 'actual_kg' => 2];
        Forecast::factory()->create($row + ['method' => ForecastMethod::MovingAverage, 'predicted_extra_kg' => 1, 'predicted_total_kg' => 1.5]);
        Forecast::factory()->create($row + ['method' => ForecastMethod::ExtraAverage, 'predicted_extra_kg' => 1.5, 'predicted_total_kg' => 2]);
        $this->actingAs($this->operator);

        $this->get('/admin/sessions?from=2026-09-01&to=2026-10-01')
            ->assertOk()
            ->assertSee('Mô hình so với cách tính thông thường')
            ->assertSeeInOrder(['mô hình đoán gần hơn', '0', 'cách thường gần hơn', '1'])
            ->assertSee('Rau muống')
            ->assertSee('7.000đ');

        $this->post('/admin/sessions/unmet', ['delivery_date' => '2026-09-30', 'product_id' => $this->rm->id, 'quantity_kg' => 1.5])->assertRedirect();
        $this->assertSame('1.50', UnmetDemand::sole()->quantity_kg);

        $this->post('/admin/sessions/unmet', ['delivery_date' => '2026-09-30', 'product_id' => $this->rm->id, 'quantity_kg' => 0])->assertRedirect();
        $this->assertSame(0, UnmetDemand::count());
    }
}
