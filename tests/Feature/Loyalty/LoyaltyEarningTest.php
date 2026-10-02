<?php

namespace Tests\Feature\Loyalty;

use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Enums\PointReason;
use App\Models\Order;
use App\Models\PackagingReturn;
use App\Models\PointTransaction;
use App\Services\LoyaltyService;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Support\BuildsCatalog;
use Tests\TestCase;

class LoyaltyEarningTest extends TestCase
{
    use BuildsCatalog, RefreshDatabase;

    private OrderService $orders;

    private LoyaltyService $loyalty;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-03 20:00:00');
        $this->buildCatalog();
        $this->orders = app(OrderService::class);
        $this->loyalty = app(LoyaltyService::class);
    }

    /**
     * Không truyền $items → đơn Giỏ Mini 45.000đ.
     *
     * @param  list<array{product_id: int, quantity: int}>|null  $items
     */
    private function place(?array $items = null, string $phone = '0988000001'): Order
    {
        return $this->orders->place([
            'name' => 'Nguyễn Thị Lan', 'phone' => $phone, 'address' => '129 Rạch Gầm',
            'delivery_zone_id' => $this->zone->id, 'delivery_date' => '2026-10-04', 'time_slot' => '17:00-19:00',
            'order_type' => $items === null ? 'basket' : 'single',
            'basket_id' => $items === null ? $this->basket->id : null,
            'items' => $items,
            'payment_method' => 'cod', 'data_consent' => true,
        ], OrderChannel::Zalo);
    }

    private function deliver(Order $order): Order
    {
        foreach ([OrderStatus::Confirmed, OrderStatus::Packing, OrderStatus::Delivering, OrderStatus::Delivered] as $status) {
            $this->orders->transition($order, $status);
        }

        return $order->refresh();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function returnPackaging(array $overrides = []): TestResponse
    {
        return $this->actingAs($this->operator)->post('/admin/packaging-returns', array_merge([
            'return_date' => '2026-10-03', 'quantity_returned' => 2, 'quantity_reused' => 1,
        ], $overrides));
    }

    public function test_a_delivered_order_earns_five_points_exactly_once(): void
    {
        $order = $this->deliver($this->place());

        $row = PointTransaction::sole();
        $this->assertSame(
            [5, PointReason::OrderDelivered, $order->id, $order->customer_id],
            [$row->points, $row->reason, $row->order_id, $row->customer_id],
        );
        $this->assertTrue($row->created_at->equalTo($order->delivered_at));
        $this->assertSame(5, $this->loyalty->balance($order->customer));

        $this->assertNull($this->loyalty->awardForDeliveredOrder($order));
        $this->assertSame(1, PointTransaction::count());
    }

    public function test_the_minimum_subtotal_is_inclusive(): void
    {
        $atThreshold = $this->deliver($this->place([['product_id' => $this->hl->id, 'quantity' => 5]]));
        $below = $this->deliver($this->place([
            ['product_id' => $this->rm->id, 'quantity' => 2],
            ['product_id' => $this->cn->id, 'quantity' => 1],
        ], '0988000002'));

        $this->assertSame([40000, 39000], [$atThreshold->subtotal, $below->subtotal]);
        $this->assertSame(5, $this->loyalty->balance($atThreshold->customer));
        $this->assertSame(0, $this->loyalty->balance($below->customer));
    }

    public function test_an_order_that_is_not_delivered_earns_nothing(): void
    {
        $order = $this->place();
        $this->assertNull($this->loyalty->awardForDeliveredOrder($order));

        $this->orders->transition($order, OrderStatus::Cancelled, 'Khách đổi lịch');
        $this->assertNull($this->loyalty->awardForDeliveredOrder($order->refresh()));
        $this->assertSame(0, PointTransaction::count());
    }

    public function test_returning_packaging_for_an_order_earns_five_points_once_per_order(): void
    {
        $order = $this->place();

        $this->returnPackaging(['order_code' => $order->code])
            ->assertSessionHas('status', 'Đã ghi nhận bao bì thu hồi. +5 điểm cho Nguyễn Thị Lan.');
        $this->returnPackaging(['order_code' => $order->code])
            ->assertSessionHas('status', 'Đã ghi nhận bao bì thu hồi.');

        $row = PointTransaction::sole();
        $this->assertSame(
            [5, PointReason::PackagingReturned, $order->id, PackagingReturn::orderBy('id')->firstOrFail()->id, $order->customer_id],
            [$row->points, $row->reason, $row->order_id, $row->packaging_return_id, $row->customer_id],
        );
        $this->assertSame(2, PackagingReturn::count());
    }

    public function test_delivered_and_returned_together_is_ten_points(): void
    {
        $order = $this->deliver($this->place());
        $this->returnPackaging(['order_code' => $order->code]);

        $this->assertSame(10, $this->loyalty->balance($order->customer));
    }

    public function test_return_without_an_order_code_earns_once_per_customer_per_day(): void
    {
        $customer = $this->place()->customer;

        $this->returnPackaging(['phone' => '0988000001']);
        $this->returnPackaging(['phone' => '0988000001']);
        $this->assertSame(5, $this->loyalty->balance($customer));

        $this->returnPackaging(['phone' => '0988000001', 'return_date' => '2026-10-02']);
        $this->assertSame(10, $this->loyalty->balance($customer));
    }

    public function test_return_without_a_known_customer_earns_nothing(): void
    {
        $this->returnPackaging()->assertSessionHas('status', 'Đã ghi nhận bao bì thu hồi.');
        $this->returnPackaging(['phone' => '0977000000'])->assertSessionHas('status', 'Đã ghi nhận bao bì thu hồi.');

        $this->assertSame(2, PackagingReturn::count());
        $this->assertSame(0, PointTransaction::count());
    }

    public function test_points_go_to_the_phone_on_the_return_and_still_only_once_per_order(): void
    {
        $order = $this->place();
        $neighbour = $this->place(null, '0988000002')->customer;

        $this->returnPackaging(['order_code' => $order->code, 'phone' => '0988000002']);
        $this->returnPackaging(['order_code' => $order->code, 'phone' => '0988000001']);

        $this->assertSame(5, $this->loyalty->balance($neighbour));
        $this->assertSame(0, $this->loyalty->balance($order->customer));
    }
}
