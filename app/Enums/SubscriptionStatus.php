<?php

namespace App\Enums;

enum SubscriptionStatus: string
{
    case Active = 'active';
    case Paused = 'paused';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Đang chạy',
            self::Paused => 'Tạm dừng',
            self::Cancelled => 'Đã hủy',
        };
    }
}
