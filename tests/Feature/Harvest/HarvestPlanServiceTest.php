<?php

namespace Tests\Feature\Harvest;

use App\Enums\ForecastMethod;
use App\Enums\HarvestPlanStatus;
use App\Enums\OrderChannel;
use App\Models\Batch;
use App\Models\Forecast;
use App\Models\HarvestPlan;
use App\Models\Product;
use App\Models\SupplyReport;
use App\Services\HarvestPlanService;
use App\Services\OrderService;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsCatalog;
use Tests\TestCase;

class HarvestPlanServiceTest extends TestCase
{
    use BuildsCatalog, RefreshDatabase;

    private HarvestPlanService $plans;

    private CarbonImmutable $date;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-02 19:00:00');
        $this->buildCatalog();
        $this->plans = app(HarvestPlanService::class);
        $this->date = CarbonImmutable::parse('2026-10-03');

        // Nhu cầu 03/10: rau muống 15 gói = 7,5 kg; cải ngọt 4 gói = 2,0 kg.
        $this->order([['product_id' => $this->rm->id, 'quantity' => 15], ['product_id' => $this->cn->id, 'quantity' => 4]]);

        // Nguồn cung 03/10.
        $this->supply($this->nh01->id, $this->rm, 8.4);
        $this->supply($this->nh02->id, $this->rm, 7.5);
        $this->supply($this->nh02->id, $this->cn, 7.5);
    }

    private function order(array $items): void
    {
        app(OrderService::class)->place([
            'name' => 'Khách', 'phone' => '0988000001', 'address' => '1 Lê Lợi',
            'delivery_zone_id' => $this->zone->id, 'delivery_date' => '2026-10-03', 'time_slot' => '17:00-19:00',
            'order_type' => 'single', 'items' => $items, 'payment_method' => 'cod', 'data_consent' => true,
        ], OrderChannel::Zalo);
    }

    private function supply(int $farmerId, Product $product, float $kg): void
    {
        SupplyReport::factory()->create(['farmer_id' => $farmerId, 'product_id' => $product->id, 'available_date' => '2026-10-03', 'quantity_kg' => $kg, 'source' => 'form']);
    }

    private function rows(HarvestPlan $plan, Product $product): array
    {
        return $plan->items->where('product_id', $product->id)->values()
            ->map(fn ($item) => [$item->farmer_id, (float) $item->suggested_kg, (float) $item->confirmed_order_kg])->all();
    }

    public function test_draft_applies_the_formula_and_allocates_to_farmers(): void
    {
        $plan = $this->plans->generateDraft($this->date, $this->operator);

        $this->assertSame(HarvestPlanStatus::Draft, $plan->status);
        $this->assertSame('2026-10-03', $plan->delivery_date->toDateString());
        $this->assertSame('10.00', $plan->safety_margin_pct);
        $this->assertSame($this->operator->id, $plan->created_by);

        // Rau muống: 7,5 × 1,1 = 8,25 → 8,5. Chia theo tỉ lệ báo 8 : 7,5 → 4,39 và 4,11 → 4,5 và 4,0.
        $this->assertSame([[$this->nh01->id, 4.5, 7.5], [$this->nh02->id, 4.0, 0.0]], $this->rows($plan, $this->rm));
        // Cải ngọt: 2,0 × 1,1 = 2,2 → 2,5.
        $this->assertSame([[$this->nh02->id, 2.5, 2.0]], $this->rows($plan, $this->cn));
        // Hành lá không có nhu cầu → không có dòng.
        $this->assertSame([], $this->rows($plan, $this->hl));
        $this->assertNull($plan->items->first()->final_kg);
    }

    public function test_forecast_extra_is_added_on_top_of_placed_orders(): void
    {
        Forecast::factory()->create([
            'product_id' => $this->rm->id, 'target_date' => '2026-10-03', 'generated_on' => '2026-10-02', 'method' => ForecastMethod::MovingAverage,
            'confirmed_kg' => 7.5, 'predicted_extra_kg' => 1.5, 'predicted_total_kg' => 9.0, 'lower_kg' => 7.5, 'upper_kg' => 10,
        ]);

        $plan = $this->plans->generateDraft($this->date);

        // (7,5 + 1,5) × 1,1 = 9,9 → 10,0
        $this->assertSame(10.0, (float) $plan->items->where('product_id', $this->rm->id)->sum('suggested_kg'));
        $this->assertSame('1.50', $plan->items->firstWhere('product_id', $this->rm->id)->forecast_extra_kg);
    }

    public function test_leftover_stock_from_a_previous_batch_reduces_the_suggestion(): void
    {
        Batch::factory()->create([
            'code' => 'F2H-261002-RM-NH01', 'farmer_id' => $this->nh01->id, 'product_id' => $this->rm->id, 'quantity_kg' => 2.0,
            'harvested_at' => '2026-10-02 05:30:00', 'best_before' => '2026-10-04', 'quality_status' => 'passed',
        ]);

        $plan = $this->plans->generateDraft($this->date);

        // (7,5 − 2,0) × 1,1 = 6,05 → 6,5
        $this->assertSame(6.5, (float) $plan->items->where('product_id', $this->rm->id)->sum('suggested_kg'));
        $this->assertSame('2.00', $plan->items->firstWhere('product_id', $this->rm->id)->available_stock_kg);
    }

    public function test_expired_or_rejected_batches_are_not_stock(): void
    {
        Batch::factory()->create(['code' => 'F2H-261001-RM-NH01', 'farmer_id' => $this->nh01->id, 'product_id' => $this->rm->id, 'quantity_kg' => 2.0, 'harvested_at' => '2026-10-01 05:30:00', 'best_before' => '2026-10-02', 'quality_status' => 'passed']);
        Batch::factory()->create(['code' => 'F2H-261002-RM-NH02', 'farmer_id' => $this->nh02->id, 'product_id' => $this->rm->id, 'quantity_kg' => 2.0, 'harvested_at' => '2026-10-02 05:30:00', 'best_before' => '2026-10-04', 'quality_status' => 'rejected']);

        $plan = $this->plans->generateDraft($this->date);

        $this->assertSame(8.5, (float) $plan->items->where('product_id', $this->rm->id)->sum('suggested_kg'));
    }

    public function test_regenerating_a_draft_replaces_its_rows(): void
    {
        $first = $this->plans->generateDraft($this->date);
        $this->order([['product_id' => $this->hl->id, 'quantity' => 5]]);
        $second = $this->plans->generateDraft($this->date);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, HarvestPlan::count());
        $this->assertCount(4, $second->items);
    }

    public function test_confirm_is_blocked_while_a_row_has_no_farmer(): void
    {
        // Hành lá: 5 gói = 1,0 kg → 1,1 → 1,5 kg, không ai báo sản lượng.
        $this->order([['product_id' => $this->hl->id, 'quantity' => 5]]);
        $plan = $this->plans->generateDraft($this->date);
        $orphan = $plan->items->firstWhere('product_id', $this->hl->id);

        $this->assertNull($orphan->farmer_id);
        $this->assertSame('1.50', $orphan->suggested_kg);

        try {
            $this->plans->confirm($plan, $this->operator);
            $this->fail('Expected DomainException');
        } catch (DomainException $e) {
            $this->assertSame('Còn dòng chưa có nông hộ. Hãy chọn nông hộ hoặc đặt số chốt về 0.', $e->getMessage());
        }

        $this->assertSame(HarvestPlanStatus::Draft, $plan->refresh()->status);
        $this->assertNull($orphan->refresh()->final_kg);

        $this->plans->updateItem($orphan, 1.5, null, $this->nh01->id);
        $this->assertSame(HarvestPlanStatus::Confirmed, $this->plans->confirm($plan->refresh(), $this->operator)->status);
    }

    public function test_changing_the_suggestion_requires_a_reason(): void
    {
        $item = $this->plans->generateDraft($this->date)->items->firstWhere('product_id', $this->cn->id);

        try {
            $this->plans->updateItem($item, 3.0);
            $this->fail('Expected DomainException');
        } catch (DomainException $e) {
            $this->assertSame('Cần ghi lý do khi số chốt khác số gợi ý.', $e->getMessage());
        }

        $this->plans->updateItem($item, 3.0, 'Cuối tuần, tăng dự phòng');
        $this->assertSame(['3.00', '2.50', 'Cuối tuần, tăng dự phòng'], [$item->final_kg, $item->suggested_kg, $item->adjust_reason]);

        // Trả về đúng số gợi ý thì không cần lý do và lý do cũ bị xóa.
        $this->plans->updateItem($item, 2.5);
        $this->assertNull($item->refresh()->adjust_reason);
    }

    public function test_negative_final_kg_is_rejected(): void
    {
        $item = $this->plans->generateDraft($this->date)->items->first();

        $this->expectException(DomainException::class);

        $this->plans->updateItem($item, -1, 'lỗi nhập');
    }

    public function test_confirm_keeps_suggested_and_fills_final_for_untouched_rows(): void
    {
        $plan = $this->plans->generateDraft($this->date);
        $this->plans->updateItem($plan->items->firstWhere('product_id', $this->cn->id), 2.0, 'Dự báo mưa, giảm lượng thu hoạch');

        $plan = $this->plans->confirm($plan, $this->operator);

        $this->assertSame(HarvestPlanStatus::Confirmed, $plan->status);
        $this->assertSame($this->operator->id, $plan->confirmed_by);
        $this->assertNotNull($plan->confirmed_at);
        $this->assertSame(['4.50', '4.00', '2.00'], $plan->items()->orderBy('id')->pluck('final_kg')->all());
        $this->assertSame(['4.50', '4.00', '2.50'], $plan->items()->orderBy('id')->pluck('suggested_kg')->all());
    }

    public function test_confirmed_plan_cannot_be_regenerated_or_edited(): void
    {
        $plan = $this->plans->confirm($this->plans->generateDraft($this->date), $this->operator);

        try {
            $this->plans->generateDraft($this->date);
            $this->fail('Expected DomainException');
        } catch (DomainException $e) {
            $this->assertSame('Kế hoạch ngày này đã chốt, không thể tạo lại gợi ý.', $e->getMessage());
        }

        $this->expectException(DomainException::class);
        $this->plans->updateItem($plan->items()->first(), 1.0, 'sửa sau chốt');
    }

    public function test_zalo_message_lists_only_that_farmers_rows(): void
    {
        $plan = $this->plans->confirm($this->plans->generateDraft($this->date), $this->operator);

        $message = $this->plans->zaloMessage($plan, $this->nh02);

        $this->assertStringContainsString('Trần Thị Hồng', $message);
        $this->assertStringContainsString('03/10/2026', $message);
        $this->assertStringContainsString('- Rau muống: 4 kg', $message);
        $this->assertStringContainsString('- Cải ngọt: 2,5 kg', $message);
        $this->assertStringNotContainsString('4,5 kg', $message);
        $this->assertStringContainsString('/nong-ho/'.$this->nh02->access_token, $message);
    }

    public function test_sent_farmer_confirmation_and_completion(): void
    {
        $plan = $this->plans->generateDraft($this->date);

        try {
            $this->plans->markSent($plan);
            $this->fail('Draft plans cannot be sent');
        } catch (DomainException) {
        }

        $plan = $this->plans->markSent($this->plans->confirm($plan, $this->operator));
        $this->assertSame(HarvestPlanStatus::Sent, $plan->status);

        $this->assertSame(2, $this->plans->farmerConfirm($plan, $this->nh02));
        $this->assertSame(0, $this->plans->farmerConfirm($plan, $this->nh02));
        $this->assertSame(2, $plan->items()->whereNotNull('farmer_confirmed_at')->count());

        try {
            $this->plans->complete($plan);
            $this->fail('Cannot complete before everything is received');
        } catch (DomainException $e) {
            $this->assertSame('Còn dòng chưa nhập số kg thực nhận.', $e->getMessage());
        }

        $plan->items()->update(['received_kg' => 1]);
        $this->assertSame(HarvestPlanStatus::Completed, $this->plans->complete($plan)->status);
    }

    public function test_farmer_cannot_confirm_a_draft(): void
    {
        $plan = $this->plans->generateDraft($this->date);

        $this->expectException(DomainException::class);

        $this->plans->farmerConfirm($plan, $this->nh01);
    }

    public function test_paused_farmers_are_not_allocated(): void
    {
        $this->nh01->update(['status' => 'paused']);

        $plan = $this->plans->generateDraft($this->date);

        // NH02 báo 7,5 kg rau muống → 7,5; thiếu 1,0 kg thành dòng chưa có nông hộ.
        $this->assertSame([[$this->nh02->id, 7.5, 7.5], [null, 1.0, 0.0]], $this->rows($plan, $this->rm));
    }
}
