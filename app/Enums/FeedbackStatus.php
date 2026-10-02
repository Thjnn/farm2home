<?php

namespace App\Enums;

enum FeedbackStatus: string
{
    case New = 'new';
    case Processing = 'processing';
    case Resolved = 'resolved';

    public function label(): string
    {
        return match ($this) {
            self::New => 'Mới',
            self::Processing => 'Đang xử lý',
            self::Resolved => 'Đã xử lý',
        };
    }
}
