<?php

namespace Tests\Feature\Orders;

use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\PointTransaction;
use App\Services\LoyaltyService;
use App\Services\OrderService;
use App\Support\Reward;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsCatalog;
use Tests\TestCase;

class CustomerCancelTest extends TestCase
{
    use BuildsCatalog, RefreshDatabase;

    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-02 10:00:00');
        $this->buildCatalog();
        $this->order = $this->placeOrder();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function placeOrder(array $overrides = []): Order
    {
        return app(OrderService::class)->place(array_merge([
            'name' => 'Nguyễn Thị Lan', 'phone' => '0988000001', 'address' => '129 Rạch Gầm',
            'delivery_zone_id' => $this->zone->id, 'delivery_date' => '2026-10-04', 'time_slot' => '17:00-19:00',
            'order_type' => 'single', 'items' => [['product_id' => $this->rm->id, 'quantity' => 2]],
            'payment_method' => 'cod', 'data_consent' => true,
        ], $overrides), OrderChannel::Website);
    }

    public function test_new_orders_get_an_access_key(): void
    {
        $other = $this->placeOrder(['delivery_date' => '2026-10-05']);

        $this->assertSame(24, strlen((string) $this->order->access_key));
        $this->assertNotSame($this->order->access_key, $other->access_key);
    }

    private function assertCancelRefused(string $needle, ?Order $order = null): void
    {
        try {
            app(OrderService::class)->cancelByCustomer($order ?? $this->order, 'Đổi lịch');
        } catch (DomainException $e) {
            $this->assertStringContainsString($needle, $e->getMessage());
            $this->assertNotSame(OrderStatus::Cancelled, $this->order->fresh()->status);

            return;
        }

        $this->fail('Expected the cancel to be refused.');
    }

    public function test_customer_can_cancel_pending_order_before_cutoff(): void
    {
        app(OrderService::class)->cancelByCustomer($this->order, 'Đặt nhầm');

        $fresh = $this->order->fresh();
        $this->assertSame(OrderStatus::Cancelled, $fresh->status);
        $this->assertSame('Khách tự hủy: Đặt nhầm', $fresh->cancel_reason);
    }

    public function test_refused_when_confirmed(): void
    {
        app(OrderService::class)->transition($this->order, OrderStatus::Confirmed);

        $this->assertCancelRefused('không tự hủy được nữa');
        $this->assertSame(OrderStatus::Confirmed, $this->order->fresh()->status);
    }

    public function test_refused_after_cutoff(): void
    {
        $this->travelTo('2026-10-03 18:00:00');

        $this->assertCancelRefused('Đã quá giờ hủy (trước 18:00 ngày 03/10)');
    }

    public function test_allowed_one_second_before_cutoff(): void
    {
        $this->travelTo('2026-10-03 17:59:59');

        app(OrderService::class)->cancelByCustomer($this->order, 'Đổi lịch');

        $this->assertSame(OrderStatus::Cancelled, $this->order->fresh()->status);
    }

    public function test_refused_when_paid(): void
    {
        $this->order->update(['payment_method' => 'bank_transfer', 'payment_status' => 'paid']);

        $this->assertCancelRefused('Đơn đã thanh toán');
    }

    public function test_refused_when_already_cancelled(): void
    {
        app(OrderService::class)->cancelByCustomer($this->order, 'Đặt nhầm');

        try {
            app(OrderService::class)->cancelByCustomer($this->order->fresh(), 'Đặt nhầm');
            $this->fail('Expected the second cancel to be refused.');
        } catch (DomainException $e) {
            $this->assertSame('Đơn này đã được hủy.', $e->getMessage());
        }
    }

    public function test_uses_fresh_status_not_stale_model(): void
    {
        $stale = $this->order;
        Order::whereKey($stale->id)->update(['status' => 'confirmed']);

        $this->assertCancelRefused('không tự hủy được nữa', $stale);
    }

