<?php

namespace App\Enums;

enum FarmerStatus: string
{
    case Active = 'active';
    case Paused = 'paused';
    case Inactive = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Đang hợp tác',
            self::Paused => 'Tạm nghỉ',
            self::Inactive => 'Ngừng',
        };
    }
}
