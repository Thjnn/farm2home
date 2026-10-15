<?php

namespace Tests\Feature;

use App\Enums\FarmerStatus;
use App\Http\Controllers\AppEntryController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsCatalog;
use Tests\TestCase;

class AppEntryTest extends TestCase
{
    use BuildsCatalog, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildCatalog();
    }

    public function test_guest_lands_on_the_customer_home(): void
    {
        $this->get('/app')->assertRedirect(route('home'));
    }

    public function test_signed_in_staff_land_on_the_admin_dashboard(): void
    {
        $this->actingAs($this->operator)->get('/app')->assertRedirect(route('admin.dashboard'));
    }

    public function test_a_phone_that_opened_its_farmer_link_returns_to_it(): void
    {
        $this->get(route('farmer.show', $this->nh01))->assertOk()
            ->assertCookie(AppEntryController::FARMER_COOKIE, $this->nh01->access_token);

        $this->withCookie(AppEntryController::FARMER_COOKIE, $this->nh01->access_token)
            ->get('/app')->assertRedirect(route('farmer.show', $this->nh01));
    }

    public function test_a_stale_or_inactive_farmer_link_is_forgotten(): void
    {
        $this->withCookie(AppEntryController::FARMER_COOKIE, 'old-token')
            ->get('/app')->assertRedirect(route('home'))->assertCookieExpired(AppEntryController::FARMER_COOKIE);

        $this->nh02->update(['status' => FarmerStatus::Inactive]);
        $this->withCookie(AppEntryController::FARMER_COOKIE, $this->nh02->access_token)
            ->get('/app')->assertRedirect(route('home'));
    }

    public function test_pages_link_the_manifest(): void
    {
        $this->get('/')->assertOk()->assertSee('manifest.webmanifest', false);
        $this->assertSame('/app', json_decode(file_get_contents(public_path('manifest.webmanifest')), true)['start_url']);
    }
}
