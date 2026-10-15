<?php

namespace Tests\Feature\Admin;

use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Enums\SupplySource;
use App\Models\Feedback;
use App\Models\Order;
use App\Models\SupplyReport;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsCatalog;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use BuildsCatalog, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-03 09:00:00');
        $this->buildCatalog();
    }

    private function place(string $phone): Order
    {
        return app(OrderService::class)->place([
            'name' => 'Nguyễn Thị Lan', 'phone' => $phone, 'address' => '129 Rạch Gầm',
            'delivery_zone_id' => $this->zone->id, 'delivery_date' => '2026-10-04', 'time_slot' => '17:00-19:00',
            'order_type' => 'basket', 'basket_id' => $this->basket->id, 'items' => null,
            'payment_method' => 'cod', 'data_consent' => true,
        ], OrderChannel::Zalo);
    }

    public function test_reports_what_happened_since_the_page_was_opened(): void
    {
        $oldOrder = $this->place('0988000001');
        $this->travel(1)->minutes();
        $since = now()->getTimestamp();
        $this->travel(10)->seconds();

        $newOrder = $this->place('0988000002');
        $oldOrder->update(['status' => OrderStatus::Confirmed]);
        SupplyReport::factory()->create(['farmer_id' => $this->nh01->id, 'product_id' => $this->rm->id, 'source' => SupplySource::Form]);
        SupplyReport::factory()->create(['farmer_id' => $this->nh02->id, 'product_id' => $this->cn->id, 'source' => SupplySource::Operator]);
        Feedback::factory()->create(['rating' => 4, 'comment' => 'Rau tươi']);

        $response = $this->actingAs($this->operator)->getJson("/admin/notifications?since={$since}")->assertOk();

        $texts = collect($response->json('events'))->pluck('text');
        $this->assertTrue($texts->contains("Đơn mới {$newOrder->code} · Nguyễn Thị Lan · giao 04/10"));
        $this->assertTrue($texts->contains("Đơn {$oldOrder->code} → Đã xác nhận"));
        $this->assertTrue($texts->contains("Nông hộ {$this->nh01->name} vừa báo sản lượng (1 dòng)"));
        $this->assertFalse($texts->contains(fn (string $text) => str_contains($text, $this->nh02->name)), 'Operator tự nhập thì không báo.');
        $this->assertTrue($texts->contains('Phản hồi mới: 4★ · Rau tươi'));
        $this->assertSame(1, $response->json('badges.orders'));

        // Lần hỏi sau bắt đầu từ mốc mới: không báo lại.
        $this->getJson('/admin/notifications?since='.$response->json('now'))->assertOk()->assertJsonPath('events', []);
    }

    public function test_menu_shows_pending_order_badge_and_guests_are_blocked(): void
    {
        $this->place('0988000001');

        $this->actingAs($this->operator)->get('/admin')->assertOk()
            ->assertSee('adminNotifications', false)
            ->assertSee('x-text="badges.orders"', false);

        auth()->logout();
        $this->get('/admin/notifications?since=0')->assertRedirect('/login');
    }
}
