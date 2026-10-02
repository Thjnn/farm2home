<?php

namespace Tests\Unit\Support;

use App\Support\Format;
use PHPUnit\Framework\TestCase;

class FormatTest extends TestCase
{
    public function test_vnd_uses_dot_thousands_and_dong_suffix(): void
    {
        $this->assertSame('12.000đ', Format::vnd(12000));
        $this->assertSame('1.150.000đ', Format::vnd(1150000));
        $this->assertSame('0đ', Format::vnd(0));
    }

    public function test_kg_uses_comma_decimal_and_trims_zeros(): void
    {
        $this->assertSame('2,5 kg', Format::kg('2.50'));
        $this->assertSame('3 kg', Format::kg('3.00'));
        $this->assertSame('0,25 kg', Format::kg(0.25));
        $this->assertSame('10 kg', Format::kg(10));
    }
}
