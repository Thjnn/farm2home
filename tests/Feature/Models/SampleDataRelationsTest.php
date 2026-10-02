<?php

namespace Tests\Feature\Models;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\SubscriptionFrequency;
use App\Enums\UserRole;
use App\Models\Basket;
use App\Models\Batch;
use App\Models\Customer;
use App\Models\Farmer;
use App\Models\Feedback;
use App\Models\Order;
use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SampleDataRelationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-02 10:00:00');
        $this->seed(DemoSeeder::class);
    }

    public function test_order_casts_and_relations(): void
    {
        $order = Order::where('code', 'DH260911-003')->firstOrFail();

        $this->assertSame(OrderType::Basket, $order->order_type);
        $this->assertSame(OrderStatus::Delivered, $order->status);
        $this->assertSame(125000, $order->total);
        $this->assertSame('2026-09-11', $order->delivery_date->toDateString());
        $this->assertSame('Nguyễn Thị Lan', $order->customer->name);
        $this->assertSame('Cụm 1 – Trung tâm Mỹ Tho', $order->zone->name);
        $this->assertGreaterThan(0, $order->items->count());
        $this->assertSame(3, $order->items->first()->basket_id);
    }

    public function test_farmer_products_and_certificate(): void
    {
        $nh02 = Farmer::where('code', 'NH02')->firstOrFail();
        $nh01 = Farmer::where('code', 'NH01')->firstOrFail();

        $this->assertCount(4, $nh02->products);
        $this->assertSame('35.00', $nh02->products->firstWhere('code', 'CN')->pivot->typical_weekly_kg);
        $this->assertTrue($nh02->hasValidCertificate());
        $this->assertFalse($nh01->hasValidCertificate());
        $this->assertSame('https://zalo.me/0911000001', $nh01->zaloLink());
    }

    public function test_certificate_is_invalid_after_expiry(): void
    {
        $nh02 = Farmer::where('code', 'NH02')->firstOrFail();

        $this->travelTo('2027-07-01 08:00:00');

        $this->assertFalse($nh02->hasValidCertificate());
    }

    public function test_batch_links_farmer_product_plan_item_and_order_items(): void
    {
        $batch = Batch::where('code', 'F2H-260917-RM-NH01')->firstOrFail();

        $this->assertSame('NH01', $batch->farmer->code);
        $this->assertSame('RM', $batch->product->code);
        $this->assertSame(1, $batch->planItem->id);
        $this->assertSame('2026-09-19', $batch->best_before->toDateString());
        $this->assertSame(4, (int) $batch->orderItems->sum('pivot.quantity'));
        $this->assertSame('2026-09-17', $batch->planItem->plan->delivery_date->toDateString());
    }

    public function test_misc_tables_and_casts(): void
    {
        $this->assertSame(45, Feedback::count());
        $this->assertCount(4, Basket::where('slug', 'gio-mini')->firstOrFail()->items);
        $this->assertSame(['rau sống'], Customer::findOrFail(1)->preferences);
        $this->assertSame(SubscriptionFrequency::Weekly, Subscription::findOrFail(1)->frequency);
        $this->assertSame(UserRole::Admin, User::where('email', 'admin@farm2home.vn')->firstOrFail()->role);
    }
}
