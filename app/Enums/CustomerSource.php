<?php

namespace App\Enums;

enum CustomerSource: string
{
    case Zalo = 'zalo';
    case Form = 'form';
    case Website = 'website';
    case Phone = 'phone';
    case Referral = 'referral';

    public function label(): string
    {
        return match ($this) {
            self::Zalo => 'Zalo',
            self::Form => 'Form',
            self::Website => 'Website',
            self::Phone => 'Điện thoại',
            self::Referral => 'Giới thiệu',
        };
    }
}
