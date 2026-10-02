<?php

namespace Tests\Feature\Loyalty;

use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Enums\PointReason;
use App\Models\Order;
use App\Models\PointTransaction;
use App\Services\LoyaltyService;
use App\Services\OrderService;
use App\Support\Reward;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsCatalog;
use Tests\TestCase;

class LoyaltyRedeemTest extends TestCase
{
    use BuildsCatalog, RefreshDatabase;

    private OrderService $orders;

    private LoyaltyService $loyalty;

    /** 2 gói rau muống: tiền hàng 24.000đ + phí giao 10.000đ. */
    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-02 10:00:00');
        $this->buildCatalog();
        $this->orders = app(OrderService::class);
        $this->loyalty = app(LoyaltyService::class);
        $this->order = $this->place();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function place(array $overrides = []): Order
    {
        return $this->orders->place(array_merge([
            'name' => 'Nguyễn Thị Lan', 'phone' => '0988000001', 'address' => '129 Rạch Gầm',
            'delivery_zone_id' => $this->zone->id, 'delivery_date' => '2026-10-03', 'time_slot' => '17:00-19:00',
            'order_type' => 'single', 'items' => [['product_id' => $this->rm->id, 'quantity' => 2]],
            'payment_method' => 'cod', 'data_consent' => true,
        ], $overrides), OrderChannel::Website);
    }

    private function givePoints(int $points): void
    {
        PointTransaction::factory()->create(['customer_id' => $this->order->customer_id, 'points' => $points]);
    }

    /**
     * @param  list<int>  $productIds
     */
    private function redeem(string $key, array $productIds = [], ?Order $order = null): PointTransaction
    {
        return $this->loyalty->redeem($order ?? $this->order, Reward::find($key), $productIds, $this->operator);
    }

    private function balance(): int
    {
        return $this->loyalty->balance($this->order->customer);
    }

    private function assertRefused(string $needle, callable $attempt): void
    {
        try {
            $attempt();
        } catch (DomainException $e) {
            $this->assertStringContainsString($needle, $e->getMessage());

            return;
        }

        $this->fail("Expected a refusal containing \"{$needle}\".");
    }

    /**
     * @return array{0: int, 1: int, 2: int, 3: int}
     */
    private function money(Order $order): array
    {
        $order->refresh();

        return [$order->subtotal, $order->shipping_fee, $order->discount, $order->total];
    }

    public function test_small_bunch_adds_one_free_pack_and_costs_twenty_points(): void
    {
        $this->givePoints(100);

        $row = $this->redeem('small_bunch', [$this->cn->id]);

        $this->assertSame(
            [-20, PointReason::Redeemed, 'small_bunch', $this->order->id, $this->operator->id],
            [$row->points, $row->reason, $row->reward, $row->order_id, $row->created_by],
        );
        $this->assertSame(80, $this->balance());

        $gift = $this->order->items()->where('is_gift', true)->sole();
        $this->assertSame(
            [$this->cn->id, 1, '0.500', 0, 0, null],
            [$gift->product_id, $gift->quantity, $gift->weight_kg, $gift->unit_price, $gift->line_total, $gift->basket_id],
        );
        $this->assertSame(1, $this->order->items()->where('is_gift', false)->count());
        $this->assertSame([24000, 10000, 0, 34000], $this->money($this->order));
        $this->assertSame('small_bunch', $this->loyalty->redemptionFor($this->order)->reward);
    }

    public function test_small_combo_takes_exactly_three_packs_and_merges_repeated_products(): void
    {
        $this->givePoints(100);

        $this->redeem('small_combo', [$this->rm->id, $this->hl->id, $this->rm->id]);

        $gifts = $this->order->items()->where('is_gift', true)->orderBy('product_id')->get();
        $this->assertSame(
            [[$this->rm->id, 2, '1.000'], [$this->hl->id, 1, '0.200']],
            $gifts->map(fn ($item) => [$item->product_id, $item->quantity, $item->weight_kg])->all(),
        );
        $this->assertSame(0, $this->balance());
    }

    public function test_gift_rewards_refuse_a_wrong_pack_count_or_a_product_not_on_sale(): void
    {
        $this->givePoints(100);
        $this->hl->forceFill(['is_active' => false])->save();

        $this->assertRefused('đúng 1 gói', fn () => $this->redeem('small_bunch', []));
        $this->assertRefused('đúng 1 gói', fn () => $this->redeem('small_bunch', [$this->rm->id, $this->cn->id]));
        $this->assertRefused('đúng 3 gói', fn () => $this->redeem('small_combo', [$this->rm->id, $this->cn->id]));
        $this->assertRefused('không còn bán', fn () => $this->redeem('small_bunch', [$this->hl->id]));
        $this->assertRefused('không còn bán', fn () => $this->redeem('small_bunch', [999999]));

        $this->assertSame(100, $this->balance());
        $this->assertSame(1, $this->order->items()->count());
    }

