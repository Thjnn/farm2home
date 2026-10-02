<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Support\BuildsCatalog;
use Tests\TestCase;

class RoleMiddlewareTest extends TestCase
{
    use BuildsCatalog, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildCatalog();
        Route::middleware(['web', 'auth', 'role:admin'])->get('/_test/admin-only', fn () => 'ok');
    }

    public function test_operator_is_forbidden_on_admin_only_routes(): void
    {
        $this->actingAs($this->operator)->get('/_test/admin-only')->assertForbidden();
    }

    public function test_admin_passes(): void
    {
        $this->actingAs($this->admin)->get('/_test/admin-only')->assertOk()->assertSee('ok');
    }

    public function test_both_roles_can_open_the_admin_area(): void
    {
        $this->actingAs($this->operator)->get('/admin')->assertOk();
        $this->actingAs($this->admin)->get('/admin')->assertOk();
    }
}
