<?php

namespace Tests\Feature\Batches;

use App\Models\Batch;
use App\Models\Farmer;
use App\Services\QrCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsCatalog;
use Tests\TestCase;

class PublicBatchPageTest extends TestCase
{
    use BuildsCatalog, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-03 12:00:00');
        $this->buildCatalog();
    }

    private function batch(Farmer $farmer, string $quality = 'passed'): Batch
    {
        return Batch::factory()->create([
            'code' => 'F2H-261003-RM-'.$farmer->code, 'farmer_id' => $farmer->id, 'product_id' => $this->rm->id, 'quantity_kg' => 5,
            'harvested_at' => '2026-10-03 05:30:00', 'received_at' => '2026-10-03 07:00:00',
            'processed_at' => '2026-10-03 08:30:00', 'packed_at' => '2026-10-03 10:00:00',
            'processing_note' => 'Nhặt lá úa, rửa sơ, để ráo', 'storage_guide' => 'Bảo quản ngăn mát 4–8°C.',
            'best_before' => '2026-10-05', 'quality_status' => $quality,
        ]);
    }

    public function test_page_shows_batch_facts_and_a_feedback_form(): void
    {
        $this->batch($this->nh01);

        $this->get('/lo/F2H-261003-RM-NH01')
            ->assertOk()
            ->assertSee('F2H-261003-RM-NH01')
            ->assertSee('Rau muống')
            ->assertSee('Nguyễn Văn Tâm')
            ->assertSee('Xã Thân Cửu Nghĩa, Đồng Tháp')
            ->assertSee('03/10/2026 05:30')
            ->assertSee('03/10/2026 10:00')
            ->assertSee('Nhặt lá úa, rửa sơ, để ráo')
            ->assertSee('Bảo quản ngăn mát 4–8°C.')
            ->assertSee('05/10/2026')
            ->assertSee('Gửi phản hồi');
    }

    public function test_page_makes_no_marketing_or_health_claims(): void
    {
        $this->batch($this->nh01);

        $this->get('/lo/F2H-261003-RM-NH01')
            ->assertDontSee('100%')
            ->assertDontSee('tuyệt đối')
            ->assertDontSee('sức khỏe')
            ->assertDontSee('0911000001');
    }

    public function test_certificate_is_shown_only_for_a_farmer_who_has_one(): void
    {
        $this->batch($this->nh01);
        $this->batch($this->nh02);

        $this->get('/lo/F2H-261003-RM-NH01')->assertDontSee('VietGAP')->assertSee('Chưa có chứng nhận được xác thực');
        $this->get('/lo/F2H-261003-RM-NH02')->assertSee('VietGAP (mẫu)')->assertSee('30/06/2027');
    }

    public function test_expired_certificate_is_not_shown(): void
    {
        $this->batch($this->nh02);
        $this->nh02->update(['certificate_expires_at' => '2026-10-02']);

        $this->get('/lo/F2H-261003-RM-NH02')->assertOk()->assertDontSee('VietGAP');
    }

    public function test_only_batches_that_passed_quality_check_are_public(): void
    {
        $this->batch($this->nh01, 'pending');
        $this->batch($this->nh02, 'rejected');

        $this->get('/lo/F2H-261003-RM-NH01')->assertNotFound();
        $this->get('/lo/F2H-261003-RM-NH02')->assertNotFound();
        $this->get('/lo/KHONG-TON-TAI')->assertNotFound();
    }

    public function test_qr_service_returns_inline_svg(): void
    {
        $svg = app(QrCodeService::class)->svg('http://farm2home.test/lo/F2H-261003-RM-NH01');

        $this->assertStringStartsWith('<svg', ltrim($svg));
        $this->assertStringNotContainsString('<?xml', $svg);
    }
}
