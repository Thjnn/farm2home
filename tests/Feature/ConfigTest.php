<?php

namespace Tests\Feature;

use Tests\TestCase;

class ConfigTest extends TestCase
{
    public function test_app_uses_vietnam_timezone_and_locale(): void
    {
        $this->assertSame('Asia/Ho_Chi_Minh', config('app.timezone'));
        $this->assertSame('vi', config('app.locale'));
        $this->assertSame('+07:00', config('database.connections.mysql.timezone'));
    }

    public function test_business_defaults_match_the_spec(): void
    {
        $this->assertSame(10, config('farm2home.safety_margin_pct'));
        $this->assertSame('moving_average', config('farm2home.forecast.default_method'));
        $this->assertSame(7, config('farm2home.forecast.window_days'));
        $this->assertSame(0.3, config('farm2home.forecast.alpha'));
        $this->assertSame(7, config('farm2home.forecast.horizon_days'));
        $this->assertSame(18, config('farm2home.order.cutoff_hour'));
        $this->assertSame(['06:00-08:00', '10:00-12:00', '17:00-19:00'], config('farm2home.order.time_slots'));
    }
}
