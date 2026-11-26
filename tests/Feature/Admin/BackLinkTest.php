<?php

namespace Tests\Feature\Admin;

use App\Models\Batch;
use App\Models\HarvestPlan;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsCatalog;
use Tests\TestCase;

class BackLinkTest extends TestCase
{
    use BuildsCatalog, RefreshDatabase;

    private Batch $batch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-03 07:00:00');
        $this->buildCatalog();
        $this->batch = Batch::factory()->create([
            'code' => 'F2H-261003-RM-NH01', 'farmer_id' => $this->nh01->id, 'product_id' => $this->rm->id, 'quantity_kg' => 5,
            'harvested_at' => '2026-10-03 05:30:00', 'best_before' => '2026-10-05', 'quality_status' => 'pending',
        ]);
    }

    public function test_detail_page_links_back_to_the_list_with_the_filters_last_used(): void
    {
        $this->actingAs($this->operator)->get(route('admin.batches.index', ['date' => '2026-09-30']))->assertOk();

        $this->actingAs($this->operator)->get(route('admin.batches.show', $this->batch))
            ->assertOk()
            ->assertSee('href="'.e(route('admin.batches.index', ['date' => '2026-09-30'])).'"', false)
            ->assertSee('← Lô hàng');
    }

    public function test_back_link_falls_back_to_the_plain_list(): void
    {
        $this->actingAs($this->operator)->get(route('admin.batches.show', $this->batch))
            ->assertOk()
            ->assertSee('href="'.route('admin.batches.index').'"', false)
            ->assertSee('← Lô hàng');
    }

    public function test_back_link_stays_after_confirming_quality(): void
    {
        $this->actingAs($this->operator)->get(route('admin.batches.index', ['date' => '2026-09-30']));
        $this->actingAs($this->operator)->patch(route('admin.batches.update', $this->batch), ['quality_status' => 'passed'])
            ->assertRedirect();

        $this->actingAs($this->operator)->get(route('admin.batches.show', $this->batch))
            ->assertSee('href="'.e(route('admin.batches.index', ['date' => '2026-09-30'])).'"', false);
    }

    public function test_detail_and_form_pages_all_have_a_back_link(): void
    {
        $order = Order::factory()->create();
        $plan = HarvestPlan::factory()->create(['delivery_date' => '2026-10-03', 'harvest_date' => '2026-10-03']);

        $pages = [
            route('admin.orders.show', $order) => '← Đơn hàng',
            route('admin.orders.create') => '← Đơn hàng',
            route('admin.harvest-plans.show', $plan) => '← Kế hoạch thu hoạch',
            route('admin.loyalty.show', $order->customer) => '← Điểm thành viên',
            route('admin.products.edit', $this->rm) => '← Sản phẩm',
            route('admin.products.create') => '← Sản phẩm',
            route('admin.farmers.edit', $this->nh01) => '← HTX',
            route('admin.baskets.edit', $this->basket) => '← Giỏ rau',
            route('admin.zones.edit', $this->zone) => '← Cụm giao',
            route('admin.users.edit', $this->operator) => '← Tài khoản',
            route('admin.subscriptions.create') => '← Giỏ định kỳ',
        ];

        foreach ($pages as $url => $label) {
            $this->actingAs($this->admin)->get($url)->assertOk()->assertSee($label);
        }
    }

    public function test_list_pages_have_no_back_link(): void
    {
        $this->actingAs($this->operator)->get(route('admin.batches.index'))->assertOk()->assertDontSee('← Lô hàng');
    }
}