    public function test_free_shipping_zeroes_the_fee(): void
    {
        $this->givePoints(30);

        $this->redeem('free_shipping');

        $this->assertSame([24000, 0, 0, 24000], $this->money($this->order));
        $this->assertSame(0, $this->balance());
    }

    public function test_free_shipping_is_refused_when_the_order_has_no_fee(): void
    {
        $pickup = $this->place(['delivery_zone_id' => $this->pickupZone->id, 'address' => null, 'delivery_date' => '2026-10-04']);
        $this->givePoints(30);

        $this->assertRefused('không có phí giao', fn () => $this->redeem('free_shipping', [], $pickup));

        $this->assertSame(30, $this->balance());
    }

    public function test_discount_takes_twenty_thousand_off(): void
    {
        $this->givePoints(60);

        $this->redeem('discount_20k');

        $this->assertSame([24000, 10000, 20000, 14000], $this->money($this->order));
        $this->assertSame(10, $this->balance());
    }

    public function test_discount_is_capped_at_the_subtotal_but_still_costs_fifty_points(): void
    {
        $small = $this->place(['items' => [['product_id' => $this->rm->id, 'quantity' => 1]], 'delivery_date' => '2026-10-04']);
        $this->givePoints(50);

        $this->redeem('discount_20k', [], $small);

        $this->assertSame([12000, 10000, 12000, 10000], $this->money($small));
        $this->assertSame(0, $this->balance());
    }

    public function test_exactly_enough_points_is_enough_and_one_short_is_not(): void
    {
        $this->givePoints(19);
        $this->assertRefused('có 19, cần 20', fn () => $this->redeem('small_bunch', [$this->rm->id]));
        $this->assertSame(0, PointTransaction::where('reason', PointReason::Redeemed)->count());
        $this->assertSame(1, $this->order->items()->count());

        $this->givePoints(1);
        $this->redeem('small_bunch', [$this->rm->id]);
        $this->assertSame(0, $this->balance());
    }

    public function test_a_second_click_on_the_same_order_does_not_spend_points_twice(): void
    {
        $this->givePoints(100);
        $this->redeem('free_shipping');

        $this->assertRefused('đã đổi quà', fn () => $this->redeem('free_shipping'));
        $this->assertRefused('đã đổi quà', fn () => $this->redeem('discount_20k'));

        $this->assertSame(70, $this->balance());
        $this->assertSame([24000, 0, 0, 24000], $this->money($this->order));
    }

    public function test_points_cannot_be_spent_past_zero_across_orders(): void
    {
        $other = $this->place(['delivery_date' => '2026-10-04']);
        $this->givePoints(100);
        $this->redeem('small_combo', [$this->rm->id, $this->rm->id, $this->rm->id]);

        $this->assertRefused('có 0, cần 20', fn () => $this->redeem('small_bunch', [$this->rm->id], $other));
    }

    public function test_rewards_can_only_be_applied_before_packing(): void
    {
        $this->givePoints(100);
        $this->orders->transition($this->order, OrderStatus::Confirmed);
        $this->orders->transition($this->order, OrderStatus::Packing);
        $this->assertFalse($this->loyalty->canRedeemOn($this->order));
        $this->assertRefused('chờ xác nhận hoặc đã xác nhận', fn () => $this->redeem('free_shipping'));

        $confirmed = $this->place(['delivery_date' => '2026-10-04']);
        $this->orders->transition($confirmed, OrderStatus::Confirmed);
        $this->redeem('free_shipping', [], $confirmed);

        $this->assertSame(70, $this->balance());
    }

    public function test_cancelling_refunds_the_points_once_and_they_work_on_the_next_order(): void
    {
        $this->givePoints(30);
        $this->redeem('free_shipping');

        $this->orders->transition($this->order, OrderStatus::Cancelled, 'Khách đổi lịch');

        $refund = PointTransaction::where('reason', PointReason::Refunded)->sole();
        $this->assertSame([30, $this->order->id, 'free_shipping'], [$refund->points, $refund->order_id, $refund->reward]);
        $this->assertSame(30, $this->balance());
        $this->assertNull($this->loyalty->refundForCancelledOrder($this->order));

        $next = $this->place(['delivery_date' => '2026-10-04']);
        $this->redeem('free_shipping', [], $next);
        $this->assertSame(0, $this->balance());
    }

    public function test_cancelling_an_order_without_a_reward_writes_nothing(): void
    {
        $this->orders->transition($this->order, OrderStatus::Cancelled, 'Khách đổi lịch');

        $this->assertSame(0, PointTransaction::count());
    }
}
