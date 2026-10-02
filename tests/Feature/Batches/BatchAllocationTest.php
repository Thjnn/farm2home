<?php

namespace Tests\Feature\Batches;

use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Enums\WasteReason;
use App\Models\Batch;
use App\Models\Order;
use App\Models\Subscription;
use App\Services\BatchService;
use App\Services\OrderService;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsCatalog;
use Tests\TestCase;

class BatchAllocationTest extends TestCase
{
    use BuildsCatalog, RefreshDatabase;

    private BatchService $batches;

    private OrderService $orders;

    private CarbonImmutable $date;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-03 07:00:00');
        $this->buildCatalog();
        $this->batches = app(BatchService::class);
        $this->orders = app(OrderService::class);
        $this->date = CarbonImmutable::parse('2026-10-03');
    }

    private function order(string $phone, int $rmPacks, bool $confirm = true): Order
    {
        $order = $this->orders->place([
            'name' => 'Khách '.$phone, 'phone' => $phone, 'address' => '1 Lê Lợi',
            'delivery_zone_id' => $this->zone->id, 'delivery_date' => '2026-10-03', 'time_slot' => '17:00-19:00',
            'order_type' => 'single', 'items' => [['product_id' => $this->rm->id, 'quantity' => $rmPacks]],
            'payment_method' => 'cod', 'data_consent' => true,
        ], OrderChannel::Zalo);

        return $confirm ? $this->orders->transition($order, OrderStatus::Confirmed) : $order;
    }

    private function batch(string $code, float $kg, string $bestBefore = '2026-10-05', string $quality = 'passed', string $harvestedAt = '2026-10-03 05:30:00'): Batch
    {
        return Batch::factory()->create([
            'code' => $code, 'farmer_id' => $this->nh01->id, 'product_id' => $this->rm->id, 'quantity_kg' => $kg,
            'harvested_at' => $harvestedAt, 'best_before' => $bestBefore, 'quality_status' => $quality,
        ]);
    }

    public function test_packs_are_allocated_and_shortage_is_reported(): void
    {
        $order = $this->order('0988000001', 14);
        $batch = $this->batch('F2H-261003-RM-NH01', 5.0);

        $result = $this->batches->allocate($this->date);

        // 5,0 kg = 10 gói 0,5 kg; đơn cần 14 gói.
        $this->assertSame(['allocated_packs' => 10, 'short_packs' => 4], $result);
        $this->assertSame(10, (int) $order->items[0]->batches()->sum('order_item_batches.quantity'));
        $this->assertSame(0.0, $this->batches->remainingKg($batch));
    }

    public function test_running_twice_does_not_allocate_twice(): void
    {
        $this->order('0988000001', 4);
        $batch = $this->batch('F2H-261003-RM-NH01', 5.0);

        $this->batches->allocate($this->date);
        $second = $this->batches->allocate($this->date);

        $this->assertSame(['allocated_packs' => 0, 'short_packs' => 0], $second);
        $this->assertSame(3.0, $this->batches->remainingKg($batch));
    }

    public function test_batch_expiring_first_is_used_first_and_an_item_can_span_batches(): void
    {
        $order = $this->order('0988000001', 6);
        $fresh = $this->batch('F2H-261003-RM-NH01', 5.0, '2026-10-05');
        $older = $this->batch('F2H-261002-RM-NH01', 1.0, '2026-10-04', 'passed', '2026-10-02 05:30:00');

        $this->batches->allocate($this->date);

        $pivot = $order->items[0]->batches()->pluck('order_item_batches.quantity', 'batches.code')->all();
        // assertEquals: không phụ thuộc thứ tự dòng trả về.
        $this->assertEquals(['F2H-261002-RM-NH01' => 2, 'F2H-261003-RM-NH01' => 4], $pivot);
        $this->assertSame([0.0, 3.0], [$this->batches->remainingKg($older), $this->batches->remainingKg($fresh)]);
    }

    public function test_subscription_orders_are_served_first(): void
    {
        $walkIn = $this->order('0988000001', 6);
        $subscriber = $this->order('0988000002', 6);
        $subscription = Subscription::factory()->create([
            'customer_id' => $subscriber->customer_id, 'basket_id' => $this->basket->id, 'frequency' => 'weekly',
            'delivery_weekday' => 6, 'time_slot' => '17:00-19:00', 'start_date' => '2026-09-01', 'status' => 'active',
        ]);
        $subscriber->update(['subscription_id' => $subscription->id]);
        $this->batch('F2H-261003-RM-NH01', 4.0);

        $result = $this->batches->allocate($this->date);

        $this->assertSame(['allocated_packs' => 8, 'short_packs' => 4], $result);
        $this->assertSame(6, (int) $subscriber->items[0]->batches()->sum('order_item_batches.quantity'));
        $this->assertSame(2, (int) $walkIn->items[0]->batches()->sum('order_item_batches.quantity'));
    }

    public function test_pending_orders_rejected_or_expired_batches_are_skipped(): void
    {
        $this->order('0988000001', 4, confirm: false);
        $confirmed = $this->order('0988000002', 4);
        $this->batch('F2H-261003-RM-NH01', 5.0, '2026-10-05', 'rejected');
        $this->batch('F2H-261001-RM-NH01', 5.0, '2026-10-02', 'passed', '2026-10-01 05:30:00');
        $this->batch('F2H-261003-RM-NH02', 5.0, '2026-10-05', 'pending');

        $result = $this->batches->allocate($this->date);

        $this->assertSame(['allocated_packs' => 0, 'short_packs' => 4], $result);
        $this->assertSame(0, $confirmed->items[0]->batches()->count());
    }

    public function test_cancelling_an_order_returns_its_packs_to_the_batch(): void
    {
        $order = $this->order('0988000001', 4);
        $batch = $this->batch('F2H-261003-RM-NH01', 5.0);
        $this->batches->allocate($this->date);
        $this->assertSame(3.0, $this->batches->remainingKg($batch));

        $this->orders->transition($order, OrderStatus::Cancelled, 'Khách đổi lịch');

        $this->assertSame(5.0, $this->batches->remainingKg($batch));
    }

    public function test_delivery_records_what_was_actually_allocated(): void
    {
        $order = $this->order('0988000001', 14);
        $this->batch('F2H-261003-RM-NH01', 5.0);
        $this->batches->allocate($this->date);

        foreach ([OrderStatus::Packing, OrderStatus::Delivering, OrderStatus::Delivered] as $status) {
            $this->orders->transition($order, $status);
        }

        $this->assertSame([14, 10], [$order->items[0]->refresh()->quantity, $order->items[0]->fulfilled_quantity]);
    }

    public function test_waste_cannot_exceed_what_is_left_in_the_batch(): void
    {
        $this->order('0988000001', 4);
        $batch = $this->batch('F2H-261003-RM-NH01', 5.0);
        $this->batches->allocate($this->date);

        $log = $this->batches->logWaste($batch, 1.0, WasteReason::Discounted, $this->operator, 'Bán giảm giá cuối ngày');

        $this->assertSame(['1.00', WasteReason::Discounted, '2026-10-03'], [$log->quantity_kg, $log->reason, $log->log_date->toDateString()]);
        $this->assertSame(2.0, $this->batches->remainingKg($batch));

        try {
            $this->batches->logWaste($batch, 2.5, WasteReason::Surplus, $this->operator);
            $this->fail('Expected DomainException');
        } catch (DomainException $e) {
            $this->assertSame('Lô này chỉ còn 2 kg.', $e->getMessage());
        }
    }
}
