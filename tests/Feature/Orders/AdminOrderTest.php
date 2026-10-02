<?php

namespace Tests\Feature\Orders;

use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsCatalog;
use Tests\TestCase;

class AdminOrderTest extends TestCase
{
    use BuildsCatalog, RefreshDatabase;

    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-02 10:00:00');
        $this->buildCatalog();
        $this->order = app(OrderService::class)->place([
            'name' => 'Nguyễn Thị Lan', 'phone' => '0988000001', 'address' => '129 Rạch Gầm',
            'delivery_zone_id' => $this->zone->id, 'delivery_date' => '2026-10-03', 'time_slot' => '17:00-19:00',
            'order_type' => 'single', 'items' => [['product_id' => $this->rm->id, 'quantity' => 2]],
            'payment_method' => 'cod', 'data_consent' => true,
        ], OrderChannel::Website);
    }

    public function test_guests_cannot_see_orders(): void
    {
        $this->get('/admin/orders')->assertRedirect('/login');
    }

    public function test_index_filters_by_delivery_date_and_status(): void
    {
        $this->actingAs($this->operator);

        $this->get('/admin/orders?delivery_date=2026-10-03')
            ->assertOk()
            ->assertSee('DH261003-001')
            ->assertSee('Nguyễn Thị Lan')
            ->assertSee('1 kg');

        $this->get('/admin/orders?delivery_date=2026-10-04')->assertOk()->assertDontSee('DH261003-001');
        $this->get('/admin/orders?delivery_date=2026-10-03&status=delivered')->assertOk()->assertDontSee('DH261003-001');
    }

    public function test_show_lists_items_and_allowed_next_steps(): void
    {
        $this->actingAs($this->operator)->get('/admin/orders/'.$this->order->id)
            ->assertOk()
            ->assertSee('Rau muống')
            ->assertSee('0988000001')
            ->assertSee('129 Rạch Gầm')
            ->assertSee('34.000đ')
            ->assertSee('Đã xác nhận')
            ->assertSee('Đã hủy');
    }

    public function test_operator_confirms_an_order(): void
    {
        $this->actingAs($this->operator)
            ->patch('/admin/orders/'.$this->order->id.'/status', ['status' => 'confirmed'])
            ->assertRedirect('/admin/orders/'.$this->order->id)
            ->assertSessionHas('status');

        $this->assertSame(OrderStatus::Confirmed, $this->order->refresh()->status);
    }

    public function test_invalid_transition_shows_the_domain_error(): void
    {
        $this->actingAs($this->operator)
            ->from('/admin/orders/'.$this->order->id)
            ->patch('/admin/orders/'.$this->order->id.'/status', ['status' => 'delivered'])
            ->assertRedirect('/admin/orders/'.$this->order->id)
            ->assertSessionHasErrors('action');

        $this->assertSame(OrderStatus::Pending, $this->order->refresh()->status);
    }

    public function test_cancel_needs_a_reason(): void
    {
        $this->actingAs($this->operator)
            ->from('/admin/orders/'.$this->order->id)
            ->patch('/admin/orders/'.$this->order->id.'/status', ['status' => 'cancelled'])
            ->assertSessionHasErrors('action');

        $this->actingAs($this->operator)
            ->patch('/admin/orders/'.$this->order->id.'/status', ['status' => 'cancelled', 'cancel_reason' => 'Khách đổi lịch']);

        $this->assertSame(OrderStatus::Cancelled, $this->order->refresh()->status);
    }

    public function test_operator_can_enter_a_zalo_order_after_cutoff_without_consent_box(): void
    {
        $this->travelTo('2026-10-02 20:00:00');

        $this->actingAs($this->operator)->post('/admin/orders', [
            'name' => 'Trần Văn Bình', 'phone' => '0988000009', 'address' => '5 Lê Lợi',
            'delivery_zone_id' => $this->zone->id, 'delivery_date' => '2026-10-03', 'time_slot' => '06:00-08:00',
            'order_type' => 'basket', 'basket_id' => $this->basket->id,
            'payment_method' => 'bank_transfer', 'channel' => 'zalo',
        ])->assertRedirect();

        $order = Order::where('code', 'DH261003-002')->firstOrFail();
        $this->assertSame(OrderChannel::Zalo, $order->channel);
        $this->assertFalse($order->customer->data_consent);
    }

    public function test_admin_order_cannot_be_dated_in_the_past(): void
    {
        $this->actingAs($this->operator)->post('/admin/orders', [
            'name' => 'Trần Văn Bình', 'phone' => '0988000009', 'address' => '5 Lê Lợi',
            'delivery_zone_id' => $this->zone->id, 'delivery_date' => '2026-10-01', 'time_slot' => '06:00-08:00',
            'order_type' => 'basket', 'basket_id' => $this->basket->id,
            'payment_method' => 'cod', 'channel' => 'phone',
        ])->assertSessionHasErrors('delivery_date');
    }

    public function test_a_quantity_box_left_blank_counts_as_zero_on_the_admin_form(): void
    {
        $this->actingAs($this->operator)->post('/admin/orders', [
            'name' => 'Trần Văn Bình', 'phone' => '0988000009', 'address' => '5 Lê Lợi',
            'delivery_zone_id' => $this->zone->id, 'delivery_date' => '2026-10-03', 'time_slot' => '06:00-08:00',
            'order_type' => 'single',
            'items' => [
                ['product_id' => $this->rm->id, 'quantity' => ''],
                ['product_id' => $this->cn->id, 'quantity' => 3],
            ],
            'payment_method' => 'cod', 'channel' => 'phone',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $order = Order::where('code', 'DH261003-002')->firstOrFail();
        $this->assertSame([$this->cn->id], $order->items->pluck('product_id')->all());
    }

    public function test_create_form_renders_for_operators(): void
    {
        $this->actingAs($this->operator)->get('/admin/orders/create')
            ->assertOk()
            ->assertSee('Tạo đơn hộ khách')
            ->assertSee('Giỏ Mini')
            ->assertSee('name="channel"', false);
    }
}
