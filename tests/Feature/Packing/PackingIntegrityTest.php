<?php

namespace Tests\Feature\Packing;

use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Enums\QualityStatus;
use App\Models\Batch;
use App\Models\HarvestPlan;
use App\Models\Order;
use App\Models\Product;
use App\Services\BatchService;
use App\Services\OrderService;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\BuildsCatalog;
use Tests\TestCase;

class PackingIntegrityTest extends TestCase
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
        HarvestPlan::factory()->create(['delivery_date' => '2026-10-03', 'harvest_date' => '2026-10-03', 'status' => 'confirmed']);
    }

    /**
     * @param  array<int, array{product_id: int, quantity: int}>  $items
     */
    private function order(string $phone, array $items): Order
    {
        $order = $this->orders->place([
            'name' => 'Khách '.$phone, 'phone' => $phone, 'address' => '1 Lê Lợi',
            'delivery_zone_id' => $this->zone->id, 'delivery_date' => '2026-10-03', 'time_slot' => '17:00-19:00',
            'order_type' => 'single', 'items' => $items, 'payment_method' => 'cod', 'data_consent' => true,
        ], OrderChannel::Zalo);

        return $this->orders->transition($order, OrderStatus::Confirmed);
    }

    private function batch(Product $product, string $code, float $kg): Batch
    {
        return Batch::factory()->create([
            'code' => $code, 'farmer_id' => $this->nh01->id, 'product_id' => $product->id, 'quantity_kg' => $kg,
            'harvested_at' => '2026-10-03 05:30:00', 'best_before' => '2026-10-05', 'quality_status' => 'passed',
        ]);
    }

    private function packAll(Order $order): void
    {
        DB::table('order_item_batches')->whereIn('order_item_id', $order->items()->pluck('id'))->update(['packed_at' => now()]);
    }

    public function test_reallocating_resets_only_bags_that_got_more_packs(): void
    {
        $order = $this->order('0988000001', [['product_id' => $this->rm->id, 'quantity' => 3], ['product_id' => $this->cn->id, 'quantity' => 1]]);
        $rm = $this->batch($this->rm, 'F2H-261003-RM-NH01', 1.0);
        $cn = $this->batch($this->cn, 'F2H-261003-CN-NH01', 1.0);
        $this->batches->allocate($this->date);
        $this->packAll($order);

        $rm->update(['quantity_kg' => 5.0]);
        $this->batches->allocate($this->date);

        $packedAt = DB::table('order_item_batches')->pluck('packed_at', 'batch_id');
        $this->assertNull($packedAt[$rm->id]);
        $this->assertNotNull($packedAt[$cn->id]);
    }

    public function test_rejecting_a_batch_removes_it_from_orders_not_yet_delivered(): void
    {
        $order = $this->order('0988000001', [['product_id' => $this->rm->id, 'quantity' => 2]]);
        $batch = $this->batch($this->rm, 'F2H-261003-RM-NH01', 5.0);
        $this->batches->allocate($this->date);

        $this->batches->setQuality($batch, QualityStatus::Rejected, 'Dập lá');

        $this->assertSame(0, $order->items[0]->batches()->count());
    }

    public function test_rejecting_a_batch_keeps_it_on_orders_already_out_for_delivery(): void
    {
        $order = $this->order('0988000001', [['product_id' => $this->rm->id, 'quantity' => 2]]);
        $batch = $this->batch($this->rm, 'F2H-261003-RM-NH01', 5.0);
        $this->batches->allocate($this->date);
        $this->packAll($order);
        $this->orders->transition($order, OrderStatus::Packing);
        $this->orders->transition($order, OrderStatus::Delivering);

        $this->batches->setQuality($batch, QualityStatus::Rejected, 'Khách phản ánh');

        $this->assertSame(1, $order->items[0]->batches()->count());
    }

    public function test_an_order_with_unpacked_bags_cannot_go_out_for_delivery(): void
    {
        $order = $this->order('0988000001', [['product_id' => $this->rm->id, 'quantity' => 2]]);
        $this->batch($this->rm, 'F2H-261003-RM-NH01', 5.0);
        $this->batches->allocate($this->date);
        $this->orders->transition($order, OrderStatus::Packing);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Đơn còn túi chưa đóng');

        $this->orders->transition($order, OrderStatus::Delivering);
    }

    public function test_bulk_delivering_counts_unpacked_orders_as_blocked(): void
    {
        $packed = $this->order('0988000001', [['product_id' => $this->rm->id, 'quantity' => 1]]);
        $unpacked = $this->order('0988000002', [['product_id' => $this->rm->id, 'quantity' => 1]]);
        $this->batch($this->rm, 'F2H-261003-RM-NH01', 5.0);
        $this->batches->allocate($this->date);
        $this->packAll($packed);
        $this->orders->transitionMany([$packed, $unpacked], OrderStatus::Packing);

        $result = $this->orders->transitionMany([$packed->fresh(), $unpacked->fresh()], OrderStatus::Delivering);

        $this->assertSame(['moved' => 1, 'skipped' => 0, 'blocked' => 1], $result);
        $this->assertSame(OrderStatus::Packing, $unpacked->fresh()->status);
    }

    public function test_delivery_counts_lines_without_a_batch_as_not_fulfilled(): void
    {
        $order = $this->order('0988000001', [['product_id' => $this->rm->id, 'quantity' => 2], ['product_id' => $this->cn->id, 'quantity' => 1]]);
        $this->batch($this->rm, 'F2H-261003-RM-NH01', 5.0);
        $this->batches->allocate($this->date);
        $this->packAll($order);

        foreach ([OrderStatus::Packing, OrderStatus::Delivering, OrderStatus::Delivered] as $status) {
            $this->orders->transition($order, $status);
        }

        $fulfilled = $order->items()->pluck('fulfilled_quantity', 'product_id');
        $this->assertSame([2, 0], [$fulfilled[$this->rm->id], $fulfilled[$this->cn->id]]);
    }

    public function test_delivery_without_any_batches_keeps_ordered_quantity(): void
    {
        $order = $this->order('0988000001', [['product_id' => $this->rm->id, 'quantity' => 2]]);

        foreach ([OrderStatus::Packing, OrderStatus::Delivering, OrderStatus::Delivered] as $status) {
            $this->orders->transition($order, $status);
        }

        $this->assertSame(2, $order->items[0]->fresh()->fulfilled_quantity);
    }
}
