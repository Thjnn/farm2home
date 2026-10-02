<?php

namespace Tests\Feature\Orders;

use App\Enums\OrderChannel;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsCatalog;
use Tests\TestCase;

class OrderLookupTest extends TestCase
{
    use BuildsCatalog, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-02 10:00:00');
        $this->buildCatalog();

        foreach (['2026-10-03', '2026-10-04', '2026-10-05', '2026-10-06', '2026-10-07', '2026-10-08'] as $date) {
            app(OrderService::class)->place([
                'name' => 'Nguyễn Thị Lan', 'phone' => '0988000001', 'address' => '129 Rạch Gầm',
                'delivery_zone_id' => $this->zone->id, 'delivery_date' => $date, 'time_slot' => '17:00-19:00',
                'order_type' => 'single', 'items' => [['product_id' => $this->rm->id, 'quantity' => 2]],
                'payment_method' => 'cod', 'data_consent' => true,
            ], OrderChannel::Website);
        }
    }

    public function test_lookup_shows_the_five_latest_orders_without_personal_data(): void
    {
        $this->post('/tra-cuu', ['phone' => '0988000001'])
            ->assertOk()
            ->assertSee('DH261008-001')
            ->assertSee('DH261004-001')
            ->assertDontSee('DH261003-001')
            ->assertSee('Chờ xác nhận')
            ->assertSee('34.000đ')
            ->assertDontSee('129 Rạch Gầm')
            ->assertDontSee('Nguyễn Thị Lan');
    }

    public function test_unknown_phone_gets_a_neutral_message(): void
    {
        $this->post('/tra-cuu', ['phone' => '0977000000'])
            ->assertOk()
            ->assertSee('Không tìm thấy đơn nào với số điện thoại này.');
    }

    public function test_phone_format_is_validated(): void
    {
        $this->post('/tra-cuu', ['phone' => 'abc'])->assertSessionHasErrors('phone');
    }

    public function test_lookup_page_renders(): void
    {
        $this->get('/tra-cuu')->assertOk()->assertSee('Tra cứu đơn');
    }
}
