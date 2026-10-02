<?php

namespace App\Enums;

enum OrderChannel: string
{
    case Zalo = 'zalo';
    case Form = 'form';
    case Website = 'website';
    case Phone = 'phone';

    public function label(): string
    {
        return match ($this) {
            self::Zalo => 'Zalo',
            self::Form => 'Form',
            self::Website => 'Website',
            self::Phone => 'Điện thoại',
        };
    }
}
