<?php

namespace App\Enums;

enum HarvestPlanStatus: string
{
    case Draft = 'draft';
    case Confirmed = 'confirmed';
    case Sent = 'sent';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Nháp',
            self::Confirmed => 'Đã chốt',
            self::Sent => 'Đã gửi nông hộ',
            self::Completed => 'Hoàn tất',
        };
    }
}
