<?php

namespace Tests\Feature\Orders;

use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Models\Batch;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsCatalog;
use Tests\TestCase;

class OrderFeedbackLinkTest extends TestCase
{
    use BuildsCatalog, RefreshDatabase;

    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-02 10:00:00');
        $this->buildCatalog();

        $this->order = app(OrderService::class)->place([
            'name' => 'Nguyễn Thị Lan', 'phone' => '0988000001', 'address' => '129 Rạch Gầm',
            'delivery_zone_id' => $this->zone->id, 'delivery_date' => '2026-10-03', 'time_slot' => '17:00-19:00',
            'order_type' => 'single', 'items' => [['product_id' => $this->rm->id, 'quantity' => 2], ['product_id' => $this->cn->id, 'quantity' => 1]],
            'payment_method' => 'cod', 'data_consent' => true,
        ], OrderChannel::Zalo);

        $this->attachBatch(0, 'F2H-261003-RM-NH01', $this->rm->id, 'passed');
        $this->attachBatch(1, 'F2H-261003-CN-NH02', $this->cn->id, 'rejected');
    }

    private function attachBatch(int $itemIndex, string $code, int $productId, string $quality): void
    {
        $batch = Batch::factory()->create([
            'code' => $code, 'farmer_id' => $this->nh01->id, 'product_id' => $productId, 'quantity_kg' => 5,
            'harvested_at' => '2026-10-03 05:30:00', 'best_before' => '2026-10-05', 'quality_status' => $quality,
        ]);
        $this->order->items[$itemIndex]->batches()->attach($batch->id, ['quantity' => 1]);
    }

    public function test_delivered_order_links_passed_batches_in_lookup_and_admin_message(): void
    {
        $this->order->update(['status' => OrderStatus::Delivered]);

        $this->post('/tra-cuu', ['phone' => '0988000001'])
            ->assertOk()
            ->assertSee('Đánh giá Rau muống')
            ->assertSee(Batch::where('code', 'F2H-261003-RM-NH01')->firstOrFail()->publicUrl())
            ->assertDontSee('F2H-261003-CN-NH02');

        $this->actingAs($this->operator)->get('/admin/orders/'.$this->order->id)
            ->assertOk()
            ->assertSee('Nhờ khách đánh giá')
            ->assertSee('cảm ơn anh/chị Nguyễn Thị Lan đã nhận đơn '.$this->order->code, false)
            ->assertSee('https://zalo.me/0988000001');
    }

    public function test_undelivered_order_has_no_feedback_links(): void
    {
        $this->post('/tra-cuu', ['phone' => '0988000001'])->assertOk()->assertDontSee('Đánh giá Rau muống');

        $this->actingAs($this->operator)->get('/admin/orders/'.$this->order->id)->assertOk()->assertDontSee('Nhờ khách đánh giá');
    }
}
