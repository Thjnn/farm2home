<?php

namespace App\Enums;

enum SupplySource: string
{
    case Zalo = 'zalo';
    case Form = 'form';
    case Operator = 'operator';

    public function label(): string
    {
        return match ($this) {
            self::Zalo => 'Zalo',
            self::Form => 'Link nông hộ',
            self::Operator => 'Operator nhập',
        };
    }
}
