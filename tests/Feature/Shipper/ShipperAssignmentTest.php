<?php

namespace Tests\Feature\Shipper;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\DeliveryZone;
use App\Models\HarvestPlan;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderService;
use App\Services\ShipperAssignmentService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsCatalog;
use Tests\TestCase;

class ShipperAssignmentTest extends TestCase
{
    use BuildsCatalog, RefreshDatabase;

    private ShipperAssignmentService $shippers;

    private CarbonImmutable $date;

    private User $binh;

    private User $an;

    private DeliveryZone $zone2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-03 07:00:00');
        $this->buildCatalog();
        $this->shippers = app(ShipperAssignmentService::class);
        $this->date = CarbonImmutable::parse('2026-10-03');
        $this->binh = User::factory()->create(['name' => 'Bình', 'role' => UserRole::Shipper]);
        $this->an = User::factory()->create(['name' => 'An', 'role' => UserRole::Shipper]);
        $this->zone2 = DeliveryZone::factory()->create(['name' => 'Cụm 2 – Đạo Thạnh', 'pickup_point' => null, 'is_active' => true]);
    }

    private function order(DeliveryZone $zone, string $status = 'packing', array $extra = []): Order
    {
        return Order::factory()->create([
            'delivery_zone_id' => $zone->id, 'delivery_date' => '2026-10-03', 'status' => $status,
        ] + $extra);
    }

    public function test_zones_are_split_between_working_shippers(): void
    {
        $a = [$this->order($this->zone), $this->order($this->zone), $this->order($this->zone)];
        $b = [$this->order($this->zone2), $this->order($this->zone2)];

        $this->shippers->assign($this->date);

        $this->assertSame([$this->binh->id], collect($a)->map(fn ($o) => $o->fresh()->shipper_id)->unique()->values()->all());
        $this->assertSame([$this->an->id], collect($b)->map(fn ($o) => $o->fresh()->shipper_id)->unique()->values()->all());
    }

    public function test_new_order_follows_the_shipper_already_on_its_zone(): void
    {
        $this->order($this->zone2, 'packing', ['shipper_id' => $this->an->id]);
        $this->order($this->zone2, 'packing', ['shipper_id' => $this->an->id]);
        $this->order($this->zone2, 'packing', ['shipper_id' => $this->binh->id]);
        $new = $this->order($this->zone2);

        $this->shippers->assign($this->date);

        $this->assertSame($this->an->id, $new->fresh()->shipper_id);
    }

    public function test_pickup_and_pending_orders_are_not_assigned(): void
    {
        $pickup = $this->order($this->pickupZone, 'packing', ['delivery_method' => 'pickup', 'delivery_address' => null]);
        $pending = $this->order($this->zone, 'pending');

        $this->shippers->assign($this->date);

        $this->assertNull($pickup->fresh()->shipper_id);
        $this->assertNull($pending->fresh()->shipper_id);
    }

    public function test_shipper_off_that_day_gets_nothing(): void
    {
        $this->an->update(['off_date' => '2026-10-03']);
        $orders = [$this->order($this->zone), $this->order($this->zone2)];

        $this->shippers->assign($this->date);

        foreach ($orders as $order) {
            $this->assertSame($this->binh->id, $order->fresh()->shipper_id);
        }
    }

    public function test_day_off_moves_orders_still_in_the_warehouse_but_keeps_the_ones_out_for_delivery(): void
    {
        $packing = $this->order($this->zone, 'packing', ['shipper_id' => $this->an->id]);
        $delivering = $this->order($this->zone, 'delivering', ['shipper_id' => $this->an->id]);

        $this->shippers->setDayOff($this->an, $this->date, true);

        $this->assertSame('2026-10-03', $this->an->fresh()->off_date->toDateString());
        $this->assertSame($this->binh->id, $packing->fresh()->shipper_id);
        $this->assertSame($this->an->id, $delivering->fresh()->shipper_id);

        $this->shippers->setDayOff($this->an, $this->date, false);
        $this->assertNull($this->an->fresh()->off_date);
    }

    public function test_nobody_working_leaves_orders_unassigned(): void
    {
        $this->binh->update(['off_date' => '2026-10-03']);
        $order = $this->order($this->zone, 'packing', ['shipper_id' => $this->an->id]);

        $this->shippers->setDayOff($this->an, $this->date, true);

        $this->assertNull($order->fresh()->shipper_id);
    }

    public function test_rebalance_redistributes_warehouse_orders_only(): void
    {
        $delivering = $this->order($this->zone, 'delivering', ['shipper_id' => $this->an->id]);
        $o1 = $this->order($this->zone, 'confirmed', ['shipper_id' => $this->an->id]);
        $o2 = $this->order($this->zone2, 'packing', ['shipper_id' => $this->an->id]);

        $this->shippers->rebalance($this->date);

        $this->assertSame($this->an->id, $delivering->fresh()->shipper_id);
        $this->assertSame($this->an->id, $o1->fresh()->shipper_id);
        $this->assertSame($this->binh->id, $o2->fresh()->shipper_id);
    }

    public function test_assign_zone_overrides_every_open_order_of_that_zone(): void
    {
        $open = $this->order($this->zone, 'delivering', ['shipper_id' => $this->an->id]);
        $done = $this->order($this->zone, 'delivered', ['shipper_id' => $this->an->id]);

        $this->shippers->assignZone($this->date, $this->zone->id, $this->binh->id);

        $this->assertSame($this->binh->id, $open->fresh()->shipper_id);
        $this->assertSame($this->an->id, $done->fresh()->shipper_id);
    }

    public function test_order_moving_to_packing_gets_a_shipper(): void
    {
        HarvestPlan::factory()->create(['delivery_date' => '2026-10-03', 'harvest_date' => '2026-10-03', 'status' => 'confirmed']);
        $order = $this->order($this->zone, 'confirmed');

        $packed = app(OrderService::class)->transition($order, OrderStatus::Packing);

        $this->assertSame($this->binh->id, $packed->shipper_id);
        $this->assertSame($this->binh->id, $order->fresh()->shipper_id);
    }
}
