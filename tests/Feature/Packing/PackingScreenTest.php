<?php

namespace Tests\Feature\Packing;

use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Models\Batch;
use App\Models\HarvestPlan;
use App\Models\Order;
use App\Models\Product;
use App\Services\BatchService;
use App\Services\OrderService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\BuildsCatalog;
use Tests\TestCase;

class PackingScreenTest extends TestCase
{
    use BuildsCatalog, RefreshDatabase;

    private HarvestPlan $plan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-03 07:00:00');
        $this->buildCatalog();
        $this->plan = HarvestPlan::factory()->create(['delivery_date' => '2026-10-03', 'harvest_date' => '2026-10-03', 'status' => 'confirmed']);
    }

    /**
     * @param  array<int, array{product_id: int, quantity: int}>  $items
     */
    private function order(string $phone, array $items): Order
    {
        $order = app(OrderService::class)->place([
            'name' => 'Khách '.$phone, 'phone' => $phone, 'address' => '1 Lê Lợi',
            'delivery_zone_id' => $this->zone->id, 'delivery_date' => '2026-10-03', 'time_slot' => '17:00-19:00',
            'order_type' => 'single', 'items' => $items, 'payment_method' => 'cod', 'data_consent' => true,
        ], OrderChannel::Zalo);

        return app(OrderService::class)->transition($order, OrderStatus::Confirmed);
    }

    private function batch(Product $product, string $code, float $kg = 5.0): Batch
    {
        return Batch::factory()->create([
            'code' => $code, 'farmer_id' => $this->nh01->id, 'product_id' => $product->id, 'quantity_kg' => $kg,
            'harvested_at' => '2026-10-03 05:30:00', 'best_before' => '2026-10-05', 'quality_status' => 'passed',
        ]);
    }

    private function allocate(): void
    {
        app(BatchService::class)->allocate(CarbonImmutable::parse('2026-10-03'));
    }

    private function packedAt(Batch $batch): ?string
    {
        return DB::table('order_item_batches')->where('batch_id', $batch->id)->value('packed_at');
    }

    public function test_ticking_the_first_bag_moves_the_order_to_packing(): void
    {
        $order = $this->order('0988000001', [['product_id' => $this->rm->id, 'quantity' => 2]]);
        $batch = $this->batch($this->rm, 'F2H-261003-RM-NH01');
        $this->allocate();

        $this->actingAs($this->operator)
            ->patch(route('admin.packing.bags.toggle', [$order, $batch]), ['packed' => 1])
            ->assertRedirect(route('admin.packing.index', ['date' => '2026-10-03']).'#order-'.$order->id);

        $this->assertSame(OrderStatus::Packing, $order->fresh()->status);
        $this->assertNotNull($this->packedAt($batch));
    }

    public function test_unticking_clears_the_bag_without_moving_the_order_back(): void
    {
        $order = $this->order('0988000001', [['product_id' => $this->rm->id, 'quantity' => 2]]);
        $batch = $this->batch($this->rm, 'F2H-261003-RM-NH01');
        $this->allocate();
        $this->actingAs($this->operator)->patch(route('admin.packing.bags.toggle', [$order, $batch]), ['packed' => 1]);

        $this->actingAs($this->operator)->patch(route('admin.packing.bags.toggle', [$order, $batch]), ['packed' => 0]);

        $this->assertNull($this->packedAt($batch));
        $this->assertSame(OrderStatus::Packing, $order->fresh()->status);
    }

    public function test_ticking_needs_a_confirmed_harvest_plan_and_writes_nothing_otherwise(): void
    {
        $order = $this->order('0988000001', [['product_id' => $this->rm->id, 'quantity' => 2]]);
        $batch = $this->batch($this->rm, 'F2H-261003-RM-NH01');
        $this->allocate();
        $this->plan->update(['status' => 'draft']);

        $this->actingAs($this->operator)
            ->patch(route('admin.packing.bags.toggle', [$order, $batch]), ['packed' => 1])
            ->assertSessionHasErrors('action');

        $this->assertNull($this->packedAt($batch));
        $this->assertSame(OrderStatus::Confirmed, $order->fresh()->status);
    }

    public function test_a_batch_outside_the_order_cannot_be_ticked(): void
    {
        $order = $this->order('0988000001', [['product_id' => $this->rm->id, 'quantity' => 2]]);
        $other = $this->order('0988000002', [['product_id' => $this->cn->id, 'quantity' => 1]]);
        $this->batch($this->rm, 'F2H-261003-RM-NH01');
        $cn = $this->batch($this->cn, 'F2H-261003-CN-NH01');
        $this->allocate();

        $this->actingAs($this->operator)
            ->patch(route('admin.packing.bags.toggle', [$order, $cn]), ['packed' => 1])
            ->assertNotFound();

        $this->assertNull($this->packedAt($cn));
        $this->assertSame(OrderStatus::Confirmed, $other->fresh()->status);
    }

    public function test_a_cancelled_order_cannot_be_ticked(): void
    {
        $order = $this->order('0988000001', [['product_id' => $this->rm->id, 'quantity' => 2]]);
        $batch = $this->batch($this->rm, 'F2H-261003-RM-NH01');
        $this->allocate();
        app(OrderService::class)->transition($order, OrderStatus::Cancelled, 'Khách đổi ý');

        $this->actingAs($this->operator)
            ->patch(route('admin.packing.bags.toggle', [$order, $batch]), ['packed' => 1])
            ->assertNotFound();
    }

    public function test_reallocating_keeps_bags_already_packed(): void
    {
        $order = $this->order('0988000001', [['product_id' => $this->rm->id, 'quantity' => 2]]);
        $batch = $this->batch($this->rm, 'F2H-261003-RM-NH01');
        $this->allocate();
        $this->actingAs($this->operator)->patch(route('admin.packing.bags.toggle', [$order, $batch]), ['packed' => 1]);

        $this->allocate();

        $this->assertNotNull($this->packedAt($batch));
    }

    public function test_pack_whole_order_marks_every_allocated_bag(): void
    {
        $order = $this->order('0988000001', [['product_id' => $this->rm->id, 'quantity' => 2], ['product_id' => $this->cn->id, 'quantity' => 1]]);
        $rm = $this->batch($this->rm, 'F2H-261003-RM-NH01');
        $cn = $this->batch($this->cn, 'F2H-261003-CN-NH01');
        $this->allocate();

        $this->actingAs($this->operator)
            ->patch(route('admin.packing.orders.pack', $order))
            ->assertRedirect();

        $this->assertNotNull($this->packedAt($rm));
        $this->assertNotNull($this->packedAt($cn));
        $this->assertSame(OrderStatus::Packing, $order->fresh()->status);
    }

    public function test_guests_cannot_tick_bags(): void
    {
        $order = $this->order('0988000001', [['product_id' => $this->rm->id, 'quantity' => 2]]);
        $batch = $this->batch($this->rm, 'F2H-261003-RM-NH01');

        $this->patch(route('admin.packing.bags.toggle', [$order, $batch]), ['packed' => 1])->assertRedirect(route('login'));
    }

    public function test_packing_screen_shows_pick_list_groups_and_bags(): void
    {
        $order = $this->order('0988000001', [['product_id' => $this->rm->id, 'quantity' => 2], ['product_id' => $this->cn->id, 'quantity' => 1]]);
        $batch = $this->batch($this->rm, 'F2H-261003-RM-NH01');
        $this->allocate();

        $this->actingAs($this->operator)->get(route('admin.packing.index', ['date' => '2026-10-03']))
            ->assertOk()
            ->assertSeeInOrder(['Tổng soạn theo rau', $this->rm->name, $this->zone->name, $order->code, 'Túi 1', $batch->code, 'Thiếu']);
    }

    public function test_packing_screen_marks_fully_packed_orders(): void
    {
        $order = $this->order('0988000001', [['product_id' => $this->rm->id, 'quantity' => 2]]);
        $this->batch($this->rm, 'F2H-261003-RM-NH01');
        $this->allocate();
        $this->actingAs($this->operator)->patch(route('admin.packing.orders.pack', $order));

        $this->actingAs($this->operator)->get(route('admin.packing.index', ['date' => '2026-10-03']))
            ->assertOk()->assertSee('Đủ túi');
    }

    public function test_packing_screen_warns_when_nothing_is_allocated_yet(): void
    {
        $this->order('0988000001', [['product_id' => $this->rm->id, 'quantity' => 2]]);

        $this->actingAs($this->operator)->get(route('admin.packing.index', ['date' => '2026-10-03']))
            ->assertOk()->assertSee('Chưa gán lô');
    }

    public function test_packing_screen_is_empty_without_confirmed_orders(): void
    {
        $this->actingAs($this->operator)->get(route('admin.packing.index', ['date' => '2026-10-03']))
            ->assertOk()->assertSee('Chưa có đơn đã xác nhận cho ngày này');
    }

    public function test_slips_print_one_slip_per_bag_with_batch_qr_and_no_phone(): void
    {
        $order = $this->order('0988000001', [['product_id' => $this->rm->id, 'quantity' => 2], ['product_id' => $this->cn->id, 'quantity' => 1]]);
        $this->batch($this->rm, 'F2H-261003-RM-NH01');
        $this->batch($this->cn, 'F2H-261003-CN-NH01');
        $this->allocate();
        $order->customer->update(['name' => 'Chị Lan']);

        $this->actingAs($this->operator)->get(route('admin.packing.slips', ['order' => $order->id]))
            ->assertOk()->assertSee('Túi 1/2')->assertSee('Túi 2/2')->assertSee('<svg', false)
            ->assertSee('Chị Lan')->assertDontSee($order->customer->phone)->assertDontSee('1 Lê Lợi');
    }

    public function test_short_bags_are_not_printed(): void
    {
        $order = $this->order('0988000001', [['product_id' => $this->rm->id, 'quantity' => 2], ['product_id' => $this->cn->id, 'quantity' => 1]]);
        $this->batch($this->rm, 'F2H-261003-RM-NH01');
        $this->allocate();

        $this->actingAs($this->operator)->get(route('admin.packing.slips', ['date' => '2026-10-03']))
            ->assertOk()->assertSee('Túi 1/1')->assertDontSee($this->cn->name);
    }

    public function test_guests_cannot_open_the_packing_screen(): void
    {
        $this->get(route('admin.packing.index'))->assertRedirect(route('login'));
    }

    public function test_dashboard_counts_today_orders_not_fully_packed(): void
    {
        $this->order('0988000001', [['product_id' => $this->rm->id, 'quantity' => 2]]);

        $this->actingAs($this->operator)->get(route('admin.dashboard'))
            ->assertOk()->assertSee('data-task="packing"', false)->assertSee('Đơn chưa đóng xong');
    }

    public function test_bulk_delivering_explains_orders_skipped_for_unpacked_bags(): void
    {
        $order = $this->order('0988000001', [['product_id' => $this->rm->id, 'quantity' => 2]]);
        $this->batch($this->rm, 'F2H-261003-RM-NH01');
        $this->allocate();
        app(OrderService::class)->transition($order, OrderStatus::Packing);

        $this->actingAs($this->operator)
            ->patch('/admin/orders/bulk-status', ['status' => 'delivering', 'order_ids' => [$order->id]])
            ->assertSessionHas('status', 'Đã chuyển 0 đơn sang "Đang giao". Bỏ qua 1 đơn vì kế hoạch thu hoạch chưa chốt hoặc còn túi chưa đóng.');
    }
}
