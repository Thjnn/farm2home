<?php

namespace Tests\Feature\Shipper;

use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Batch;
use App\Models\HarvestPlan;
use App\Models\Order;
use App\Models\User;
use App\Services\BatchService;
use App\Services\OrderService;
use App\Services\PackingService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsCatalog;
use Tests\TestCase;

class ShipperScreenTest extends TestCase
{
    use BuildsCatalog, RefreshDatabase;

    private User $binh;

    private User $an;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-03 07:00:00');
        $this->buildCatalog();
        $this->binh = User::factory()->create(['name' => 'Bình', 'role' => UserRole::Shipper]);
        $this->an = User::factory()->create(['name' => 'An', 'role' => UserRole::Shipper, 'off_date' => '2026-10-03']);
        HarvestPlan::factory()->create(['delivery_date' => '2026-10-03', 'harvest_date' => '2026-10-03', 'status' => 'confirmed']);
        Batch::factory()->create([
            'code' => 'F2H-261003-RM-NH01', 'farmer_id' => $this->nh01->id, 'product_id' => $this->rm->id, 'quantity_kg' => 10,
            'harvested_at' => '2026-10-03 05:30:00', 'best_before' => '2026-10-05', 'quality_status' => 'passed',
        ]);
    }

    /** Đơn đang đóng gói, đã chia cho Bình (An nghỉ hôm nay); $packAll = đã tick đủ túi. */
    private function packedOrder(string $phone, string $payment = 'cod', bool $packAll = true): Order
    {
        $orders = app(OrderService::class);
        $order = $orders->place([
            'name' => 'Chị Lan', 'phone' => $phone, 'address' => '12 Lê Lợi',
            'delivery_zone_id' => $this->zone->id, 'delivery_date' => '2026-10-03', 'time_slot' => '17:00-19:00',
            'order_type' => 'single', 'items' => [['product_id' => $this->rm->id, 'quantity' => 2]],
            'payment_method' => $payment, 'data_consent' => true,
        ], OrderChannel::Zalo);
        $orders->transition($order, OrderStatus::Confirmed);
        app(BatchService::class)->allocate(CarbonImmutable::parse('2026-10-03'));
        $order = $order->fresh();
        $packAll ? app(PackingService::class)->markOrder($order) : $orders->transition($order, OrderStatus::Packing);

        return $order->fresh();
    }

    public function test_shipper_sees_only_their_orders_with_call_map_and_amount(): void
    {
        $cod = $this->packedOrder('0988000001');
        $bank = $this->packedOrder('0988000002', 'bank_transfer');
        $other = Order::factory()->create(['delivery_date' => '2026-10-03', 'status' => 'packing', 'shipper_id' => $this->an->id]);

        $this->actingAs($this->binh)->get(route('shipper.index', ['date' => '2026-10-03']))
            ->assertOk()
            ->assertSee($cod->code)->assertSee($bank->code)->assertDontSee($other->code)
            ->assertSee('12 Lê Lợi')
            ->assertSee('href="tel:0988000001"', false)
            ->assertSee('https://www.google.com/maps/search/?api=1&amp;query=', false)
            ->assertSee('Thu tiền: '.number_format($cod->total, 0, ',', '.').'đ')
            ->assertSee('Đã chuyển khoản');
    }

    public function test_pick_up_moves_fully_packed_orders_out_for_delivery(): void
    {
        $ready = $this->packedOrder('0988000001');
        $notReady = $this->packedOrder('0988000002', packAll: false);

        $this->actingAs($this->binh)->post(route('shipper.pickup'), ['date' => '2026-10-03'])
            ->assertRedirect(route('shipper.index', ['date' => '2026-10-03']))
            ->assertSessionHas('status', 'Đã nhận 1 đơn đi giao. 1 đơn chưa đủ túi, chưa nhận.');

        $this->assertSame(OrderStatus::Delivering, $ready->fresh()->status);
        $this->assertSame(OrderStatus::Packing, $notReady->fresh()->status);
    }

    public function test_delivered_marks_order_done_and_cod_paid(): void
    {
        $order = $this->packedOrder('0988000001');
        app(OrderService::class)->transition($order, OrderStatus::Delivering);

        $this->actingAs($this->binh)->patch(route('shipper.delivered', $order))->assertRedirect();

        $this->assertSame(OrderStatus::Delivered, $order->fresh()->status);
        $this->assertSame(PaymentStatus::Paid, $order->fresh()->payment_status);
    }

    public function test_failed_delivery_records_reason_and_keeps_status(): void
    {
        $order = $this->packedOrder('0988000001');
        app(OrderService::class)->transition($order, OrderStatus::Delivering);

        $this->actingAs($this->binh)->patch(route('shipper.issue', $order), ['reason' => 'Không có nhà'])->assertRedirect();
        $this->assertSame('Không có nhà', $order->fresh()->delivery_issue);
        $this->assertNotNull($order->fresh()->delivery_issue_at);
        $this->assertSame(OrderStatus::Delivering, $order->fresh()->status);

        $this->actingAs($this->binh)->patch(route('shipper.issue', $order), ['reason' => 'Khác', 'note' => 'Hẹn giao sáng mai'])->assertRedirect();
        $this->assertSame('Khác: Hẹn giao sáng mai', $order->fresh()->delivery_issue);

        $this->actingAs($this->binh)->get(route('shipper.index', ['date' => '2026-10-03']))->assertSee('Khác: Hẹn giao sáng mai');
    }

    public function test_failed_delivery_needs_a_listed_reason(): void
    {
        $order = $this->packedOrder('0988000001');
        app(OrderService::class)->transition($order, OrderStatus::Delivering);

        $this->actingAs($this->binh)->patch(route('shipper.issue', $order), ['reason' => 'Mưa'])->assertSessionHasErrors('reason');
        $this->assertNull($order->fresh()->delivery_issue);
    }

    public function test_another_shippers_order_is_not_found(): void
    {
        $other = Order::factory()->create(['delivery_date' => '2026-10-03', 'status' => 'delivering', 'shipper_id' => $this->an->id]);

        $this->actingAs($this->binh)->patch(route('shipper.delivered', $other))->assertNotFound();
        $this->actingAs($this->binh)->patch(route('shipper.issue', $other), ['reason' => 'Không có nhà'])->assertNotFound();
        $this->assertSame(OrderStatus::Delivering, $other->fresh()->status);
    }

    public function test_order_still_packing_cannot_be_marked_delivered(): void
    {
        $order = $this->packedOrder('0988000001');

        $this->actingAs($this->binh)->patch(route('shipper.delivered', $order))->assertSessionHasErrors('action');
        $this->assertSame(OrderStatus::Packing, $order->fresh()->status);
    }

    public function test_shipper_off_today_still_sees_orders_already_out_for_delivery(): void
    {
        $order = Order::factory()->create(['delivery_date' => '2026-10-03', 'status' => 'delivering', 'shipper_id' => $this->an->id]);

        $this->actingAs($this->an)->get(route('shipper.index', ['date' => '2026-10-03']))->assertOk()->assertSee($order->code);
    }
}
