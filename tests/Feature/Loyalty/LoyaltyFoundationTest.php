<?php

namespace Tests\Feature\Loyalty;

use App\Enums\PointReason;
use App\Models\Customer;
use App\Models\Order;
use App\Models\PointTransaction;
use App\Support\Reward;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LoyaltyFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_adds_the_ledger_and_the_two_columns(): void
    {
        $this->assertTrue(Schema::hasTable('point_transactions'));
        $this->assertTrue(Schema::hasColumn('orders', 'requested_reward'));
        $this->assertTrue(Schema::hasColumn('order_items', 'is_gift'));
    }

    public function test_an_order_can_have_only_one_row_per_reason(): void
    {
        $order = Order::factory()->create();
        $attributes = ['customer_id' => $order->customer_id, 'order_id' => $order->id, 'reason' => PointReason::OrderDelivered];
        PointTransaction::factory()->create($attributes);
        PointTransaction::factory()->create(['reason' => PointReason::PackagingReturned] + $attributes);

        $this->expectException(UniqueConstraintViolationException::class);
        PointTransaction::factory()->create($attributes);
    }

    public function test_rows_without_an_order_are_not_limited_by_the_unique_key(): void
    {
        $customer = Customer::factory()->create();
        PointTransaction::factory()->count(2)->create(['customer_id' => $customer->id, 'reason' => PointReason::PackagingReturned]);

        $this->assertSame(10, (int) $customer->pointTransactions()->sum('points'));
    }

    public function test_model_casts_and_relations(): void
    {
        $order = Order::factory()->create();
        $row = PointTransaction::factory()->create(['customer_id' => $order->customer_id, 'order_id' => $order->id, 'points' => '-20', 'reason' => 'redeemed', 'reward' => 'small_bunch'])->refresh();

        $this->assertSame(-20, $row->points);
        $this->assertSame(PointReason::Redeemed, $row->reason);
        $this->assertTrue($row->customer->is($order->customer));
        $this->assertTrue($row->order->is($order));
        $this->assertNull($row->packagingReturn);
        $this->assertNull($row->creator);
    }

    public function test_reward_catalog_matches_the_agreed_tiers(): void
    {
        $this->assertSame(
            ['small_bunch' => 20, 'free_shipping' => 30, 'discount_20k' => 50, 'small_combo' => 100],
            collect(Reward::all())->mapWithKeys(fn (Reward $reward) => [$reward->key => $reward->points])->all(),
        );
        $this->assertSame(['small_bunch', 'free_shipping', 'discount_20k', 'small_combo'], Reward::keys());
        $this->assertSame([1, 3], [Reward::find('small_bunch')->packs, Reward::find('small_combo')->packs]);
        $this->assertSame(20000, Reward::find('discount_20k')->amount);
        $this->assertSame('Miễn phí 1 lần giao hàng', Reward::find('free_shipping')->label);
        $this->assertTrue(Reward::find('small_combo')->isGift());
        $this->assertFalse(Reward::find('free_shipping')->isGift());
        $this->assertNull(Reward::find('gold_bar'));
        $this->assertNull(Reward::find(null));
    }

    public function test_earning_rules_match_the_agreed_numbers(): void
    {
        $this->assertSame(
            [5, 5, 50000],
            [config('farm2home.loyalty.order_points'), config('farm2home.loyalty.packaging_points'), config('farm2home.loyalty.min_order_subtotal')],
        );
    }
}
