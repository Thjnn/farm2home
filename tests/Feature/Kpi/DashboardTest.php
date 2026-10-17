<?php

namespace Tests\Feature\Kpi;

use App\Models\Farmer;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-03 21:00:00');
    }

    public function test_dashboard_reminds_about_certificates_expiring_soon_or_expired(): void
    {
        Farmer::factory()->create(['code' => 'NH11', 'certificate_name' => 'VietGAP', 'certificate_file' => 'certificates/a.pdf', 'certificate_expires_at' => '2026-10-13']);
        Farmer::factory()->create(['code' => 'NH12', 'certificate_name' => 'VietGAP', 'certificate_file' => 'certificates/b.pdf', 'certificate_expires_at' => '2026-10-01']);
        Farmer::factory()->create(['code' => 'NH13', 'certificate_name' => 'VietGAP', 'certificate_file' => 'certificates/c.pdf', 'certificate_expires_at' => '2027-06-30']);
        Farmer::factory()->create(['code' => 'NH14', 'certificate_name' => 'VietGAP', 'certificate_file' => null, 'certificate_expires_at' => '2026-10-05']);

        $this->actingAs(User::factory()->create(['role' => 'admin']))->get('/admin')
            ->assertOk()
            ->assertSee('Chứng nhận cần gia hạn')
            ->assertSee('NH11')->assertSee('còn 10 ngày')
            ->assertSee('NH12')->assertSee('đã hết hạn 2 ngày, trang QR không còn hiện chứng nhận')
            ->assertDontSee('NH13')->assertDontSee('NH14');

        $this->get('/admin/farmers')->assertSee('(còn 10 ngày, nhắc gia hạn)');
    }

    public function test_dashboard_shows_all_five_kpi_groups_for_the_sample_period(): void
    {
        $this->seed(DemoSeeder::class);
        $this->actingAs(User::where('email', 'operator@farm2home.vn')->firstOrFail());

        $this->get('/admin?from=2026-09-10&to=2026-10-03')
            ->assertOk()
            // Dự báo
            ->assertSee('Dự báo')->assertSee('WAPE')->assertSee('39,1%')->assertSee('38 / 112')
            // Khách hàng
            ->assertSee('Khách hàng')->assertSee('98,3%')->assertSee('96%')->assertSee('4,64')->assertSee('38 / 45')
            // Kinh tế
            ->assertSee('Kinh tế')->assertSee('7.773.000đ')->assertSee('59.336đ')->assertSee('12,7%')
            // Nông hộ
            ->assertSee('Nông hộ')->assertSee('100%')
            // Môi trường
            ->assertSee('Môi trường')->assertSee('87,7 kg')->assertSee('27,2%')->assertSee('83,3%')
            ->assertDontSee('CO₂')->assertDontSee('CO2');
    }

    public function test_default_range_is_the_last_28_days(): void
    {
        $this->seed(DemoSeeder::class);
        $this->actingAs(User::where('email', 'admin@farm2home.vn')->firstOrFail());

        $this->get('/admin')->assertOk()->assertSee('value="2026-09-06"', false)->assertSee('value="2026-10-03"', false);
    }

    public function test_empty_database_renders_dashes_instead_of_errors(): void
    {
        $user = User::factory()->create(['role' => 'operator']);

        $this->actingAs($user)->get('/admin')->assertOk()->assertSee('—')->assertSee('Chưa có dữ liệu trong khoảng này');
    }

    public function test_invalid_range_is_rejected(): void
    {
        $user = User::factory()->create(['role' => 'operator']);

        $this->actingAs($user)->get('/admin?from=2026-10-03&to=2026-10-01')->assertSessionHasErrors('to');
    }
}
