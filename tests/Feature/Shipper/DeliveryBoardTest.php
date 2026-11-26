<?php

namespace Tests\Feature\Shipper;

use App\Enums\UserRole;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsCatalog;
use Tests\TestCase;

class DeliveryBoardTest extends TestCase
{
    use BuildsCatalog, RefreshDatabase;

    private User $binh;

    private User $an;

    private DeliveryZone $zone2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-03 09:00:00');
        $this->buildCatalog();
        $this->binh = User::factory()->create(['name' => 'Anh Bình', 'role' => UserRole::Shipper]);
        $this->an = User::factory()->create(['name' => 'Chị An', 'role' => UserRole::Shipper]);
        $this->zone2 = DeliveryZone::factory()->create(['name' => 'Cụm 2 – Đạo Thạnh', 'pickup_point' => null, 'is_active' => true]);
    }

    private function order(DeliveryZone $zone, ?User $shipper, string $status = 'packing', array $extra = []): Order
    {
        return Order::factory()->create([
            'delivery_zone_id' => $zone->id, 'delivery_date' => '2026-10-03', 'status' => $status, 'shipper_id' => $shipper?->id,
        ] + $extra);
    }

    public function test_board_shows_each_shipper_their_zones_and_unassigned_orders(): void
    {
        $this->order($this->zone, $this->binh);
        $this->order($this->zone, $this->binh, 'delivered');
        $this->order($this->zone2, null);
        $this->order($this->zone2, null, 'delivered');

        $this->actingAs($this->operator)->get(route('admin.delivery.index', ['date' => '2026-10-03']))
            ->assertOk()
            ->assertSee('Anh Bình')->assertSee('Chị An')
            ->assertSee('2 đơn · đã giao 1')
            ->assertSee('Chưa có người giao: 1 đơn');
    }

    public function test_board_highlights_failed_deliveries(): void
    {
        $this->order($this->zone, $this->binh, 'delivering', ['delivery_issue' => 'Không có nhà', 'delivery_issue_at' => now()]);

        $this->actingAs($this->operator)->get(route('admin.delivery.index', ['date' => '2026-10-03']))
            ->assertSee('Không giao được: Không có nhà');
    }

    public function test_day_off_toggle_moves_orders_to_the_other_shipper(): void
    {
        $order = $this->order($this->zone, $this->an);

        $this->actingAs($this->operator)->patch(route('admin.delivery.day-off', $this->an), ['date' => '2026-10-03', 'off' => 1])
            ->assertRedirect(route('admin.delivery.index', ['date' => '2026-10-03']));

        $this->assertSame($this->binh->id, $order->fresh()->shipper_id);
        $this->actingAs($this->operator)->get(route('admin.delivery.index', ['date' => '2026-10-03']))->assertSee('Nghỉ ngày này');
    }

    public function test_rebalance_button(): void
    {
        $a = $this->order($this->zone, $this->an);
        $b = $this->order($this->zone2, $this->an);

        $this->actingAs($this->operator)->post(route('admin.delivery.rebalance'), ['date' => '2026-10-03'])->assertRedirect();

        $this->assertNotSame($a->fresh()->shipper_id, $b->fresh()->shipper_id);
    }

    public function test_operator_sets_shipper_for_a_zone_and_for_one_order(): void
    {
        $a = $this->order($this->zone, $this->an);
        $b = $this->order($this->zone, $this->an);

        $this->actingAs($this->operator)->patch(route('admin.delivery.zone', $this->zone), ['date' => '2026-10-03', 'shipper_id' => $this->binh->id])->assertRedirect();
        $this->assertSame([$this->binh->id, $this->binh->id], [$a->fresh()->shipper_id, $b->fresh()->shipper_id]);

        $this->actingAs($this->operator)->patch(route('admin.delivery.order', $a), ['shipper_id' => $this->an->id])->assertRedirect();
        $this->assertSame($this->an->id, $a->fresh()->shipper_id);

        $this->actingAs($this->operator)->patch(route('admin.delivery.order', $a), ['shipper_id' => ''])->assertRedirect();
        $this->assertNull($a->fresh()->shipper_id);
    }

    public function test_only_shipper_accounts_can_be_chosen(): void
    {
        $order = $this->order($this->zone, $this->an);

        $this->actingAs($this->operator)->patch(route('admin.delivery.order', $order), ['shipper_id' => $this->operator->id])
            ->assertSessionHasErrors('shipper_id');
        $this->assertSame($this->an->id, $order->fresh()->shipper_id);
    }

    public function test_order_detail_shows_shipper_and_failed_delivery(): void
    {
        $order = $this->order($this->zone, $this->binh, 'delivering', ['delivery_issue' => 'Khách từ chối nhận', 'delivery_issue_at' => now()]);

        $this->actingAs($this->operator)->get(route('admin.orders.show', $order))
            ->assertSee('Anh Bình')
            ->assertSee('Khách từ chối nhận');
    }

    public function test_failed_delivery_is_notified_once_without_a_status_toast(): void
    {
        $order = $this->order($this->zone, $this->binh, 'delivering', ['created_at' => now()->subHour()]);
        $since = now()->getTimestamp();
        $this->travel(10)->seconds();
        $order->update(['delivery_issue' => 'Không có nhà', 'delivery_issue_at' => now()]);

        $texts = collect($this->actingAs($this->operator)->getJson("/admin/notifications?since={$since}")->json('events'))->pluck('text');

        $this->assertTrue($texts->contains("Đơn {$order->code} không giao được: Không có nhà"));
        $this->assertFalse($texts->contains("Đơn {$order->code} → Đang giao"));
    }
}
