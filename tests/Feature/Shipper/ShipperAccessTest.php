<?php

namespace Tests\Feature\Shipper;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsCatalog;
use Tests\TestCase;

class ShipperAccessTest extends TestCase
{
    use BuildsCatalog, RefreshDatabase;

    private User $shipper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildCatalog();
        $this->shipper = User::factory()->create(['email' => 'binh@example.test', 'password' => 'password', 'role' => UserRole::Shipper]);
    }

    public function test_shipper_lands_on_the_delivery_screen_after_login(): void
    {
        $this->post(route('login.store'), ['email' => 'binh@example.test', 'password' => 'password'])
            ->assertRedirect(route('shipper.index'));
    }

    public function test_shipper_is_not_sent_to_an_admin_page_they_tried_before_login(): void
    {
        $this->get(route('admin.orders.index'))->assertRedirect(route('login'));

        $this->post(route('login.store'), ['email' => 'binh@example.test', 'password' => 'password'])
            ->assertRedirect(route('shipper.index'));
    }

    public function test_operator_still_lands_on_admin(): void
    {
        $this->post(route('login.store'), ['email' => 'operator@example.test', 'password' => 'password'])
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_shipper_opening_admin_is_redirected_to_the_delivery_screen(): void
    {
        $this->actingAs($this->shipper)->get(route('admin.orders.index'))->assertRedirect(route('shipper.index'));
    }

    public function test_operator_cannot_open_the_delivery_screen(): void
    {
        $this->actingAs($this->operator)->get(route('shipper.index'))->assertForbidden();
    }

    public function test_app_entry_and_guest_pages_send_shipper_home(): void
    {
        $this->actingAs($this->shipper)->get(route('app'))->assertRedirect(route('shipper.index'));
        $this->actingAs($this->shipper)->get(route('login'))->assertRedirect(route('shipper.index'));
    }
}
