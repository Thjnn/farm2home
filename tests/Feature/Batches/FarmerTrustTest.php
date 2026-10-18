<?php

namespace Tests\Feature\Batches;

use App\Models\Batch;
use App\Models\Customer;
use App\Models\Farmer;
use App\Models\Feedback;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Support\BuildsCatalog;
use Tests\TestCase;

/**
 * Tài khoản nhận tiền, ảnh vườn, ảnh lô và sao đánh giá của nông hộ.
 */
class FarmerTrustTest extends TestCase
{
    use BuildsCatalog, RefreshDatabase;

    private Batch $batch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-03 12:00:00');
        $this->buildCatalog();
        Storage::fake('public');

        $this->batch = Batch::factory()->create([
            'code' => 'F2H-261003-RM-NH01', 'farmer_id' => $this->nh01->id, 'product_id' => $this->rm->id,
            'quality_status' => 'passed', 'qr_key' => 'KHOA123',
        ]);
    }

    /**
     * @param  list<int>  $ratings
     */
    private function rate(array $ratings, ?Customer $customer = null): void
    {
        foreach ($ratings as $rating) {
            Feedback::factory()->create(['batch_id' => $this->batch->id, 'customer_id' => $customer?->id, 'rating' => $rating, 'freshness_rating' => 4]);
        }
    }

    public function test_admin_saves_an_encrypted_bank_account_and_farm_photos(): void
    {
        $this->actingAs($this->admin)->put('/admin/farmers/'.$this->nh01->id, [
            'code' => 'NH01', 'name' => $this->nh01->name, 'phone' => $this->nh01->phone, 'status' => 'active',
            'bank_name' => 'Agribank', 'bank_account_number' => '7300205123456', 'bank_account_holder' => 'NGUYEN VAN TAM',
            'farm_photos' => [UploadedFile::fake()->image('vuon1.jpg', 3000, 2000), UploadedFile::fake()->image('vuon2.png', 800, 600)],
        ])->assertRedirect('/admin/farmers');

        $farmer = $this->nh01->fresh();
        $this->assertSame('7300205123456', $farmer->bank_account_number);
        $this->assertNotSame('7300205123456', DB::table('farmers')->where('id', $farmer->id)->value('bank_account_number'));
        $this->assertCount(2, $farmer->farm_photos);

        // Ảnh được thu nhỏ còn cạnh dài 1280px và lưu JPEG.
        [$width, $height] = getimagesizefromstring(Storage::disk('public')->get($farmer->farm_photos[0]));
        $this->assertSame([1280, 853], [$width, $height]);
    }

    public function test_bank_account_number_must_be_digits(): void
    {
        $this->actingAs($this->admin)->put('/admin/farmers/'.$this->nh01->id, [
            'code' => 'NH01', 'name' => $this->nh01->name, 'phone' => $this->nh01->phone, 'status' => 'active',
            'bank_account_number' => '7300-205',
        ])->assertSessionHasErrors('bank_account_number');
    }

    public function test_new_farm_photos_replace_the_old_set(): void
    {
        $this->nh01->update(['farm_photos' => [Storage::disk('public')->putFileAs('farms', UploadedFile::fake()->image('cu.jpg'), 'cu.jpg')]]);

        $this->actingAs($this->admin)->put('/admin/farmers/'.$this->nh01->id, [
            'code' => 'NH01', 'name' => $this->nh01->name, 'phone' => $this->nh01->phone, 'status' => 'active',
            'farm_photos' => [UploadedFile::fake()->image('moi.jpg')],
        ]);

        Storage::disk('public')->assertMissing('farms/cu.jpg');
        $this->assertCount(1, $this->nh01->fresh()->farm_photos);
    }

    public function test_operator_takes_a_batch_photo_that_shows_on_the_qr_page(): void
    {
        $this->actingAs($this->operator)
            ->post('/admin/batches/'.$this->batch->id.'/photo', ['photo' => UploadedFile::fake()->image('lo.jpg', 1000, 750)])
            ->assertRedirect('/admin/batches/'.$this->batch->id);

        $photo = $this->batch->fresh()->photo;
        Storage::disk('public')->assertExists($photo);

        $this->get('/lo/F2H-261003-RM-NH01/KHOA123')->assertSee($photo)->assertSee('Ảnh chụp chính lô rau này');
    }

    public function test_batch_photo_must_be_an_image(): void
    {
        $this->actingAs($this->operator)
            ->post('/admin/batches/'.$this->batch->id.'/photo', ['photo' => UploadedFile::fake()->create('lo.pdf', 100, 'application/pdf')])
            ->assertSessionHasErrors('photo');
    }

    public function test_qr_page_shows_the_farmer_rating_only_with_enough_reviews(): void
    {
        $this->rate([5, 4, 5, 4]);
        $this->get('/lo/F2H-261003-RM-NH01/KHOA123')->assertDontSee('lượt)');

        $this->rate([5]);
        $this->get('/lo/F2H-261003-RM-NH01/KHOA123')
            ->assertSee('Vườn rau của Nguyễn Văn Tâm')
            ->assertSee('4,6★')
            ->assertSee('(5 lượt)')
            ->assertSee('độ tươi 4,0★');
    }

    public function test_qr_page_shows_farm_photos(): void
    {
        $this->nh01->update(['farm_photos' => ['farms/vuon.jpg']]);

        $this->get('/lo/F2H-261003-RM-NH01/KHOA123')->assertSee('farms/vuon.jpg')->assertSee('Ảnh vườn rau của nông hộ');
    }

    public function test_farmer_page_shows_rating_comments_and_a_masked_bank_account(): void
    {
        $this->nh01->update(['bank_name' => 'Agribank', 'bank_account_number' => '7300205123456']);
        $this->rate([5, 3]);
        Feedback::factory()->create(['batch_id' => $this->batch->id, 'rating' => 4, 'comment' => 'Rau muống giòn, tươi']);

        $this->get('/nong-ho/'.$this->nh01->access_token)
            ->assertSee('Khách đánh giá rau của cô chú')
            ->assertSee('(3 lượt đánh giá)')
            ->assertSee('Rau muống giòn, tươi')
            ->assertSee('Agribank ••• 3456')
            ->assertDontSee('7300205123456');
    }

    public function test_lookup_shows_the_customers_feedback_history_with_replies(): void
    {
        $customer = Customer::factory()->create(['phone' => '0988000009']);
        Feedback::factory()->create([
            'batch_id' => $this->batch->id, 'customer_id' => $customer->id, 'rating' => 2, 'comment' => 'Rau hơi héo',
            'status' => 'resolved', 'response' => 'Tụi em đã đổi lô mới cho anh chị.',
        ]);
        Feedback::factory()->create(['batch_id' => $this->batch->id, 'rating' => 1, 'comment' => 'Phản hồi của người khác']);

        $this->post('/tra-cuu', ['phone' => '0988000009'])
            ->assertSee('Phản hồi bạn đã gửi')
            ->assertSee('Rau hơi héo')
            ->assertSee('Tụi em đã đổi lô mới cho anh chị.')
            ->assertSee('Đã xử lý')
            ->assertDontSee('Phản hồi của người khác');
    }

    public function test_farmer_without_reviews_sees_an_empty_state(): void
    {
        $other = Farmer::factory()->create(['code' => 'NH09']);

        $this->get('/nong-ho/'.$other->access_token)->assertSee('Chưa có lượt đánh giá nào.');
    }
}
