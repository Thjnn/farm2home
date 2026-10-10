<?php

namespace Tests\Feature\Farmer;

use App\Enums\HarvestPlanStatus;
use App\Enums\SupplySource;
use App\Models\Forecast;
use App\Models\HarvestPlan;
use App\Models\SupplyReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsCatalog;
use Tests\TestCase;

class FarmerPortalTest extends TestCase
{
    use BuildsCatalog, RefreshDatabase;

    private string $url;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-02 10:00:00');
        $this->buildCatalog();
        $this->url = '/nong-ho/'.$this->nh01->access_token;
    }

    public function test_farmer_sees_own_products_for_the_next_seven_days_without_login(): void
    {
        $this->get($this->url)
            ->assertOk()
            ->assertSee('Nguyễn Văn Tâm')
            ->assertSee('Rau muống')
            ->assertSee('Hành lá')
            ->assertDontSee('Cải ngọt')
            ->assertSee('03/10')
            ->assertSee('09/10')
            ->assertDontSee('10/10');
    }

    public function test_farmer_sees_the_expected_demand_for_own_products_with_a_harvest_warning(): void
    {
        Forecast::factory()->create(['product_id' => $this->rm->id, 'target_date' => '2026-10-03', 'predicted_total_kg' => 20.2, 'confirmed_kg' => 5]);
        Forecast::factory()->create(['product_id' => $this->rm->id, 'target_date' => '2026-10-04', 'predicted_total_kg' => 10]);
        Forecast::factory()->create(['product_id' => $this->cn->id, 'target_date' => '2026-10-03', 'predicted_total_kg' => 99]);

        $this->get($this->url)
            ->assertOk()
            ->assertSee('Tuần này khách dự kiến cần')
            ->assertSee('khoảng 30 kg')
            ->assertSee('thứ bảy 03/10: khoảng 20 kg', false)
            ->assertSee('Chỉ cắt rau theo')
            ->assertDontSee('Cải ngọt');
    }

    public function test_no_demand_box_without_forecasts(): void
    {
        $this->get($this->url)->assertOk()->assertDontSee('Tuần này khách dự kiến cần');
    }

    public function test_unknown_token_and_inactive_farmer_get_404(): void
    {
        $this->get('/nong-ho/'.str_repeat('z', 40))->assertNotFound();

        $this->nh01->update(['status' => 'inactive']);
        $this->get($this->url)->assertNotFound();
    }

    public function test_farmer_reports_supply(): void
    {
        $this->post($this->url.'/san-luong', ['supply' => ['2026-10-03' => [$this->rm->id => '6.5', $this->hl->id => '']]])
            ->assertRedirect($this->url)
            ->assertSessionHas('status');

        $this->assertSame(1, SupplyReport::count());
        $report = SupplyReport::firstOrFail();
        $this->assertSame('6.50', $report->quantity_kg);
        $this->assertSame(SupplySource::Form, $report->source);
        $this->assertSame('2026-10-03', $report->available_date->toDateString());
    }

    public function test_reporting_again_updates_instead_of_duplicating(): void
    {
        $this->post($this->url.'/san-luong', ['supply' => ['2026-10-03' => [$this->rm->id => '6.5']]]);
        $this->post($this->url.'/san-luong', ['supply' => ['2026-10-03' => [$this->rm->id => '4']]]);

        $this->assertSame(1, SupplyReport::count());
        $this->assertSame('4.00', SupplyReport::firstOrFail()->quantity_kg);
    }

    public function test_blank_or_zero_removes_the_report(): void
    {
        $this->post($this->url.'/san-luong', ['supply' => ['2026-10-03' => [$this->rm->id => '6.5']]]);
        $this->post($this->url.'/san-luong', ['supply' => ['2026-10-03' => [$this->rm->id => '0']]]);

        $this->assertSame(0, SupplyReport::count());
    }

    public function test_product_the_farmer_does_not_grow_is_rejected(): void
    {
        $this->post($this->url.'/san-luong', ['supply' => ['2026-10-03' => [$this->cn->id => '5']]])
            ->assertSessionHasErrors('supply');

        $this->assertSame(0, SupplyReport::count());
    }

    public function test_dates_outside_the_window_are_rejected(): void
    {
        foreach (['2026-10-02', '2026-10-10', 'khong-phai-ngay'] as $date) {
            $this->post($this->url.'/san-luong', ['supply' => [$date => [$this->rm->id => '5']]])
                ->assertSessionHasErrors('supply');
        }

        $this->assertSame(0, SupplyReport::count());
    }

    public function test_quantity_must_be_between_0_and_500(): void
    {
        $this->post($this->url.'/san-luong', ['supply' => ['2026-10-03' => [$this->rm->id => '-1']]])->assertSessionHasErrors();
        $this->post($this->url.'/san-luong', ['supply' => ['2026-10-03' => [$this->rm->id => '501']]])->assertSessionHasErrors();
        $this->post($this->url.'/san-luong', ['supply' => ['2026-10-03' => [$this->rm->id => 'nhiều']]])->assertSessionHasErrors();
    }

    public function test_date_with_a_confirmed_plan_is_locked(): void
    {
        HarvestPlan::factory()->create(['harvest_date' => '2026-10-03', 'delivery_date' => '2026-10-03', 'safety_margin_pct' => 10, 'status' => HarvestPlanStatus::Confirmed]);

        $this->get($this->url)->assertSee('Đã chốt');

        $this->post($this->url.'/san-luong', ['supply' => ['2026-10-03' => [$this->rm->id => '5']]])
            ->assertSessionHasErrors('supply');

        $this->assertSame(0, SupplyReport::count());
    }

    public function test_existing_reports_are_prefilled(): void
    {
        SupplyReport::factory()->create(['farmer_id' => $this->nh01->id, 'product_id' => $this->rm->id, 'available_date' => '2026-10-04', 'quantity_kg' => 7.5, 'source' => 'zalo']);

        $this->get($this->url)->assertSee('value="7.5"', false);
    }
}
