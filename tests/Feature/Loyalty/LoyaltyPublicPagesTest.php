<?php

namespace Tests\Feature\Loyalty;

use App\Models\Customer;
use App\Models\Order;
use App\Models\PointTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsCatalog;
use Tests\TestCase;

class LoyaltyPublicPagesTest extends TestCase
{
    use BuildsCatalog, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-02 10:00:00');
        $this->buildCatalog();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function form(array $overrides = []): array
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
            'data_consent' => '1',
        ], $overrides);
    }

    public function test_order_form_offers_the_four_rewards(): void
    {
        $this->get('/dat-hang')
            ->assertOk()
            ->assertSee('Đổi điểm lấy quà')
            ->assertSee('name="requested_reward"', false)
            ->assertSee('Tặng bó rau nhỏ — 20 điểm')
            ->assertSee('Miễn phí 1 lần giao hàng — 30 điểm')
            ->assertSee('Giảm 20.000đ cho đơn — 50 điểm')
            ->assertSee('Tặng combo rau nhỏ — 100 điểm');
    }

    public function test_requested_reward_is_stored_without_touching_points_or_totals(): void
    {
        $this->post('/dat-hang', $this->form(['requested_reward' => 'free_shipping']))->assertRedirect('/dat-hang/cam-on');

        $order = Order::firstOrFail();
        $this->assertSame('free_shipping', $order->requested_reward);
        $this->assertSame([10000, 34000], [$order->shipping_fee, $order->total]);
        $this->assertSame(0, PointTransaction::count());

        $this->get('/dat-hang/cam-on')
            ->assertOk()
            ->assertSee('Miễn phí 1 lần giao hàng')
            ->assertSee('Yêu cầu đổi quà sẽ được xác nhận cùng đơn hàng.');
    }

    public function test_order_without_a_reward_keeps_the_column_null(): void
    {
        $this->post('/dat-hang', $this->form(['requested_reward' => '']))->assertRedirect('/dat-hang/cam-on');

        $this->assertNull(Order::firstOrFail()->requested_reward);
        $this->get('/dat-hang/cam-on')->assertOk()->assertDontSee('Yêu cầu đổi quà');
    }

    public function test_unknown_reward_key_is_rejected_and_no_order_is_created(): void
    {
        $this->post('/dat-hang', $this->form(['requested_reward' => 'gold_bar']))->assertSessionHasErrors('requested_reward');

        $this->assertSame(0, Order::count());
    }

    public function test_lookup_shows_the_balance_and_which_rewards_are_within_reach(): void
    {
        $this->post('/dat-hang', $this->form());
        PointTransaction::factory()->create(['customer_id' => Customer::firstOrFail()->id, 'points' => 35]);

        $this->post('/tra-cuu', ['phone' => '0988000001'])
            ->assertOk()
            ->assertSee('Bạn đang có 35 điểm')
            ->assertSeeInOrder([
                'Tặng bó rau nhỏ', '20 điểm · đủ điểm',
                'Miễn phí 1 lần giao hàng', '30 điểm · đủ điểm',
                'Giảm 20.000đ cho đơn', '50 điểm',
                'Tặng combo rau nhỏ', '100 điểm',
            ])
            ->assertDontSee('50 điểm · đủ điểm')
            ->assertDontSee('Nguyễn Thị Lan')
            ->assertDontSee('129 Rạch Gầm');
    }

    public function test_a_customer_with_no_points_still_sees_how_to_earn_them(): void
    {
        $this->post('/dat-hang', $this->form());

        $this->post('/tra-cuu', ['phone' => '0988000001'])
            ->assertOk()
            ->assertSee('Bạn đang có 0 điểm')
            ->assertSee('40.000đ')
            ->assertDontSee('đủ điểm');
    }

    public function test_lookup_shows_no_points_block_for_an_unknown_phone_or_before_searching(): void
    {
        $this->post('/tra-cuu', ['phone' => '0977000000'])->assertOk()->assertDontSee('Bạn đang có');
        $this->get('/tra-cuu')->assertOk()->assertDontSee('Bạn đang có');
    }
}
