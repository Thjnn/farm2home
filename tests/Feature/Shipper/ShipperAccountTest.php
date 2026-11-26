<?php

namespace Tests\Feature\Shipper;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsCatalog;
use Tests\TestCase;

class ShipperAccountTest extends TestCase
{
    use BuildsCatalog, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildCatalog();
    }

    public function test_admin_creates_a_shipper_account(): void
    {
        $this->actingAs($this->admin)->get(route('admin.users.create'))->assertOk()->assertSee('Giao hàng');

        $this->actingAs($this->admin)->post(route('admin.users.store'), [
            'name' => 'Anh Bình', 'email' => 'binh@example.test', 'phone' => '0909000001',
            'role' => 'shipper', 'password' => 'password123',
        ])->assertRedirect(route('admin.users.index'));

        $this->assertSame(UserRole::Shipper, User::where('email', 'binh@example.test')->first()->role);
    }
}
