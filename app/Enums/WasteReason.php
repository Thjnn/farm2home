<?php

namespace App\Enums;

enum WasteReason: string
{
    case Surplus = 'surplus';
    case Damaged = 'damaged';
    case Expired = 'expired';
    case Discounted = 'discounted';

    public function label(): string
    {
        return match ($this) {
            self::Surplus => 'Dư thừa',
            self::Damaged => 'Hư hỏng',
            self::Expired => 'Hết hạn',
            self::Discounted => 'Bán giảm giá',
        };
    }
}
