<?php

namespace Tests\Feature\Ui;

use App\Enums\OrderStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Validator;
use Tests\Support\BuildsCatalog;
use Tests\TestCase;

class LayoutTest extends TestCase
{
    use BuildsCatalog, RefreshDatabase;

    public function test_blade_directives_format_money_and_weight(): void
    {
        $this->assertSame('12.000đ | 2,5 kg', trim(Blade::render('@vnd($a) | @kg($b)', ['a' => 12000, 'b' => '2.50'])));
    }

    public function test_status_badge_shows_the_enum_label(): void
    {
        $html = Blade::render('<x-status-badge :status="$s" />', ['s' => OrderStatus::Delivering]);

        $this->assertStringContainsString('Đang giao', $html);
    }

    public function test_admin_layout_has_navigation_and_user_name(): void
    {
        $this->buildCatalog();

        $this->actingAs($this->operator)->get('/admin')
            ->assertOk()
            ->assertSee('FARM2HOME')
            ->assertSee('Đơn hàng')
            ->assertSee('Kế hoạch thu hoạch')
            ->assertSee('Operator')
            ->assertSee('Đăng xuất');
    }

    public function test_validation_messages_are_vietnamese(): void
    {
        $validator = Validator::make(['phone' => 'abc'], ['phone' => ['required', 'regex:/^0\d{9}$/'], 'name' => ['required']]);

        $this->assertSame('Số điện thoại không đúng định dạng.', $validator->errors()->first('phone'));
        $this->assertSame('Vui lòng nhập họ tên.', $validator->errors()->first('name'));
    }
}
