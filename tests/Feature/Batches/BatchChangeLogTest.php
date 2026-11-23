<?php

namespace Tests\Feature\Batches;

use App\Enums\OrderChannel;
use App\Models\Batch;
use App\Models\BatchChange;
use App\Models\HarvestPlanItem;
use App\Models\SupplyReport;
use App\Services\BatchService;
use App\Services\HarvestPlanService;
use App\Services\OrderService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsCatalog;
use Tests\TestCase;

class BatchChangeLogTest extends TestCase
{
    use BuildsCatalog, RefreshDatabase;

    private HarvestPlanItem $item;

    private Batch $batch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-02 19:00:00');
        $this->buildCatalog();

        app(OrderService::class)->place([
            'name' => 'Khách', 'phone' => '0988000001', 'address' => '1 Lê Lợi',
            'delivery_zone_id' => $this->zone->id, 'delivery_date' => '2026-10-03', 'time_slot' => '17:00-19:00',
            'order_type' => 'single', 'items' => [['product_id' => $this->rm->id, 'quantity' => 14]],
            'payment_method' => 'cod', 'data_consent' => true,
        ], OrderChannel::Zalo);
        SupplyReport::factory()->create(['farmer_id' => $this->nh01->id, 'product_id' => $this->rm->id, 'available_date' => '2026-10-03', 'quantity_kg' => 9, 'source' => 'form']);

        $plans = app(HarvestPlanService::class);
        $plan = $plans->generateDraft(CarbonImmutable::parse('2026-10-03'), $this->operator);
        $plans->confirm($plan, $this->operator);
        $this->item = $plan->items->firstOrFail()->refresh();
        $this->travelTo('2026-10-03 07:00:00');

        $this->batch = app(BatchService::class)->receive($this->item, 7.8)->refresh();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function form(array $overrides = []): array
    {
        return [
            'quality_status' => $this->batch->quality_status->value,
            'qc_note' => $this->batch->qc_note,
            'processed_at' => $this->batch->processed_at?->format('Y-m-d\TH:i'),
            'packed_at' => $this->batch->packed_at?->format('Y-m-d\TH:i'),
            'processing_note' => $this->batch->processing_note,
            ...$overrides,
        ];
    }

    public function test_editing_a_batch_records_who_changed_what(): void
    {
        $this->actingAs($this->admin)->patch('/admin/batches/'.$this->batch->id, $this->form([
            'processing_note' => 'Rửa 2 lần',
            'quality_status' => 'passed',
        ]))->assertRedirect();

        $this->assertDatabaseCount('batch_changes', 1);
        $change = BatchChange::firstOrFail();
        $this->assertSame([null, 'Rửa 2 lần'], $change->changes['processing_note']);
        $this->assertSame(['pending', 'passed'], $change->changes['quality_status']);
        $this->assertSame($this->admin->id, $change->user_id);
    }

    public function test_saving_without_changes_records_nothing(): void
    {
        $this->actingAs($this->admin)->patch('/admin/batches/'.$this->batch->id, $this->form())->assertRedirect();

        $this->assertDatabaseCount('batch_changes', 0);
    }

    public function test_creating_a_batch_is_not_a_change_but_receiving_again_is(): void
    {
        $this->assertDatabaseCount('batch_changes', 0);

        app(BatchService::class)->receive($this->item, 8.5);

        $this->assertDatabaseCount('batch_changes', 1);
        $change = BatchChange::firstOrFail();
        $this->assertArrayHasKey('quantity_kg', $change->changes);
        $this->assertNull($change->user_id);
    }

    public function test_admin_page_lists_changes_and_public_page_shows_only_count(): void
    {
        $this->actingAs($this->admin)->patch('/admin/batches/'.$this->batch->id, $this->form(['quality_status' => 'passed']));
        $this->batch->refresh();
        $this->actingAs($this->admin)->patch('/admin/batches/'.$this->batch->id, $this->form(['processing_note' => 'Rửa 2 lần']));

        $this->get('/admin/batches/'.$this->batch->id)->assertOk()->assertSee('Lịch sử sửa')->assertSee($this->admin->name)->assertSee('Rửa 2 lần');

        auth()->logout();
        $this->get($this->batch->refresh()->publicUrl())->assertOk()->assertSee('đã sửa 2 lần')->assertDontSee($this->admin->name);
    }
}
