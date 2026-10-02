<?php

namespace Tests\Feature\Batches;

use App\Enums\OrderChannel;
use App\Enums\QualityStatus;
use App\Models\Batch;
use App\Models\HarvestPlanItem;
use App\Models\SupplyReport;
use App\Services\BatchService;
use App\Services\HarvestPlanService;
use App\Services\OrderService;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsCatalog;
use Tests\TestCase;

class BatchReceiveTest extends TestCase
{
    use BuildsCatalog, RefreshDatabase;

    private BatchService $batches;

    private HarvestPlanItem $item;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-02 19:00:00');
        $this->buildCatalog();
        $this->batches = app(BatchService::class);

        app(OrderService::class)->place([
            'name' => 'Khách', 'phone' => '0988000001', 'address' => '1 Lê Lợi',
            'delivery_zone_id' => $this->zone->id, 'delivery_date' => '2026-10-03', 'time_slot' => '17:00-19:00',
            'order_type' => 'single', 'items' => [['product_id' => $this->rm->id, 'quantity' => 14]],
            'payment_method' => 'cod', 'data_consent' => true,
        ], OrderChannel::Zalo);
        SupplyReport::factory()->create(['farmer_id' => $this->nh01->id, 'product_id' => $this->rm->id, 'available_date' => '2026-10-03', 'quantity_kg' => 9, 'source' => 'form']);

        $plans = app(HarvestPlanService::class);
        $plan = $plans->generateDraft(CarbonImmutable::parse('2026-10-03'), $this->operator);
        $this->item = $plan->items->firstOrFail();
    }

    private function confirmPlan(): void
    {
        app(HarvestPlanService::class)->confirm($this->item->plan, $this->operator);
        $this->item->refresh();
        $this->travelTo('2026-10-03 07:00:00');
    }

    public function test_receiving_creates_a_batch_with_code_and_shelf_life(): void
    {
        $this->confirmPlan();

        $batch = $this->batches->receive($this->item, 7.8)->refresh();

        $this->assertSame('F2H-261003-RM-NH01', $batch->code);
        $this->assertSame('7.80', $batch->quantity_kg);
        $this->assertSame('2026-10-03 05:30', $batch->harvested_at->format('Y-m-d H:i'));
        $this->assertSame('2026-10-03 07:00', $batch->received_at->format('Y-m-d H:i'));
        $this->assertSame('2026-10-05', $batch->best_before->toDateString());
        $this->assertSame('Bảo quản ngăn mát 4–8°C.', $batch->storage_guide);
        $this->assertSame(QualityStatus::Pending, $batch->quality_status);
        $this->assertSame($this->item->id, $batch->harvest_plan_item_id);
        $this->assertSame('7.80', $this->item->refresh()->received_kg);
    }

    public function test_receiving_again_corrects_the_quantity_instead_of_duplicating(): void
    {
        $this->confirmPlan();

        $this->batches->receive($this->item, 7.8);
        $this->batches->receive($this->item, 8.1);

        $this->assertSame(1, Batch::count());
        $this->assertSame('8.10', Batch::firstOrFail()->quantity_kg);
        $this->assertSame('8.10', $this->item->refresh()->received_kg);
    }

    public function test_cannot_receive_for_a_draft_plan(): void
    {
        try {
            $this->batches->receive($this->item, 7.8);
            $this->fail('Expected DomainException');
        } catch (DomainException $e) {
            $this->assertSame('Chỉ nhận hàng cho kế hoạch đã chốt.', $e->getMessage());
        }

        $this->assertSame(0, Batch::count());
    }

    public function test_quantity_must_be_positive_and_row_must_have_a_farmer(): void
    {
        $this->confirmPlan();

        try {
            $this->batches->receive($this->item, 0);
            $this->fail('Expected DomainException');
        } catch (DomainException $e) {
            $this->assertSame('Khối lượng nhận phải lớn hơn 0.', $e->getMessage());
        }

        $this->item->update(['farmer_id' => null]);

        $this->expectException(DomainException::class);
        $this->batches->receive($this->item->refresh(), 5);
    }

    public function test_quality_check_passes_or_rejects_a_batch(): void
    {
        $this->confirmPlan();
        $batch = $this->batches->receive($this->item, 7.8);

        $this->batches->setQuality($batch, QualityStatus::Rejected, 'Lá úa nhiều');

        $this->assertSame([QualityStatus::Rejected, 'Lá úa nhiều'], [$batch->refresh()->quality_status, $batch->qc_note]);
    }
}
