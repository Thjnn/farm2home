<?php

namespace Tests\Feature\Loyalty;

use App\Models\Customer;
use App\Models\Order;
use App\Models\PointTransaction;
use App\Services\KpiService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsCatalog;
use Tests\TestCase;

class LoyaltyAdminPagesTest extends TestCase
{
    use BuildsCatalog, RefreshDatabase;

    private Customer $lan;

    private Customer $binh;

    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-03 21:00:00');
        $this->buildCatalog();

        $this->lan = Customer::factory()->create(['name' => 'Nguyễn Thị Lan', 'phone' => '0988000001', 'delivery_zone_id' => $this->zone->id]);
        $this->binh = Customer::factory()->create(['name' => 'Trần Văn Bình', 'phone' => '0988000002', 'delivery_zone_id' => $this->zone->id]);
        Customer::factory()->create(['name' => 'Lê Thị Chưa Có Điểm', 'phone' => '0988000003', 'delivery_zone_id' => $this->zone->id]);
        $this->order = Order::factory()->create(['code' => 'DH261003-001', 'customer_id' => $this->lan->id, 'delivery_zone_id' => $this->zone->id]);

        // Lan: +5 +5 +5 +5 rồi đổi bó rau 20 điểm → số dư 0. Bình: +5 từ trước kỳ xem → số dư 5.
        $this->row($this->lan, 5, 'order_delivered', '2026-09-20 18:00:00');
        $this->row($this->lan, 5, 'order_delivered', '2026-09-27 18:00:00');
        $this->row($this->lan, 5, 'packaging_returned', '2026-09-27 19:00:00');
        $this->row($this->lan, 5, 'order_delivered', '2026-10-01 18:00:00');
        $this->row($this->lan, -20, 'redeemed', '2026-10-02 09:00:00', ['order_id' => $this->order->id, 'reward' => 'small_bunch', 'created_by' => $this->operator->id]);
        $this->row($this->binh, 5, 'order_delivered', '2026-08-15 18:00:00');
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function row(Customer $customer, int $points, string $reason, string $at, array $extra = []): PointTransaction
    {
        return PointTransaction::factory()->create(['customer_id' => $customer->id, 'points' => $points, 'reason' => $reason, 'created_at' => $at, 'updated_at' => $at] + $extra);
    }

    /**
     * @return array{points_earned: int, points_redeemed: int, redemptions: int}
     */
    private function kpi(string $from, string $to): array
    {
        return app(KpiService::class)->loyalty(CarbonImmutable::parse($from), CarbonImmutable::parse($to));
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get('/admin/loyalty')->assertRedirect('/login');
        $this->get('/admin/loyalty/'.$this->lan->id)->assertRedirect('/login');
    }

    public function test_index_lists_customers_with_points_highest_balance_first(): void
    {
        $response = $this->actingAs($this->operator)->get('/admin/loyalty')
            ->assertOk()
            ->assertSee('Điểm thành viên')
            ->assertSeeInOrder(['Trần Văn Bình', '0988000002', 'Nguyễn Thị Lan', '0988000001'])
            ->assertDontSee('Lê Thị Chưa Có Điểm');

        $rows = collect($response->viewData('customers')->items())
            ->map(fn (Customer $customer) => [$customer->name, (int) $customer->earned, (int) $customer->balance])
            ->all();
        $this->assertSame([['Trần Văn Bình', 5, 5], ['Nguyễn Thị Lan', 20, 0]], $rows);
    }

    public function test_show_lists_the_history_newest_first(): void
    {
        $this->actingAs($this->operator)->get('/admin/loyalty/'.$this->lan->id)
            ->assertOk()
            ->assertSee('Điểm của Nguyễn Thị Lan')
            ->assertSee('Số dư: <span class="font-semibold">0 điểm</span>', false)
            ->assertSeeInOrder([
                '02/10/2026', 'Đổi quà', 'DH261003-001', 'Tặng bó rau nhỏ', '-20', 'Operator',
                '01/10/2026', 'Đơn đã giao', '+5',
                '27/09/2026', 'Trả bao bì', '+5',
                '27/09/2026', 'Đơn đã giao', '+5',
                '20/09/2026', 'Đơn đã giao', '+5',
            ]);
    }

    public function test_menu_has_the_loyalty_entry(): void
    {
        $this->actingAs($this->operator)->get('/admin/orders')->assertOk()->assertSee('href="'.route('admin.loyalty.index').'"', false);
    }

    public function test_dashboard_shows_points_for_the_period(): void
    {
        $this->actingAs($this->operator)->get('/admin?from=2026-09-10&to=2026-10-03')
            ->assertOk()
            ->assertSee('Điểm đã cộng')
            ->assertSee('Điểm đã đổi')
            ->assertSee('1 lượt đổi quà');

        $this->assertSame(['points_earned' => 20, 'points_redeemed' => 20, 'redemptions' => 1], $this->kpi('2026-09-10', '2026-10-03'));
    }

    public function test_a_refund_nets_out_of_the_redeemed_numbers(): void
    {
        $this->row($this->lan, 20, 'refunded', '2026-10-03 08:00:00', ['order_id' => $this->order->id, 'reward' => 'small_bunch']);

        $this->assertSame(['points_earned' => 20, 'points_redeemed' => 0, 'redemptions' => 0], $this->kpi('2026-09-10', '2026-10-03'));
    }

    public function test_period_boundaries_are_inclusive_and_an_empty_period_gives_zeros(): void
    {
        $this->assertSame(['points_earned' => 5, 'points_redeemed' => 0, 'redemptions' => 0], $this->kpi('2026-09-20', '2026-09-20'));
        $this->assertSame(['points_earned' => 0, 'points_redeemed' => 0, 'redemptions' => 0], $this->kpi('2026-11-01', '2026-11-07'));
    }
}
