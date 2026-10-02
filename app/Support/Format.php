<?php

namespace App\Support;

final class Format
{
    public static function vnd(int $amount): string
    {
        return number_format($amount, 0, ',', '.').'đ';
    }

    public static function kg(float|string $kg): string
    {
        return rtrim(rtrim(number_format((float) $kg, 2, ',', '.'), '0'), ',').' kg';
    }
}
