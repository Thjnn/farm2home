<?php

namespace Tests\Feature\Orders;

use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsCatalog;
use Tests\TestCase;

class PublicOrderFormTest extends TestCase
{
    use BuildsCatalog, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-02 10:00:00');
        $this->buildCatalog();
    }

    private function form(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Nguyễn Thị Lan',
            'phone' => '0988000001',
            'address' => '129 Rạch Gầm',
            'delivery_zone_id' => $this->zone->id,
            'delivery_date' => '2026-10-03',
            'time_slot' => '17:00-19:00',
            'order_type' => 'single',
            'items' => [['product_id' => $this->rm->id, 'quantity' => 2]],
            'payment_method' => 'cod',
            'data_consent' => '1',
        ], $overrides);
    }

    public function test_home_page_shows_baskets_and_makes_no_marketing_claims(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Điều phối nông sản theo nhu cầu')
            ->assertSee('Giỏ Mini')
            ->assertSee('45.000đ')
            ->assertSee('Đặt hàng')
            ->assertDontSee('100%');
    }

    public function test_home_and_order_form_show_product_photos(): void
    {
        $this->rm->update(['image' => 'products/rau-muong.jpg']);

        $this->get('/')->assertOk()->assertSee('images/products/rau-muong.jpg');
        $this->get('/dat-hang')->assertOk()->assertSee('images/products/rau-muong.jpg');
    }

    public function test_order_form_lists_catalog_and_allowed_dates(): void
    {
        $this->get('/dat-hang')
            ->assertOk()
            ->assertSee('Giỏ Mini')
            ->assertSee('Rau muống')
            ->assertSee('12.000đ')
            ->assertSee('Cụm 1 – Trung tâm')
            ->assertSee('17:00-19:00')
            ->assertSee('value="2026-10-03"', false)
            ->assertSee('value="2026-10-09"', false)
            ->assertDontSee('value="2026-10-02"', false)
            ->assertDontSee('value="2026-10-10"', false);
    }

    public function test_valid_order_is_stored_and_customer_sees_the_code(): void
    {
        $this->post('/dat-hang', $this->form())->assertRedirect('/dat-hang/cam-on');

        $order = Order::firstOrFail();
        $this->assertSame('DH261003-001', $order->code);
        $this->assertSame('website', $order->channel->value);

        $this->get('/dat-hang/cam-on')
            ->assertOk()
            ->assertSee('DH261003-001')
            ->assertSee('34.000đ')
            ->assertSee('03/10/2026')
            ->assertDontSee('129 Rạch Gầm')
            ->assertDontSee('0988000001');
    }

    public function test_order_after_cutoff_for_tomorrow_is_rejected(): void
    {
        $this->travelTo('2026-10-02 18:30:00');

        $this->post('/dat-hang', $this->form())->assertSessionHasErrors('delivery_date');
        $this->assertSame(0, Order::count());

        $this->post('/dat-hang', $this->form(['delivery_date' => '2026-10-04']))->assertRedirect('/dat-hang/cam-on');
        $this->assertSame(1, Order::count());
    }

    public function test_date_beyond_the_window_is_rejected(): void
    {
        $this->post('/dat-hang', $this->form(['delivery_date' => '2026-10-10']))->assertSessionHasErrors('delivery_date');
    }

    public function test_phone_must_be_ten_digits_starting_with_zero(): void
    {
        foreach (['12345', '988000001', '09880000011', '0988 000 001'] as $phone) {
            $this->post('/dat-hang', $this->form(['phone' => $phone]))->assertSessionHasErrors('phone');
        }
    }

    public function test_consent_is_required(): void
    {
        $this->post('/dat-hang', $this->form(['data_consent' => null]))->assertSessionHasErrors('data_consent');
    }

    public function test_address_is_required_for_home_delivery_but_not_for_pickup(): void
    {
        $this->post('/dat-hang', $this->form(['address' => '']))->assertSessionHasErrors('address');

        $this->post('/dat-hang', $this->form(['address' => '', 'delivery_zone_id' => $this->pickupZone->id]))
            ->assertRedirect('/dat-hang/cam-on');
    }

    public function test_unknown_time_slot_and_missing_basket_are_rejected(): void
    {
        $this->post('/dat-hang', $this->form(['time_slot' => '02:00-03:00']))->assertSessionHasErrors('time_slot');
        $this->post('/dat-hang', $this->form(['order_type' => 'basket', 'items' => null]))->assertSessionHasErrors('basket_id');
    }

    public function test_basket_order_through_the_form(): void
    {
        $this->post('/dat-hang', $this->form(['order_type' => 'basket', 'basket_id' => $this->basket->id, 'items' => null]))
            ->assertRedirect('/dat-hang/cam-on');

        $this->assertSame(55000, Order::firstOrFail()->total);
    }

    public function test_a_quantity_box_left_blank_counts_as_zero(): void
    {
        $this->post('/dat-hang', $this->form(['items' => [
            ['product_id' => $this->rm->id, 'quantity' => 2],
            ['product_id' => $this->cn->id, 'quantity' => ''],
        ]]))->assertRedirect('/dat-hang/cam-on');

        $order = Order::firstOrFail();
        $this->assertSame([$this->rm->id], $order->items->pluck('product_id')->all());
        $this->assertSame(34000, $order->total);
    }

    public function test_quantity_errors_are_shown_next_to_the_product_list(): void
    {
        // Đi theo redirect như trình duyệt: assertSessionHasErrors() ở giữa sẽ nạp lại session và làm mất túi lỗi của request sau.
        $this->from('/dat-hang')
            ->followingRedirects()
            ->post('/dat-hang', $this->form(['items' => [['product_id' => $this->rm->id, 'quantity' => 99]]]))
            ->assertOk()
            ->assertSee('Số gói không được lớn hơn 20.');

        $this->assertSame(0, Order::count());
    }

    public function test_thanks_page_without_an_order_goes_home(): void
    {
        $this->get('/dat-hang/cam-on')->assertRedirect('/');
    }
}
