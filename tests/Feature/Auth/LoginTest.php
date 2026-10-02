<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsCatalog;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use BuildsCatalog, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildCatalog();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/admin')->assertRedirect('/login');
    }

    public function test_login_page_is_in_vietnamese(): void
    {
        $this->get('/login')->assertOk()->assertSee('Đăng nhập')->assertSee('Mật khẩu');
    }

    public function test_operator_can_log_in_and_reach_the_dashboard(): void
    {
        $this->post('/login', ['email' => 'operator@example.test', 'password' => 'password'])
            ->assertRedirect('/admin');

        $this->assertAuthenticatedAs($this->operator);
        $this->get('/admin')->assertOk()->assertSee('Tổng quan');
    }

    public function test_wrong_password_shows_a_vietnamese_error_and_keeps_the_email(): void
    {
        $this->from('/login')
            ->post('/login', ['email' => 'operator@example.test', 'password' => 'sai-mat-khau'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors(['email' => 'Email hoặc mật khẩu không đúng.'])
            ->assertSessionHasInput('email', 'operator@example.test');

        $this->assertGuest();
    }

    public function test_logout_ends_the_session(): void
    {
        $this->actingAs($this->operator)->post('/logout')->assertRedirect('/login');

        $this->assertGuest();
    }

    public function test_logged_in_user_visiting_login_goes_to_dashboard(): void
    {
        $this->actingAs($this->admin)->get('/login')->assertRedirect('/admin');
    }
}
