<?php

namespace Tests\Feature\Orders;

use App\Enums\DeliveryMethod;
use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Services\OrderService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Support\BuildsCatalog;
use Tests\TestCase;

class OrderServiceTest extends TestCase
{
    use BuildsCatalog, RefreshDatabase;

    private OrderService $orders;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-02 10:00:00');
        $this->buildCatalog();
        $this->orders = app(OrderService::class);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Nguyễn Thị Lan',
            'phone' => '0988000001',
            'address' => '129 Rạch Gầm',
            'delivery_zone_id' => $this->zone->id,
            'delivery_date' => '2026-10-03',
            'time_slot' => '17:00-19:00',
            'order_type' => 'single',
            'items' => [['product_id' => $this->rm->id, 'quantity' => 2]],
            'payment_method' => 'cod',
            'data_consent' => true,
            'note' => null,
        ], $overrides);
    }

    public function test_single_order_totals_weight_and_code(): void
    {
        $order = $this->orders->place($this->payload(), OrderChannel::Website);

        $this->assertSame('DH261003-001', $order->code);
        $this->assertSame(OrderStatus::Pending, $order->status);
        $this->assertSame(OrderChannel::Website, $order->channel);
        $this->assertSame(DeliveryMethod::Home, $order->delivery_method);
        $this->assertSame('129 Rạch Gầm', $order->delivery_address);
        $this->assertSame([24000, 10000, 0, 34000], [$order->subtotal, $order->shipping_fee, $order->discount, $order->total]);
        $this->assertCount(1, $order->items);
        $this->assertSame('1.000', $order->items[0]->weight_kg);
        $this->assertSame([12000, 24000], [$order->items[0]->unit_price, $order->items[0]->line_total]);
        $this->assertNull($order->items[0]->basket_id);
    }

    public function test_basket_order_puts_the_basket_price_in_subtotal_and_zero_on_lines(): void
    {
        $order = $this->orders->place($this->payload(['order_type' => 'basket', 'basket_id' => $this->basket->id, 'items' => null]), OrderChannel::Website);

        $this->assertSame([45000, 10000, 55000], [$order->subtotal, $order->shipping_fee, $order->total]);
        $this->assertCount(3, $order->items);
        $this->assertSame([0], $order->items->pluck('unit_price')->unique()->values()->all());
        $this->assertSame([$this->basket->id], $order->items->pluck('basket_id')->unique()->values()->all());
        $this->assertEqualsWithDelta(1.2, $order->items->sum(fn ($i) => (float) $i->weight_kg), 0.0001);
    }

    public function test_pickup_zone_has_no_shipping_fee_and_no_address(): void
    {
        $order = $this->orders->place($this->payload(['delivery_zone_id' => $this->pickupZone->id, 'address' => null]), OrderChannel::Website);

        $this->assertSame(DeliveryMethod::Pickup, $order->delivery_method);
        $this->assertSame(0, $order->shipping_fee);
        $this->assertNull($order->delivery_address);
    }

    public function test_shipping_is_free_from_the_zone_threshold(): void
    {
        $order = $this->orders->place($this->payload(['items' => [['product_id' => $this->rm->id, 'quantity' => 13]]]), OrderChannel::Website);

        $this->assertSame([156000, 0, 156000], [$order->subtotal, $order->shipping_fee, $order->total]);
    }

    public function test_codes_are_sequential_per_delivery_date(): void
    {
        $a = $this->orders->place($this->payload(), OrderChannel::Website);
        $b = $this->orders->place($this->payload(['phone' => '0988000002']), OrderChannel::Zalo);
        $c = $this->orders->place($this->payload(['phone' => '0988000003', 'delivery_date' => '2026-10-04']), OrderChannel::Website);

        $this->assertSame(['DH261003-001', 'DH261003-002', 'DH261004-001'], [$a->code, $b->code, $c->code]);
    }

    public function test_code_continues_after_a_cancelled_order(): void
    {
        $a = $this->orders->place($this->payload(), OrderChannel::Website);
        $this->orders->transition($a, OrderStatus::Cancelled, 'Khách đổi lịch');
        $b = $this->orders->place($this->payload(), OrderChannel::Website);

        $this->assertSame('DH261003-002', $b->code);
    }

    public function test_returning_phone_reuses_customer(): void
    {
        $this->orders->place($this->payload(), OrderChannel::Zalo);
        $this->orders->place($this->payload(['name' => 'Chị Lan', 'address' => '5 Lê Lợi']), OrderChannel::Website);

        $this->assertSame(1, Customer::count());
        $customer = Customer::firstOrFail();
        $this->assertSame('Chị Lan', $customer->name);
        $this->assertSame('5 Lê Lợi', $customer->address);
        $this->assertSame('zalo', $customer->source->value);
        $this->assertTrue($customer->data_consent);
        $this->assertSame(2, $customer->orders()->count());
    }

    public function test_zero_quantity_lines_are_ignored_and_duplicates_merged(): void
    {
        $order = $this->orders->place($this->payload(['items' => [
            ['product_id' => $this->rm->id, 'quantity' => 1],
            ['product_id' => $this->cn->id, 'quantity' => 0],
            ['product_id' => $this->rm->id, 'quantity' => 2],
        ]]), OrderChannel::Website);

        $this->assertCount(1, $order->items);
        $this->assertSame(3, $order->items[0]->quantity);
        $this->assertSame(36000, $order->subtotal);
    }

    public function test_order_without_any_quantity_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        $this->orders->place($this->payload(['items' => [['product_id' => $this->rm->id, 'quantity' => 0]]]), OrderChannel::Website);
    }

    public function test_inactive_product_is_rejected(): void
    {
        $this->cn->update(['is_active' => false]);

        $this->expectException(ValidationException::class);

        $this->orders->place($this->payload(['items' => [['product_id' => $this->cn->id, 'quantity' => 1]]]), OrderChannel::Website);
    }

    public function test_nothing_is_saved_when_placing_fails(): void
    {
        try {
            $this->orders->place($this->payload(['items' => []]), OrderChannel::Website);
        } catch (ValidationException) {
        }

        $this->assertSame(0, Order::count());
        $this->assertSame(0, Customer::count());
    }

    public function test_confirming_and_delivering_a_cod_order(): void
    {
        $order = $this->orders->place($this->payload(), OrderChannel::Website);

        $this->orders->transition($order, OrderStatus::Confirmed);
        $this->assertNotNull($order->confirmed_at);

        $this->orders->transition($order, OrderStatus::Packing);
        $this->orders->transition($order, OrderStatus::Delivering);
        $this->orders->transition($order, OrderStatus::Delivered);

        $order->refresh();
        $this->assertSame(OrderStatus::Delivered, $order->status);
        $this->assertNotNull($order->delivered_at);
        $this->assertSame(PaymentStatus::Paid, $order->payment_status);
        $this->assertSame(2, $order->items[0]->fulfilled_quantity);
    }

    public function test_bank_transfer_order_is_not_marked_paid_on_delivery(): void
    {
        $order = $this->orders->place($this->payload(['payment_method' => 'bank_transfer']), OrderChannel::Website);
        foreach ([OrderStatus::Confirmed, OrderStatus::Packing, OrderStatus::Delivering, OrderStatus::Delivered] as $status) {
            $this->orders->transition($order, $status);
        }

        $this->assertSame(PaymentStatus::Unpaid, $order->refresh()->payment_status);
    }

    public function test_steps_cannot_be_skipped(): void
    {
        $order = $this->orders->place($this->payload(), OrderChannel::Website);

        $this->expectException(DomainException::class);

        $this->orders->transition($order, OrderStatus::Delivered);
    }

    public function test_cancelling_requires_a_reason(): void
    {
        $order = $this->orders->place($this->payload(), OrderChannel::Website);

        try {
            $this->orders->transition($order, OrderStatus::Cancelled);
            $this->fail('Expected DomainException');
        } catch (DomainException $e) {
            $this->assertSame('Cần nhập lý do hủy đơn.', $e->getMessage());
        }

        $this->orders->transition($order, OrderStatus::Cancelled, 'Khách đổi lịch');
        $this->assertSame('Khách đổi lịch', $order->refresh()->cancel_reason);
    }
}
