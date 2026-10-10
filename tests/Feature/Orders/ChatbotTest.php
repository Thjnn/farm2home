<?php

namespace Tests\Feature\Orders;

use App\Models\Forecast;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Support\BuildsCatalog;
use Tests\TestCase;

class ChatbotTest extends TestCase
{
    use BuildsCatalog, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-02 10:00:00');
        $this->buildCatalog();
        Http::preventStrayRequests();
    }

    private function ask(string $message): string
    {
        return $this->postJson('/hoi-dap', ['message' => $message])->assertOk()->json('reply');
    }

    public function test_order_page_shows_the_chatbot(): void
    {
        $this->get('/dat-hang')->assertOk()->assertSee('Hỏi đáp nhanh');
    }

    public function test_product_price_comes_from_the_catalog_with_or_without_accents(): void
    {
        $this->assertStringContainsString('Rau muống: 12.000đ / gói 0,5 kg', $this->ask('rau muống giá sao'));
        $this->assertStringContainsString('Rau muống: 12.000đ', $this->ask('rau muong bao nhieu'));
    }

    public function test_price_list_lists_every_active_product(): void
    {
        $reply = $this->ask('Bảng giá rau?');

        $this->assertStringContainsString('Cải ngọt: 15.000đ', $reply);
        $this->assertStringContainsString('Hành lá: 8.000đ', $reply);
    }

    public function test_basket_question_shows_price_and_contents(): void
    {
        $reply = $this->ask('giỏ rau có gì');

        $this->assertStringContainsString('Giỏ Mini: 45.000đ', $reply);
        $this->assertStringContainsString('1 gói Rau muống', $reply);
    }

    public function test_shipping_fee_per_zone_and_pickup_point(): void
    {
        $reply = $this->ask('phí ship bao nhiêu');

        $this->assertStringContainsString('Cụm 1 – Trung tâm: 10.000đ, miễn phí khi tiền hàng từ 150.000đ', $reply);
        $this->assertStringContainsString('Cổng trường', $reply);
    }

    public function test_hour_question_is_not_mistaken_for_a_basket(): void
    {
        $reply = $this->ask('mấy giờ giao');

        $this->assertStringContainsString('06:00-08:00', $reply);
        $this->assertStringContainsString('18:00', $reply);
    }

    public function test_forecast_question_gives_the_weekly_total_peak_day_and_reason(): void
    {
        Forecast::factory()->create(['product_id' => $this->rm->id, 'target_date' => '2026-10-03', 'predicted_total_kg' => 20.2, 'confirmed_kg' => 5]);
        Forecast::factory()->create(['product_id' => $this->rm->id, 'target_date' => '2026-10-05', 'predicted_total_kg' => 10, 'confirmed_kg' => 8]);
        // Lần chạy cũ hơn và phương pháp khác không được tính.
        Forecast::factory()->create(['product_id' => $this->rm->id, 'target_date' => '2026-10-03', 'generated_on' => '2026-10-01', 'predicted_total_kg' => 50]);
        Forecast::factory()->create(['product_id' => $this->rm->id, 'target_date' => '2026-10-03', 'method' => 'weekday_average', 'predicted_total_kg' => 70]);

        $reply = $this->ask('dự báo số lượng rau muống tuần này như thế nào?');

        $this->assertStringContainsString('Rau muống: dự kiến khách đặt khoảng 30 kg (03/10–05/10)', $reply);
        $this->assertStringContainsString('Đông nhất thứ bảy 03/10, khoảng 20 kg, vì cuối tuần thường đông khách hơn', $reply);
        $this->assertStringContainsString('không phải số rau đang còn', $reply);
        $this->assertStringNotContainsString('12.000đ', $reply);
    }

    public function test_even_forecast_names_no_peak_day(): void
    {
        Forecast::factory()->create(['product_id' => $this->rm->id, 'target_date' => '2026-10-03', 'predicted_total_kg' => 10]);
        Forecast::factory()->create(['product_id' => $this->rm->id, 'target_date' => '2026-10-04', 'predicted_total_kg' => 11]);

        $reply = $this->ask('dự báo rau muống');

        $this->assertStringContainsString('Các ngày khá đều, khoảng 10,5 kg mỗi ngày', $reply);
        $this->assertStringNotContainsString('Đông nhất', $reply);
    }

    public function test_forecast_question_without_forecasts_says_so(): void
    {
        $this->assertStringContainsString('chưa có số dự kiến', $this->ask('nhu cầu rau tuần tới'));
        $this->assertStringContainsString('ngày giao sớm nhất', $this->ask('dự kiến khi nào giao'));
    }

    public function test_delivery_date_answer_gives_the_date_and_the_reason(): void
    {
        $this->assertStringContainsString('ngày giao sớm nhất là thứ bảy 03/10, vì vẫn trước 18:00', mb_strtolower($this->ask('khi nào giao')));

        $this->travelTo('2026-10-02 19:00:00');
        $this->assertStringContainsString('chủ nhật 04/10, vì đã qua 18:00', mb_strtolower($this->ask('khi nào giao')));
    }

    public function test_dish_question_suggests_vegetables_instead_of_delivery_hours(): void
    {
        $reply = $this->ask('ngày mai tui có bữa tiệc tui cần rau nấu lẩu có thể giới thiệu vài nguyên liệu rau k');

        $this->assertStringContainsString('Món lẩu bạn có thể chọn', $reply);
        $this->assertStringContainsString('Rau muống: 12.000đ', $reply);
        $this->assertStringContainsString('ngày giao sớm nhất', $reply);
        // "bao lâu" bỏ dấu cũng thành "lau" nhưng không phải món lẩu.
        $this->assertStringNotContainsString('Món lẩu', $this->ask('đặt trước bao lâu'));
    }

    public function test_health_questions_get_no_medical_advice(): void
    {
        $this->assertStringContainsString('không tư vấn sức khỏe', $this->ask('bị tiểu đường nên ăn rau gì'));
        // "chưa" bỏ dấu thành "chua" nhưng không phải câu hỏi sức khỏe.
        $this->assertStringContainsString('Tra cứu đơn', $this->ask('đơn của tôi chưa giao'));
    }

    public function test_unknown_question_falls_back_to_the_topic_list(): void
    {
        $this->assertStringContainsString('Mình chưa hiểu', $this->ask('xyz'));
    }

    public function test_message_is_required_and_short(): void
    {
        $this->postJson('/hoi-dap', ['message' => ''])->assertUnprocessable();
        $this->postJson('/hoi-dap', ['message' => str_repeat('a', 301)])->assertUnprocessable();
    }

    public function test_named_area_gets_only_its_own_zone(): void
    {
        $this->zone->update(['name' => 'Cụm 1 – Trung tâm Mỹ Tho', 'description' => 'Phường Mỹ Tho']);

        $reply = $this->ask('giao về mỹ tho tốn bao nhiêu');

        $this->assertStringContainsString('Cụm 1 – Trung tâm Mỹ Tho (Phường Mỹ Tho): 10.000đ', $reply);
        $this->assertStringNotContainsString('Cổng trường', $reply);
    }

    public function test_without_a_key_gemini_is_never_called(): void
    {
        $this->ask('phí ship bao nhiêu');

        Http::assertNothingSent();
    }

    public function test_gemini_answers_with_store_data_when_a_key_is_set(): void
    {
        config(['services.gemini.key' => 'test-key', 'services.gemini.model' => 'gemini-flash-latest']);
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['candidates' => [['content' => ['parts' => [
            ['text' => 'đang suy nghĩ', 'thought' => true],
            ['text' => 'Giao về Mỹ Tho phí 10.000đ.'],
        ]]]]])]);

        $this->assertSame('Giao về Mỹ Tho phí 10.000đ.', $this->ask('giao về mỹ tho tốn bao nhiêu'));

        Http::assertSent(function (Request $request) {
            $prompt = $request['system_instruction']['parts'][0]['text'];

            return str_ends_with($request->url(), '/models/gemini-flash-latest:generateContent')
                && $request->hasHeader('x-goog-api-key', 'test-key')
                && str_contains($prompt, 'Rau muống: 12.000đ')
                && str_contains($prompt, 'Cụm 1 – Trung tâm')
                && $request['contents'][0]['parts'][0]['text'] === 'giao về mỹ tho tốn bao nhiêu';
        });
    }

    public function test_falls_back_to_the_script_when_gemini_is_out_of_quota(): void
    {
        config(['services.gemini.key' => 'test-key']);
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['error' => ['code' => 429]], 429)]);

        $this->assertStringContainsString('Cụm 1 – Trung tâm: 10.000đ', $this->ask('phí ship bao nhiêu'));
    }
}
