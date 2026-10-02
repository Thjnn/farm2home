<?php

namespace Tests\Feature\Batches;

use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Enums\QualityStatus;
use App\Models\Batch;
use App\Models\HarvestPlanItem;
use App\Models\SupplyReport;
use App\Services\HarvestPlanService;
use App\Services\OrderService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsCatalog;
use Tests\TestCase;

class BatchScreensTest extends TestCase
{
    use BuildsCatalog, RefreshDatabase;

    private HarvestPlanItem $item;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-02 19:00:00');
        $this->buildCatalog();

        $order = app(OrderService::class)->place([
            'name' => 'Khách', 'phone' => '0988000001', 'address' => '1 Lê Lợi',
            'delivery_zone_id' => $this->zone->id, 'delivery_date' => '2026-10-03', 'time_slot' => '17:00-19:00',
            'order_type' => 'single', 'items' => [['product_id' => $this->rm->id, 'quantity' => 14]],
            'payment_method' => 'cod', 'data_consent' => true,
        ], OrderChannel::Zalo);
        app(OrderService::class)->transition($order, OrderStatus::Confirmed);
        SupplyReport::factory()->create(['farmer_id' => $this->nh01->id, 'product_id' => $this->rm->id, 'available_date' => '2026-10-03', 'quantity_kg' => 9, 'source' => 'form']);

        $plans = app(HarvestPlanService::class);
        $plan = $plans->confirm($plans->generateDraft(CarbonImmutable::parse('2026-10-03'), $this->operator), $this->operator);
        $this->item = $plan->items->firstOrFail();

        $this->travelTo('2026-10-03 07:00:00');
        $this->actingAs($this->operator);
    }

    private function receive(): Batch
    {
        $this->post('/admin/harvest-plan-items/'.$this->item->id.'/receive', ['received_kg' => '7.8']);

        return Batch::firstOrFail();
    }

    public function test_operator_receives_produce_from_the_plan_screen(): void
    {
        $this->post('/admin/harvest-plan-items/'.$this->item->id.'/receive', ['received_kg' => '7.8'])
            ->assertRedirect('/admin/harvest-plans/'.$this->item->harvest_plan_id);

        $this->assertSame('F2H-261003-RM-NH01', Batch::firstOrFail()->code);
        $this->get('/admin/harvest-plans/'.$this->item->harvest_plan_id)->assertSee('F2H-261003-RM-NH01')->assertSee('7,8 kg');
    }

    public function test_received_kg_is_validated(): void
    {
        $this->post('/admin/harvest-plan-items/'.$this->item->id.'/receive', ['received_kg' => 'abc'])->assertSessionHasErrors('received_kg');
        $this->from('/admin/harvest-plans/'.$this->item->harvest_plan_id)
            ->post('/admin/harvest-plan-items/'.$this->item->id.'/receive', ['received_kg' => '0'])
            ->assertSessionHasErrors('action');
    }

    public function test_batch_list_and_detail(): void
    {
        $batch = $this->receive();

        $this->get('/admin/batches?date=2026-10-03')->assertOk()->assertSee('F2H-261003-RM-NH01')->assertSee('Chờ kiểm');
        $this->get('/admin/batches/'.$batch->id)
            ->assertOk()
            ->assertSee('Nguyễn Văn Tâm')
            ->assertSee('7,8 kg')
            ->assertSee('In tem QR');
    }

    public function test_operator_records_processing_times_and_passes_the_batch(): void
    {
        $batch = $this->receive();

        $this->patch('/admin/batches/'.$batch->id, [
            'quality_status' => 'passed', 'qc_note' => '',
            'processed_at' => '2026-10-03T08:30', 'packed_at' => '2026-10-03T10:00',
            'processing_note' => 'Nhặt lá úa, rửa sơ, để ráo',
        ])->assertRedirect('/admin/batches/'.$batch->id);

        $batch->refresh();
        $this->assertSame(QualityStatus::Passed, $batch->quality_status);
        $this->assertSame('2026-10-03 10:00', $batch->packed_at->format('Y-m-d H:i'));
        $this->assertSame('Nhặt lá úa, rửa sơ, để ráo', $batch->processing_note);
    }

    public function test_allocate_button_assigns_batches_and_reports_shortage(): void
    {
        $batch = $this->receive();
        $batch->update(['quality_status' => 'passed', 'quantity_kg' => 5]);

        $this->post('/admin/batches/allocate', ['date' => '2026-10-03'])
            ->assertRedirect()
            ->assertSessionHas('status', 'Đã gán 10 gói. Còn thiếu 4 gói.');
    }

    public function test_label_sheet_prints_one_qr_per_label_with_the_public_url(): void
    {
        $batch = $this->receive();
        $batch->update(['quality_status' => 'passed', 'packed_at' => '2026-10-03 10:00:00']);

        $response = $this->get('/admin/batches/'.$batch->id.'/labels?count=6')->assertOk();

        $this->assertSame(6, substr_count($response->getContent(), '<svg'));
        $response->assertSee('F2H-261003-RM-NH01')->assertSee('Rau muống')->assertSee('05/10/2026');
    }

    public function test_labels_are_refused_for_a_batch_that_did_not_pass(): void
    {
        $batch = $this->receive();

        // Trang tem mở ở tab mới nên không có trang trước để quay lại: phải về trang lô.
        $this->get('/admin/batches/'.$batch->id.'/labels')
            ->assertRedirect('/admin/batches/'.$batch->id)
            ->assertSessionHasErrors('action');
    }
}
