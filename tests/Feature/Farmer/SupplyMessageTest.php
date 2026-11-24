<?php

namespace Tests\Feature\Farmer;

use App\Enums\SupplySource;
use App\Models\SupplyReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Support\BuildsCatalog;
use Tests\TestCase;

class SupplyMessageTest extends TestCase
{
    use BuildsCatalog, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Thứ Sáu 02/10/2026.
        $this->travelTo('2026-10-02 17:00:00');
        $this->buildCatalog();
        config(['services.gemini.key' => null]);
        $this->actingAs($this->operator);
    }

    public function test_without_ai_the_message_is_read_by_keywords_and_shown_for_review(): void
    {
        $this->post('/admin/supply/read-message', ['farmer_id' => $this->nh02->id, 'message' => 'mai có 30 ký cải ngọt, 20 ký rau muống'])
            ->assertRedirect();

        $this->assertSame([
            ['product_id' => $this->cn->id, 'kg' => 30.0, 'date' => '2026-10-03', 'reason' => null],
            ['product_id' => $this->rm->id, 'kg' => 20.0, 'date' => '2026-10-03', 'reason' => null],
        ], session('parsed')['items']);
        $this->assertSame('rules', session('parsed')['method']);
        $this->assertSame(0, SupplyReport::count());

        $this->get('/admin/supply')->assertOk()->assertSee('dò từ khóa')->assertSee('Xác nhận lưu');
    }

    public function test_ai_reading_is_used_and_lines_needing_a_person_are_flagged(): void
    {
        config(['services.gemini.key' => 'test-key']);
        Http::fake(['*' => Http::response(['candidates' => [['content' => ['parts' => [['text' => json_encode(['items' => [
            ['product' => 'RM', 'kg' => 25, 'date' => '2026-10-03', 'unsure' => false],
            // NH01 không trồng cải ngọt.
            ['product' => 'CN', 'kg' => 10, 'date' => '2026-10-03', 'unsure' => false],
            ['product' => 'XYZ', 'kg' => 5, 'date' => '2026-10-03', 'unsure' => false],
        ]])]]]]]])]);

        $this->post('/admin/supply/read-message', ['farmer_id' => $this->nh01->id, 'message' => 'mai 2 yến rưỡi muống, 10 ký cải ngọt, 5 ký bắp cải']);

        $this->assertSame('ai', session('parsed')['method']);
        $this->assertSame(
            [null, 'HTX chưa đăng ký loại rau này', 'Không rõ loại rau'],
            array_column(session('parsed')['items'], 'reason'),
        );
        Http::assertSent(fn ($request) => str_contains($request['system_instruction']['parts'][0]['text'], 'RM: Rau muống')
            && $request['generationConfig']['responseMimeType'] === 'application/json');
    }

    public function test_ai_failure_falls_back_to_keywords(): void
    {
        config(['services.gemini.key' => 'test-key']);
        Http::fake(['*' => Http::response('quá tải', 503)]);

        $this->post('/admin/supply/read-message', ['farmer_id' => $this->nh01->id, 'message' => 'mai co 20kg rau muong']);

        $this->assertSame('rules', session('parsed')['method']);
        $this->assertSame($this->rm->id, session('parsed')['items'][0]['product_id']);
    }

    public function test_confirm_saves_only_checked_rows_as_zalo_supply(): void
    {
        $this->post('/admin/supply/confirm-message', ['farmer_id' => $this->nh02->id, 'rows' => [
            ['keep' => '1', 'product_id' => $this->cn->id, 'kg' => '30', 'date' => '2026-10-03'],
            ['keep' => '0', 'product_id' => '', 'kg' => '5', 'date' => '2026-10-03'],
        ]])->assertRedirect('/admin/supply?date=2026-10-03')->assertSessionHas('status');

        $report = SupplyReport::sole();
        $this->assertSame([$this->nh02->id, $this->cn->id, '30.00', SupplySource::Zalo], [$report->farmer_id, $report->product_id, $report->quantity_kg, $report->source]);
    }

    public function test_checked_row_must_be_complete(): void
    {
        $this->post('/admin/supply/confirm-message', ['farmer_id' => $this->nh02->id, 'rows' => [
            ['keep' => '1', 'product_id' => '', 'kg' => '30', 'date' => '2026-10-03'],
        ]])->assertSessionHasErrors('rows.0.product_id');

        $this->assertSame(0, SupplyReport::count());
    }

    public function test_eval_command_scores_the_sample_messages(): void
    {
        $this->artisan('supply:eval-messages', ['--split' => 'dev'])
            ->expectsOutputToContain('Chưa có GEMINI_API_KEY')
            ->expectsOutputToContain('Dò từ khóa (cách đơn giản)')
            ->assertSuccessful();
    }
}
