<?php

namespace App\Enums;

enum OrderType: string
{
    case Basket = 'basket';
    case Single = 'single';

    public function label(): string
    {
        return match ($this) {
            self::Basket => 'Giỏ rau',
            self::Single => 'Chọn lẻ',
        };
    }
}
