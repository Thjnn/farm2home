<?php

namespace Tests\Feature\Waste;

use App\Enums\OrderChannel;
use App\Enums\WasteReason;
use App\Models\Batch;
use App\Models\PackagingReturn;
use App\Models\WasteLog;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsCatalog;
use Tests\TestCase;

class WasteAndPackagingTest extends TestCase
{
    use BuildsCatalog, RefreshDatabase;

    private Batch $batch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-03 20:00:00');
        $this->buildCatalog();
        $this->batch = Batch::factory()->create([
            'code' => 'F2H-261003-RM-NH01', 'farmer_id' => $this->nh01->id, 'product_id' => $this->rm->id, 'quantity_kg' => 5,
            'harvested_at' => '2026-10-03 05:30:00', 'best_before' => '2026-10-05', 'quality_status' => 'passed',
        ]);
        $this->actingAs($this->operator);
    }

    public function test_close_day_screen_lists_batches_with_leftover(): void
    {
        $this->get('/admin/waste?date=2026-10-03')
            ->assertOk()
            ->assertSee('F2H-261003-RM-NH01')
            ->assertSee('5 kg')
            ->assertSee('Dư thừa')
            ->assertSee('Bán giảm giá');
    }

    public function test_operator_logs_surplus_for_a_batch(): void
    {
        $this->post('/admin/waste', ['batch_id' => $this->batch->id, 'quantity_kg' => '2', 'reason' => 'surplus', 'note' => 'Không bán hết'])
            ->assertRedirect()
            ->assertSessionHas('status');

        $log = WasteLog::firstOrFail();
        $this->assertSame(['2.00', WasteReason::Surplus, $this->operator->id, $this->rm->id], [$log->quantity_kg, $log->reason, $log->created_by, $log->product_id]);
        $this->get('/admin/waste?date=2026-10-03')->assertSee('3 kg')->assertSee('Không bán hết');
    }

    public function test_cannot_log_more_than_what_is_left(): void
    {
        $this->from('/admin/waste')
            ->post('/admin/waste', ['batch_id' => $this->batch->id, 'quantity_kg' => '6', 'reason' => 'surplus'])
            ->assertSessionHasErrors('action');

        $this->assertSame(0, WasteLog::count());
    }

    public function test_reason_must_be_a_known_value(): void
    {
        $this->post('/admin/waste', ['batch_id' => $this->batch->id, 'quantity_kg' => '1', 'reason' => 'stolen'])
            ->assertSessionHasErrors('reason');
    }

    public function test_packaging_return_is_linked_to_customer_and_order_when_known(): void
    {
        $order = app(OrderService::class)->place([
            'name' => 'Nguyễn Thị Lan', 'phone' => '0988000001', 'address' => '1 Lê Lợi',
            'delivery_zone_id' => $this->zone->id, 'delivery_date' => '2026-10-03', 'time_slot' => '17:00-19:00',
            'order_type' => 'single', 'items' => [['product_id' => $this->rm->id, 'quantity' => 2]],
            'payment_method' => 'cod', 'data_consent' => true,
        ], OrderChannel::Zalo);

        $this->post('/admin/packaging-returns', [
            'return_date' => '2026-10-03', 'phone' => '0988000001', 'order_code' => $order->code,
            'quantity_returned' => 2, 'quantity_reused' => 1,
        ])->assertRedirect();

        $row = PackagingReturn::firstOrFail();
        $this->assertSame([$order->customer_id, $order->id, 2, 1], [$row->customer_id, $row->order_id, $row->quantity_returned, $row->quantity_reused]);
        $this->get('/admin/packaging-returns')->assertSee('Nguyễn Thị Lan')->assertSee($order->code);
    }

    public function test_reused_cannot_exceed_returned(): void
    {
        $this->post('/admin/packaging-returns', ['return_date' => '2026-10-03', 'quantity_returned' => 1, 'quantity_reused' => 2])
            ->assertSessionHasErrors('quantity_reused');
    }

    public function test_packaging_return_without_customer_is_allowed(): void
    {
        $this->post('/admin/packaging-returns', ['return_date' => '2026-10-03', 'quantity_returned' => 3, 'quantity_reused' => 3])->assertRedirect();

        $this->assertNull(PackagingReturn::firstOrFail()->customer_id);
    }
}
