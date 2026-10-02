<?php

namespace Tests\Feature\Subscriptions;

use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Models\Order;
use App\Models\Subscription;
use App\Models\User;
use App\Services\SubscriptionService;
use Carbon\CarbonImmutable;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionServiceTest extends TestCase
{
    use RefreshDatabase;

    private SubscriptionService $subscriptions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-05 12:00:00');
        $this->seed(DemoSeeder::class);
        $this->subscriptions = app(SubscriptionService::class);
    }

    public function test_generates_confirmed_basket_orders_for_due_active_subscriptions(): void
    {
        // Dữ liệu mẫu: Thứ Bảy (6) có 4 giỏ đang chạy (id 3, 5, 6, 7) và 1 giỏ tạm dừng (id 9).
        $count = $this->subscriptions->generateOrders(CarbonImmutable::parse('2026-10-10'));

        $this->assertSame(4, $count);

        $orders = Order::whereDate('delivery_date', '2026-10-10')->orderBy('id')->get();
        $this->assertSame(['DH261010-001', 'DH261010-002', 'DH261010-003', 'DH261010-004'], $orders->pluck('code')->all());
        $this->assertEquals([3, 5, 6, 7], $orders->pluck('subscription_id')->sort()->values()->all());

        $order = $orders->firstWhere('subscription_id', 3);
        $subscription = Subscription::with('basket', 'customer')->findOrFail(3);
        $this->assertSame([OrderStatus::Confirmed, OrderType::Basket, OrderChannel::Website], [$order->status, $order->order_type, $order->channel]);
        $this->assertSame([$subscription->customer_id, $subscription->time_slot, (int) $subscription->basket->price], [$order->customer_id, $order->time_slot, $order->subtotal]);
        $this->assertNotNull($order->confirmed_at);
        $this->assertSame($subscription->basket->items()->count(), $order->items()->count());
    }

    public function test_running_again_does_not_duplicate(): void
    {
        $this->subscriptions->generateOrders(CarbonImmutable::parse('2026-10-10'));

        $this->assertSame(0, $this->subscriptions->generateOrders(CarbonImmutable::parse('2026-10-10')));
        $this->assertSame(4, Order::whereDate('delivery_date', '2026-10-10')->count());
    }

    public function test_other_weekdays_use_their_own_subscriptions(): void
    {
        // Thứ Ba 06/10: giỏ id 1. Thứ Năm 08/10: giỏ id 2, 4, 8. Thứ Tư 07/10: không có.
        $this->assertSame(1, $this->subscriptions->generateOrders(CarbonImmutable::parse('2026-10-06')));
        $this->assertSame(3, $this->subscriptions->generateOrders(CarbonImmutable::parse('2026-10-08')));
        $this->assertSame(0, $this->subscriptions->generateOrders(CarbonImmutable::parse('2026-10-07')));
    }

    public function test_paused_ended_or_inactive_basket_subscriptions_are_skipped(): void
    {
        Subscription::findOrFail(3)->update(['status' => 'cancelled']);
        Subscription::findOrFail(5)->update(['end_date' => '2026-10-09']);
        Subscription::findOrFail(6)->basket->update(['is_active' => false]);

        $count = $this->subscriptions->generateOrders(CarbonImmutable::parse('2026-10-10'));

        // Giỏ 5, 6 và 7 dùng chung basket 3 trong dữ liệu mẫu → đều bị bỏ qua.
        $this->assertSame(0, $count);
    }

    public function test_artisan_command_defaults_to_tomorrow(): void
    {
        // Hôm nay 05/10 (Thứ Hai) → ngày mai Thứ Ba 06/10.
        $this->artisan('subscriptions:generate')->assertSuccessful();

        $this->assertSame(1, Order::whereDate('delivery_date', '2026-10-06')->whereNotNull('subscription_id')->count());
    }

    public function test_operator_generates_from_the_admin_screen(): void
    {
        $this->actingAs(User::where('email', 'operator@farm2home.vn')->firstOrFail());

        $this->get('/admin/subscriptions')->assertOk()->assertSee('Giỏ Xanh Tiết Kiệm')->assertSee('Hằng tuần');
        $this->post('/admin/subscriptions/generate', ['date' => '2026-10-10'])
            ->assertRedirect()
            ->assertSessionHas('status', 'Đã tạo 4 đơn giỏ định kỳ.');
    }

    public function test_operator_creates_and_edits_a_subscription(): void
    {
        $this->actingAs(User::where('email', 'operator@farm2home.vn')->firstOrFail());

        $this->get('/admin/subscriptions/create')->assertOk();
        $this->post('/admin/subscriptions', [
            'phone' => '0988000002', 'basket_id' => 1, 'frequency' => 'biweekly', 'delivery_weekday' => 3,
            'time_slot' => '17:00-19:00', 'start_date' => '2026-10-07', 'status' => 'active',
        ])->assertRedirect('/admin/subscriptions');

        $subscription = Subscription::orderByDesc('id')->firstOrFail();
        $this->assertSame([2, 3, 'biweekly'], [$subscription->customer_id, $subscription->delivery_weekday, $subscription->frequency->value]);

        $this->get('/admin/subscriptions/'.$subscription->id.'/edit')->assertOk()->assertSee('value="0988000002"', false);
        $this->put('/admin/subscriptions/'.$subscription->id, [
            'phone' => '0988000002', 'basket_id' => 1, 'frequency' => 'biweekly', 'delivery_weekday' => 3,
            'time_slot' => '17:00-19:00', 'start_date' => '2026-10-07', 'status' => 'paused',
        ])->assertRedirect('/admin/subscriptions');
        $this->assertSame('paused', $subscription->refresh()->status->value);

        $this->post('/admin/subscriptions', [
            'phone' => '0900999999', 'basket_id' => 1, 'frequency' => 'weekly', 'delivery_weekday' => 9,
            'time_slot' => '17:00-19:00', 'start_date' => '2026-10-07', 'status' => 'active',
        ])->assertSessionHasErrors(['phone', 'delivery_weekday']);
    }
}
