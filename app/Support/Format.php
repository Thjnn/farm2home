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

    public static function pct(?float $value): string
    {
        if ($value === null) {
            return '—';
        }

        $text = number_format($value, 1, ',', '.');

        return (str_ends_with($text, ',0') ? substr($text, 0, -2) : $text).'%';
    }
}
