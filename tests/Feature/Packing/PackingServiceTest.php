<?php

namespace Tests\Feature\Packing;

use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Models\Batch;
use App\Models\Order;
use App\Models\Product;
use App\Services\BatchService;
use App\Services\OrderService;
use App\Services\PackingService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Tests\Support\BuildsCatalog;
use Tests\TestCase;

class PackingServiceTest extends TestCase
{
    use BuildsCatalog, RefreshDatabase;

    private PackingService $packing;

    private CarbonImmutable $date;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-03 07:00:00');
        $this->buildCatalog();
        $this->packing = app(PackingService::class);
        $this->date = CarbonImmutable::parse('2026-10-03');
    }

    /**
     * @param  array<int, array{product_id: int, quantity: int}>  $items
     */
    private function order(string $phone, array $items, bool $basket = false): Order
    {
        $order = app(OrderService::class)->place([
            'name' => 'Khách '.$phone, 'phone' => $phone, 'address' => '1 Lê Lợi',
            'delivery_zone_id' => $this->zone->id, 'delivery_date' => '2026-10-03', 'time_slot' => '17:00-19:00',
            'order_type' => $basket ? 'basket' : 'single', 'basket_id' => $basket ? $this->basket->id : null,
            'items' => $items, 'payment_method' => 'cod', 'data_consent' => true,
        ], OrderChannel::Zalo);

        return app(OrderService::class)->transition($order, OrderStatus::Confirmed);
    }

    private function batch(Product $product, string $code, float $kg, string $bestBefore = '2026-10-05'): Batch
    {
        return Batch::factory()->create([
            'code' => $code, 'farmer_id' => $this->nh01->id, 'product_id' => $product->id, 'quantity_kg' => $kg,
            'harvested_at' => '2026-10-03 05:30:00', 'best_before' => $bestBefore, 'quality_status' => 'passed',
        ]);
    }

    public function test_one_bag_per_product_and_batch_merging_basket_and_single_lines(): void
    {
        $order = $this->order('0988000001', [], basket: true);
        $order->items()->create([
            'product_id' => $this->rm->id, 'quantity' => 2, 'weight_kg' => 1.0, 'unit_price' => 12000, 'line_total' => 24000,
        ]);
        $this->batch($this->rm, 'F2H-261003-RM-NH01', 5.0);
        app(BatchService::class)->allocate($this->date);

        $bags = $this->packing->bagsFor($order->fresh());

        $rmBags = $bags->where('product.id', $this->rm->id);
        $this->assertCount(1, $rmBags);
        $this->assertSame(3, $rmBags->first()['packs']);
        $this->assertSame(1.5, $rmBags->first()['kg']);
    }

    public function test_two_batches_of_one_product_make_two_bags(): void
    {
        $order = $this->order('0988000001', [['product_id' => $this->rm->id, 'quantity' => 3]]);
        $this->batch($this->rm, 'F2H-261002-RM-NH01', 0.5, '2026-10-04');
        $this->batch($this->rm, 'F2H-261003-RM-NH01', 5.0);
        app(BatchService::class)->allocate($this->date);

        $bags = $this->packing->bagsFor($order->fresh());

        $this->assertSame([1, 2], $bags->pluck('packs')->all());
        $this->assertCount(2, $bags->pluck('key')->unique());
        $this->assertFalse($bags->contains('short', true));
    }

    public function test_unallocated_packs_show_as_a_short_bag_and_block_fully_packed(): void
    {
        $order = $this->order('0988000001', [['product_id' => $this->rm->id, 'quantity' => 3]]);
        $this->batch($this->rm, 'F2H-261003-RM-NH01', 1.0);
        app(BatchService::class)->allocate($this->date);

        $bags = $this->packing->bagsFor($order->fresh());

        $this->assertSame([2, 1], $bags->pluck('packs')->all());
        $this->assertTrue($bags->last()['short']);
        $this->assertNull($bags->last()['batch']);
        $this->assertSame('short-'.$this->rm->id, $bags->last()['key']);
        $this->assertFalse($this->packing->isFullyPacked($order->fresh()));
    }

    public function test_order_is_fully_packed_only_when_every_bag_is_ticked(): void
    {
        $order = $this->order('0988000001', [['product_id' => $this->rm->id, 'quantity' => 2]]);
        $this->batch($this->rm, 'F2H-261003-RM-NH01', 5.0);
        app(BatchService::class)->allocate($this->date);
        $this->assertFalse($this->packing->isFullyPacked($order->fresh()));

        $order->items[0]->batches()->updateExistingPivot($order->items[0]->batches()->first()->id, ['packed_at' => now()]);

        $this->assertTrue($this->packing->isFullyPacked($order->fresh()));
    }

    public function test_pick_list_sums_packs_per_product_for_the_day(): void
    {
        $this->order('0988000001', [['product_id' => $this->rm->id, 'quantity' => 2]]);
        $this->order('0988000002', [['product_id' => $this->rm->id, 'quantity' => 1], ['product_id' => $this->cn->id, 'quantity' => 1]]);
        $this->batch($this->rm, 'F2H-261003-RM-NH01', 5.0);
        app(BatchService::class)->allocate($this->date);

        $list = $this->packing->pickList($this->date);

        $this->assertSame([$this->rm->id, $this->cn->id], $list->pluck('product.id')->all());
        $this->assertSame(['packs' => 3, 'allocated' => 3, 'short' => 0], Arr::only($list->firstWhere('product.id', $this->rm->id), ['packs', 'allocated', 'short']));
        $this->assertSame(1.5, $list->firstWhere('product.id', $this->rm->id)['kg']);
        $this->assertSame(1, $list->firstWhere('product.id', $this->cn->id)['short']);
    }

    public function test_orders_lists_only_confirmed_and_packing_orders_of_the_day(): void
    {
        $confirmed = $this->order('0988000001', [['product_id' => $this->rm->id, 'quantity' => 1]]);
        $cancelled = $this->order('0988000002', [['product_id' => $this->rm->id, 'quantity' => 1]]);
        app(OrderService::class)->transition($cancelled, OrderStatus::Cancelled, 'Khách đổi ý');

        $this->assertSame([$confirmed->id], $this->packing->orders($this->date)->pluck('id')->all());
    }
}
