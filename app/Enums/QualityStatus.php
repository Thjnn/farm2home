<?php

namespace App\Enums;

enum QualityStatus: string
{
    case Pending = 'pending';
    case Passed = 'passed';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Chờ kiểm',
            self::Passed => 'Đạt',
            self::Rejected => 'Không đạt',
        };
    }
}