    public function test_operator_with_stale_model_cannot_overwrite_a_customer_cancel(): void
    {
        $stale = Order::findOrFail($this->order->id);
        app(OrderService::class)->cancelByCustomer($this->order, 'Đặt nhầm');

        try {
            app(OrderService::class)->transition($stale, OrderStatus::Confirmed);
            $this->fail('Expected the stale confirm to be refused.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('vừa được cập nhật', $e->getMessage());
        }

        $this->assertSame(OrderStatus::Cancelled, $this->order->fresh()->status);
    }

    public function test_unpaid_bank_transfer_cancel_flags_a_refund_check(): void
    {
        $this->order->update(['payment_method' => 'bank_transfer']);

        app(OrderService::class)->cancelByCustomer($this->order, 'Đặt nhầm');

        $this->assertSame('Khách tự hủy (chuyển khoản, kiểm tra hoàn tiền nếu đã nhận): Đặt nhầm', $this->order->fresh()->cancel_reason);
        $this->assertSame(OrderStatus::Cancelled, $this->order->fresh()->status);
    }

    public function test_cancel_refunds_redeemed_points(): void
    {
        $loyalty = app(LoyaltyService::class);
        PointTransaction::factory()->create(['customer_id' => $this->order->customer_id, 'points' => 30]);
        $loyalty->redeem($this->order, Reward::find('free_shipping'), [], $this->operator);

        app(OrderService::class)->cancelByCustomer($this->order->fresh(), 'Không cần nữa');

        $this->assertSame(30, $loyalty->balance($this->order->customer));
    }

    private function cancelUrl(?string $key = null): string
    {
        return "/don/{$this->order->code}/".($key ?? $this->order->access_key).'/huy';
    }

    public function test_page_shows_order_with_cancel_form(): void
    {
        $this->get($this->order->customerUrl())
            ->assertOk()
            ->assertSee($this->order->code)
            ->assertSee('Rau muống')
            ->assertSee('Hủy đơn này')
            ->assertSee('Đổi lịch')
            ->assertSee('trước 18:00 ngày 03/10')
            ->assertDontSee('129 Rạch Gầm');
    }

    public function test_wrong_key_is_404(): void
    {
        $this->get("/don/{$this->order->code}/wrongkey")->assertNotFound();
        $this->post($this->cancelUrl('wrongkey'), ['reason' => 'Đổi lịch'])->assertNotFound();

        $this->assertSame(OrderStatus::Pending, $this->order->fresh()->status);
    }

    public function test_unknown_code_is_404(): void
    {
        $this->get('/don/DH000000-999/abc')->assertNotFound();
    }

    public function test_customer_cancels_through_the_form(): void
    {
        $this->post($this->cancelUrl(), ['reason' => 'Đổi lịch'])->assertRedirect($this->order->customerUrl());

        $this->assertSame(OrderStatus::Cancelled, $this->order->fresh()->status);
        $this->get($this->order->customerUrl())
            ->assertSee('Khách tự hủy: Đổi lịch')
            ->assertDontSee('Hủy đơn này');
    }

    public function test_reason_is_required(): void
    {
        $this->post($this->cancelUrl(), ['reason' => '   '])->assertSessionHasErrors('reason');

        $this->assertSame(OrderStatus::Pending, $this->order->fresh()->status);
    }

    public function test_second_cancel_shows_error_not_crash(): void
    {
        $this->post($this->cancelUrl(), ['reason' => 'Đổi lịch']);

        $this->post($this->cancelUrl(), ['reason' => 'Đổi lịch'])
            ->assertRedirect()
            ->assertSessionHasErrors(['action' => 'Đơn này đã được hủy.']);
    }

    public function test_confirmed_order_page_explains_why(): void
    {
        app(OrderService::class)->transition($this->order, OrderStatus::Confirmed);

        $this->get($this->order->customerUrl())
            ->assertOk()
            ->assertSee('không tự hủy được nữa')
            ->assertDontSee('Hủy đơn này');
    }

    public function test_thanks_page_shows_the_customer_link(): void
    {
        $this->withSession(['last_order_code' => $this->order->code])
            ->get('/dat-hang/cam-on')
            ->assertOk()
            ->assertSee('Lưu link này để xem hoặc hủy đơn')
            ->assertSee($this->order->customerUrl(), false);
    }

    public function test_admin_order_page_has_copy_link(): void
    {
        $this->actingAs($this->operator)
            ->get("/admin/orders/{$this->order->id}")
            ->assertOk()
            ->assertSee('Sao chép link cho khách')
            ->assertSee($this->order->customerUrl(), false);
    }

    public function test_chatbot_points_to_the_self_cancel_link(): void
    {
        $reply = $this->postJson('/hoi-dap', ['message' => 'hủy đơn'])->assertOk()->json('reply');

        $this->assertStringContainsString('tự hủy được bằng link', $reply);
    }
}
