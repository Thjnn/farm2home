<?php

namespace Tests\Feature\Farmer;

use App\Enums\SupplySource;
use App\Models\HarvestPlan;
use App\Models\HarvestPlanItem;
use App\Models\SupplyReport;
use App\Services\SupplyReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsCatalog;
use Tests\TestCase;

class AdminSupplyTest extends TestCase
{
    use BuildsCatalog, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-02 10:00:00');
        $this->buildCatalog();
    }

    public function test_grid_shows_each_farmer_with_their_products_and_link(): void
    {
        SupplyReport::factory()->create(['farmer_id' => $this->nh01->id, 'product_id' => $this->rm->id, 'available_date' => '2026-10-03', 'quantity_kg' => 6.5, 'source' => 'form']);

        $this->actingAs($this->operator)->get('/admin/supply?date=2026-10-03')
            ->assertOk()
            ->assertSee('Nguyễn Văn Tâm')
            ->assertSee('Trần Thị Hồng')
            ->assertSee('value="6.5"', false)
            ->assertSee('/nong-ho/'.$this->nh01->access_token)
            ->assertSee('Tổng nguồn cung');
    }

    public function test_supply_far_above_the_farmers_usual_level_is_flagged_for_a_call_back(): void
    {
        // NH01 thường có 40 kg rau muống/tuần ≈ 5,7 kg/ngày; × 3 = 17,1 kg là ngưỡng.
        SupplyReport::factory()->create(['farmer_id' => $this->nh01->id, 'product_id' => $this->rm->id, 'available_date' => '2026-10-03', 'quantity_kg' => 80, 'source' => 'form']);
        SupplyReport::factory()->create(['farmer_id' => $this->nh02->id, 'product_id' => $this->rm->id, 'available_date' => '2026-10-03', 'quantity_kg' => 9, 'source' => 'form']);

        $unusual = app(SupplyReportService::class)->unusualReports('2026-10-03');
        $this->assertSame(["{$this->nh01->id}-{$this->rm->id}"], $unusual->keys()->all());
        $this->assertSame(['kg' => 80.0, 'usual_kg' => 5.7], $unusual->first());

        $this->actingAs($this->operator)->get('/admin/supply?date=2026-10-03')
            ->assertSee('Cao bất thường: thường khoảng 5,7 kg/ngày');

        $plan = HarvestPlan::factory()->create(['harvest_date' => '2026-10-03', 'delivery_date' => '2026-10-03']);
        HarvestPlanItem::factory()->create(['harvest_plan_id' => $plan->id, 'product_id' => $this->rm->id, 'farmer_id' => $this->nh01->id, 'suggested_kg' => 8]);
        $this->get('/admin/harvest-plans/'.$plan->id)->assertSee('Báo 80 kg, thường khoảng 5,7 kg/ngày');
    }

    public function test_operator_saves_supply_reported_over_zalo(): void
    {
        $this->actingAs($this->operator)->post('/admin/supply', [
            'date' => '2026-10-03',
            'source' => 'zalo',
            'supply' => [
                $this->nh01->id => [$this->rm->id => '8', $this->hl->id => '2'],
                $this->nh02->id => [$this->cn->id => '7.5'],
            ],
        ])->assertRedirect('/admin/supply?date=2026-10-03');

        $this->assertSame(3, SupplyReport::count());
        $this->assertSame(SupplySource::Zalo, SupplyReport::firstOrFail()->source);
    }

    public function test_source_must_be_zalo_or_operator_and_date_not_in_the_past(): void
    {
        $this->actingAs($this->operator)
            ->post('/admin/supply', ['date' => '2026-10-03', 'source' => 'form', 'supply' => []])
            ->assertSessionHasErrors('source');

        $this->actingAs($this->operator)
            ->post('/admin/supply', ['date' => '2026-10-01', 'source' => 'zalo', 'supply' => []])
            ->assertSessionHasErrors('date');
    }

    public function test_guest_is_redirected(): void
    {
        $this->get('/admin/supply')->assertRedirect('/login');
    }
}
