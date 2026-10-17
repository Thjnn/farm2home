<?php

namespace Tests\Feature\Harvest;

use App\Enums\HarvestPlanStatus;
use App\Enums\OrderChannel;
use App\Models\HarvestPlan;
use App\Models\SupplyReport;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsCatalog;
use Tests\TestCase;

class HarvestPlanScreensTest extends TestCase
{
    use BuildsCatalog, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-02 19:00:00');
        $this->buildCatalog();

        app(OrderService::class)->place([
            'name' => 'Khách', 'phone' => '0988000001', 'address' => '1 Lê Lợi',
            'delivery_zone_id' => $this->zone->id, 'delivery_date' => '2026-10-03', 'time_slot' => '17:00-19:00',
            'order_type' => 'single', 'items' => [['product_id' => $this->rm->id, 'quantity' => 14]],
            'payment_method' => 'cod', 'data_consent' => true,
        ], OrderChannel::Zalo);
        SupplyReport::factory()->create(['farmer_id' => $this->nh01->id, 'product_id' => $this->rm->id, 'available_date' => '2026-10-03', 'quantity_kg' => 9, 'source' => 'form']);
    }

    private function generate(): HarvestPlan
    {
        $this->actingAs($this->operator)->post('/admin/harvest-plans/generate', ['delivery_date' => '2026-10-03']);

        return HarvestPlan::firstOrFail();
    }

    public function test_generate_creates_a_draft_and_shows_the_suggestion(): void
    {
        $this->actingAs($this->operator)
            ->post('/admin/harvest-plans/generate', ['delivery_date' => '2026-10-03'])
            ->assertRedirect('/admin/harvest-plans/'.HarvestPlan::firstOrFail()->id);

        // 14 gói = 7,0 kg × 1,1 = 7,7 → 8,0 kg
        $this->get('/admin/harvest-plans/'.HarvestPlan::firstOrFail()->id)
            ->assertOk()
            ->assertSee('Nháp')
            ->assertSee('Rau muống')
            ->assertSee('Nguyễn Văn Tâm')
            ->assertSee('7 kg')
            ->assertSee('8 kg')
            ->assertSee('1 đơn chờ xác nhận')
            ->assertSee('Chốt kế hoạch');
    }

    public function test_generating_also_runs_the_forecast_for_today(): void
    {
        $this->generate();

        $this->assertDatabaseHas('forecasts', ['product_id' => $this->rm->id, 'target_date' => '2026-10-03', 'generated_on' => '2026-10-02']);
    }

    public function test_operator_saves_all_rows_at_once(): void
    {
        SupplyReport::factory()->create(['farmer_id' => $this->nh02->id, 'product_id' => $this->rm->id, 'available_date' => '2026-10-03', 'quantity_kg' => 9, 'source' => 'form']);
        $plan = $this->generate();
        [$first, $second] = $plan->items()->orderBy('id')->get()->all();
        $url = '/admin/harvest-plans/'.$plan->id;

        $this->get($url)->assertSee('Lưu tất cả');

        // Một dòng thiếu lý do → không dòng nào được lưu, số đã nhập vẫn giữ lại.
        $this->from($url)->patch($url.'/items', ['items' => [
            $first->id => ['final_kg' => (float) $first->suggested_kg, 'adjust_reason' => '', 'farmer_id' => $first->farmer_id],
            $second->id => ['final_kg' => '9', 'adjust_reason' => '', 'farmer_id' => $second->farmer_id],
        ]])->assertSessionHasErrors('action')->assertSessionHasInput('items');
        $this->assertNull($first->refresh()->final_kg);

        $this->patch($url.'/items', ['items' => [
            $first->id => ['final_kg' => (float) $first->suggested_kg, 'adjust_reason' => '', 'farmer_id' => $first->farmer_id],
            $second->id => ['final_kg' => '9', 'adjust_reason' => 'Cuối tuần, tăng dự phòng', 'farmer_id' => $second->farmer_id],
        ]])->assertRedirect($url);

        $this->assertSame($first->suggested_kg, $first->refresh()->final_kg);
        $this->assertSame(['9.00', 'Cuối tuần, tăng dự phòng'], [$second->refresh()->final_kg, $second->adjust_reason]);
    }

    public function test_operator_splits_a_product_across_farmers_registered_for_it(): void
    {
        $plan = $this->generate();
        $first = $plan->items()->firstOrFail();
        $url = '/admin/harvest-plans/'.$plan->id;
        $row = fn ($kg, $reason, $farmerId) => ['final_kg' => $kg, 'adjust_reason' => $reason, 'farmer_id' => $farmerId];

        // Chỉ nhà đã đăng ký trồng rau muống được hiện trong ô chọn.
        $this->nh02->products()->detach($this->rm->id);
        $this->get($url)->assertSee('+ Thêm nông hộ cho Rau muống')->assertSee($this->nh01->name)->assertDontSee($this->nh02->name);
        $this->nh02->products()->attach($this->rm->id, ['typical_weekly_kg' => 10]);

        $this->patch($url.'/items', ['items' => [$first->id => $row('8', '', $first->farmer_id)], 'add_product_id' => $this->rm->id])->assertRedirect($url);
        $added = $plan->items()->where('suggested_kg', 0)->firstOrFail();

        // Không cho cùng một nhà ở hai dòng của cùng loại rau.
        $this->from($url)->patch($url.'/items', ['items' => [
            $first->id => $row('8', '', $first->farmer_id), $added->id => $row('2', '', $first->farmer_id),
        ]])->assertSessionHasErrors('action');

        $this->patch($url.'/items', ['items' => [
            $first->id => $row('6', 'Chia cho nhà khác', $first->farmer_id), $added->id => $row('2', '', $this->nh02->id),
        ]])->assertRedirect($url);
        $this->assertSame(['2.00', $this->nh02->id, 'Chia thêm nông hộ'], [$added->refresh()->final_kg, $added->farmer_id, $added->adjust_reason]);

        $this->patch($url.'/items', ['items' => [$added->id => $row('2', '', $this->nh02->id)], 'remove_item_id' => $added->id])->assertRedirect($url);
        $this->assertModelMissing($added);

        $this->from($url)->patch($url.'/items', ['items' => [$first->id => $row('6', 'x', $first->farmer_id)], 'remove_item_id' => $first->id])->assertSessionHasErrors('action');
    }

    public function test_confirm_shows_the_zalo_message_and_link(): void
    {
        $plan = $this->generate();

        $this->post('/admin/harvest-plans/'.$plan->id.'/confirm')->assertRedirect('/admin/harvest-plans/'.$plan->id);

        $this->assertSame(HarvestPlanStatus::Confirmed, $plan->refresh()->status);
        $this->get('/admin/harvest-plans/'.$plan->id)
            ->assertSee('Đã chốt')
            ->assertSee('- Rau muống: 8 kg')
            ->assertSee('https://zalo.me/0911000001')
            ->assertSee('Đánh dấu đã gửi')
            ->assertDontSee('Chốt kế hoạch');
    }

    public function test_mark_sent_and_index_listing(): void
    {
        $plan = $this->generate();
        $this->post('/admin/harvest-plans/'.$plan->id.'/confirm');
        $this->post('/admin/harvest-plans/'.$plan->id.'/sent')->assertRedirect();

        $this->assertSame(HarvestPlanStatus::Sent, $plan->refresh()->status);
        $this->get('/admin/harvest-plans')->assertOk()->assertSee('03/10/2026')->assertSee('Đã gửi nông hộ');
    }

    public function test_farmer_sees_the_list_only_after_confirmation_and_can_confirm(): void
    {
        $plan = $this->generate();
        $url = '/nong-ho/'.$this->nh01->access_token;

        $this->get($url)->assertDontSee('Danh sách thu hoạch');
        $this->post($url.'/thu-hoach/'.$plan->id)->assertSessionHasErrors('action');

        $this->actingAs($this->operator)->post('/admin/harvest-plans/'.$plan->id.'/confirm');
        auth()->logout();

        $this->get($url)->assertSee('Danh sách thu hoạch')->assertSee('8 kg')->assertSee('Tôi sẽ thu hoạch');

        $this->post($url.'/thu-hoach/'.$plan->id)->assertRedirect($url);
        $this->assertNotNull($plan->items()->firstOrFail()->farmer_confirmed_at);
        $this->get($url)->assertSee('Đã xác nhận');
    }

    public function test_delivery_date_is_required_and_guests_are_blocked(): void
    {
        $this->actingAs($this->operator)->post('/admin/harvest-plans/generate', [])->assertSessionHasErrors('delivery_date');
        auth()->logout();
        $this->get('/admin/harvest-plans')->assertRedirect('/login');
    }
}
