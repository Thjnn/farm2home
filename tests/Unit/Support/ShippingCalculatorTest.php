<?php

namespace Tests\Unit\Support;

use App\Support\ShippingCalculator;
use PHPUnit\Framework\TestCase;

class ShippingCalculatorTest extends TestCase
{
    public function test_zone_fee_applies_below_the_free_shipping_threshold(): void
    {
        $this->assertSame(10000, ShippingCalculator::fee(10000, 150000, false, 149999));
    }

    public function test_shipping_is_free_from_the_threshold(): void
    {
        $this->assertSame(0, ShippingCalculator::fee(10000, 150000, false, 150000));
    }

    public function test_zone_without_threshold_always_charges(): void
    {
        $this->assertSame(15000, ShippingCalculator::fee(15000, null, false, 999999));
    }

    public function test_pickup_is_always_free(): void
    {
        $this->assertSame(0, ShippingCalculator::fee(10000, null, true, 1000));
    }
}
