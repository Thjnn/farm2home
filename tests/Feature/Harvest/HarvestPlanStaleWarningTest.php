<?php

namespace Tests\Feature\Harvest;

use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Models\Farmer;
use App\Models\HarvestPlan;
use App\Models\Order;
use App\Models\SupplyReport;
use App\Services\HarvestPlanService;
use App\Services\OrderService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsCatalog;
use Tests\TestCase;

class HarvestPlanStaleWarningTest extends TestCase
{
    use BuildsCatalog, RefreshDatabase;

    private HarvestPlanService $plans;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-02 19:00:00');
        $this->buildCatalog();
        $this->plans = app(HarvestPlanService::class);

        $this->placeOrder('0988000001');
        $this->report($this->nh01, $this->rm->id, 9);
        $this->report($this->nh02, $this->rm->id, 9);
    }

    private function placeOrder(string $phone): void
    {
        app(OrderService::class)->place([
            'name' => 'Khách', 'phone' => $phone, 'address' => '1 Lê Lợi',
            'delivery_zone_id' => $this->zone->id, 'delivery_date' => '2026-10-03', 'time_slot' => '17:00-19:00',
            'order_type' => 'single', 'items' => [['product_id' => $this->rm->id, 'quantity' => 4]],
            'payment_method' => 'cod', 'data_consent' => true,
        ], OrderChannel::Zalo);
    }

    private function report(Farmer $farmer, int $productId, float $kg): void
    {
        SupplyReport::updateOrCreate(
            ['farmer_id' => $farmer->id, 'product_id' => $productId, 'available_date' => '2026-10-03'],
            ['quantity_kg' => $kg, 'source' => 'form'],
        );
    }

    private function generate(): HarvestPlan
    {
        return $this->plans->generateDraft(CarbonImmutable::parse('2026-10-03'), $this->operator);
    }

    public function test_fresh_draft_has_no_warnings(): void
    {
        $this->assertSame([], $this->plans->staleWarnings($this->generate()));
    }

    public function test_order_placed_after_generation_is_flagged(): void
    {
        $plan = $this->generate();
        $this->travel(5)->minutes();
        $this->placeOrder('0988000002');

        $warnings = $this->plans->staleWarnings($plan);

        $this->assertCount(1, $warnings);
        $this->assertStringContainsString('1 đơn mới', $warnings[0]);
        $this->assertStringContainsString('19:00 02/10', $warnings[0]);
    }

    public function test_confirming_a_pending_order_is_not_flagged_but_cancelling_one_is(): void
    {
        $plan = $this->generate();
        $this->travel(5)->minutes();
        $orders = app(OrderService::class);

        $orders->transition(Order::firstOrFail(), OrderStatus::Confirmed);
        $this->assertSame([], $this->plans->staleWarnings($plan));

        $orders->transition(Order::firstOrFail(), OrderStatus::Cancelled, 'Khách đổi ý');
        $warnings = $this->plans->staleWarnings($plan);
        $this->assertCount(1, $warnings);
        $this->assertStringContainsString('1 đơn bị hủy', $warnings[0]);
    }

    public function test_supply_change_after_generation_names_the_cooperative_once(): void
    {
        $this->report($this->nh01, $this->hl->id, 2);
        $plan = $this->generate();
        $this->travel(5)->minutes();
        $this->report($this->nh01, $this->rm->id, 12);
        $this->report($this->nh01, $this->hl->id, 3);

        $warnings = implode("\n", $this->plans->staleWarnings($plan));

        $this->assertSame(1, substr_count($warnings, $this->nh01->name));
        $this->assertStringContainsString('sau lúc tạo gợi ý', $warnings);
        $this->assertStringNotContainsString($this->nh02->name, $warnings);
    }

    public function test_active_cooperative_without_report_is_listed_but_paused_one_is_not(): void
    {
        SupplyReport::query()->delete();
        $this->nh02->update(['status' => 'paused']);

        $warnings = implode("\n", $this->plans->staleWarnings($this->generate()));

        $this->assertStringContainsString('chưa báo sản lượng', $warnings);
        $this->assertStringContainsString($this->nh01->name, $warnings);
        $this->assertStringNotContainsString($this->nh02->name, $warnings);
    }

    public function test_confirmed_plan_has_no_warnings(): void
    {
        $plan = $this->generate();
        $this->plans->confirm($plan, $this->operator);
        $this->travel(5)->minutes();
        $this->placeOrder('0988000002');

        $this->assertSame([], $this->plans->staleWarnings($plan->refresh()));
    }

    public function test_show_page_renders_warning_box(): void
    {
        $plan = $this->generate();
        $this->travel(5)->minutes();
        $this->placeOrder('0988000002');

        $this->actingAs($this->operator)->get('/admin/harvest-plans/'.$plan->id)
            ->assertOk()->assertSee('Kiểm tra dữ liệu trước khi chốt')->assertSee('1 đơn mới');
    }
}
