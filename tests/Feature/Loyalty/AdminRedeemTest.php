<?php

namespace Tests\Feature\Loyalty;

use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Models\HarvestPlan;
use App\Models\Order;
use App\Models\PointTransaction;
use App\Services\LoyaltyService;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsCatalog;
use Tests\TestCase;

class AdminRedeemTest extends TestCase
{
    use BuildsCatalog, RefreshDatabase;

    /** 2 gói rau muống 24.000đ + phí giao 10.000đ; khách xin miễn phí giao. */
    private Order $order;

    private string $url;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-02 10:00:00');
        $this->buildCatalog();
        $this->order = app(OrderService::class)->place([
            'name' => 'Nguyễn Thị Lan', 'phone' => '0988000001', 'address' => '129 Rạch Gầm',
            'delivery_zone_id' => $this->zone->id, 'delivery_date' => '2026-10-03', 'time_slot' => '17:00-19:00',
            'order_type' => 'single', 'items' => [['product_id' => $this->rm->id, 'quantity' => 2]],
            'payment_method' => 'cod', 'data_consent' => true, 'requested_reward' => 'free_shipping',
        ], OrderChannel::Website);
        $this->url = '/admin/orders/'.$this->order->id;
    }

    private function givePoints(int $points): void
    {
        PointTransaction::factory()->create(['customer_id' => $this->order->customer_id, 'points' => $points]);
    }

    private function balance(): int
    {
        return app(LoyaltyService::class)->balance($this->order->customer);
    }

    public function test_guests_cannot_apply_a_reward(): void
    {
        $this->post($this->url.'/reward', ['reward' => 'free_shipping'])->assertRedirect('/login');
    }

    public function test_order_page_shows_balance_request_and_shortfall(): void
    {
        $this->givePoints(25);

        $this->actingAs($this->operator)->get($this->url)
            ->assertOk()
            ->assertSee('Điểm thành viên')
            ->assertSee('<span class="font-semibold">25 điểm</span>', false)
            ->assertSee('Khách muốn đổi')
            ->assertSee('Miễn phí 1 lần giao hàng')
            ->assertSee('thiếu 5 điểm')
            ->assertSee('name="reward"', false)
            ->assertSee('Áp dụng quà');
    }

    public function test_order_page_hides_the_form_when_no_reward_is_within_reach(): void
    {
        $this->actingAs($this->operator)->get($this->url)
            ->assertOk()
            ->assertSee('<span class="font-semibold">0 điểm</span>', false)
            ->assertSee('Chưa đủ điểm để đổi quà')
            ->assertDontSee('name="reward"', false);
    }

    public function test_operator_applies_free_shipping(): void
    {
        $this->givePoints(30);

        $this->actingAs($this->operator)
            ->post($this->url.'/reward', ['reward' => 'free_shipping'])
            ->assertRedirect($this->url)
            ->assertSessionHas('status', 'Đã áp dụng quà đổi điểm.');

        $this->order->refresh();
        $this->assertSame([0, 24000], [$this->order->shipping_fee, $this->order->total]);
        $this->assertSame(0, $this->balance());

        $this->get($this->url)
            ->assertOk()
            ->assertSee('Đã áp dụng: Miễn phí 1 lần giao hàng')
            ->assertSee('-30 điểm')
            ->assertDontSee('name="reward"', false);
    }

    public function test_operator_applies_a_gift_and_the_line_is_tagged(): void
    {
        $this->givePoints(100);

        $this->actingAs($this->operator)
            ->post($this->url.'/reward', ['reward' => 'small_combo', 'products' => [$this->cn->id, $this->cn->id, $this->hl->id]])
            ->assertRedirect($this->url);

        $this->assertSame(3, (int) $this->order->items()->where('is_gift', true)->sum('quantity'));
        $this->get($this->url)->assertOk()->assertSee('Quà đổi điểm')->assertSee('Cải ngọt')->assertSee('Hành lá');
    }

    public function test_empty_gift_selects_are_ignored_and_a_missing_choice_is_refused(): void
    {
        $this->givePoints(100);

        $this->actingAs($this->operator)->followingRedirects()->from($this->url)
            ->post($this->url.'/reward', ['reward' => 'small_bunch', 'products' => ['', '', '']])
            ->assertOk()
            ->assertSee('Quà này gồm đúng 1 gói rau; vui lòng chọn đủ.');
        $this->assertSame(100, $this->balance());

        $this->actingAs($this->operator)
            ->post($this->url.'/reward', ['reward' => 'small_bunch', 'products' => [$this->rm->id, '', '']])
            ->assertRedirect($this->url)
            ->assertSessionHasNoErrors();
        $this->assertSame(80, $this->balance());
    }

    public function test_a_refusal_is_shown_on_the_order_page_and_changes_nothing(): void
    {
        $this->givePoints(10);

        $this->actingAs($this->operator)->followingRedirects()->from($this->url)
            ->post($this->url.'/reward', ['reward' => 'free_shipping'])
            ->assertOk()
            ->assertSee('Khách chưa đủ điểm: có 10, cần 30.');

        $this->assertSame(10000, $this->order->refresh()->shipping_fee);
        $this->assertSame(10, $this->balance());
    }

    public function test_an_unknown_reward_key_is_a_validation_error(): void
    {
        $this->givePoints(100);

        $this->actingAs($this->operator)->post($this->url.'/reward', ['reward' => 'gold_bar'])->assertSessionHasErrors('reward');
        $this->actingAs($this->operator)->post($this->url.'/reward', [])->assertSessionHasErrors('reward');

        $this->assertSame(100, $this->balance());
    }

    public function test_gift_warning_appears_only_when_the_harvest_plan_is_already_confirmed(): void
    {
        $this->givePoints(100);
        $plan = HarvestPlan::factory()->create(['delivery_date' => '2026-10-03', 'harvest_date' => '2026-10-03', 'status' => 'draft']);

        $this->actingAs($this->operator)->get($this->url)->assertOk()->assertDontSee('Kế hoạch thu hoạch ngày này đã chốt');

        $plan->forceFill(['status' => 'confirmed'])->save();

        $this->actingAs($this->operator)->get($this->url)->assertOk()->assertSee('Kế hoạch thu hoạch ngày này đã chốt');
    }

    public function test_an_order_past_confirmation_shows_no_form(): void
    {
        $this->givePoints(100);
        $orders = app(OrderService::class);
        $orders->transition($this->order, OrderStatus::Confirmed);
        $orders->transition($this->order, OrderStatus::Packing);

        $this->actingAs($this->operator)->get($this->url)
            ->assertOk()
            ->assertSee('Chỉ đổi quà được trước khi đóng gói.')
            ->assertDontSee('name="reward"', false);
    }
}
