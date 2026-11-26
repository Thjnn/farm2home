<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Operator = 'operator';
    case Shipper = 'shipper';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Quản trị',
            self::Operator => 'Vận hành',
            self::Shipper => 'Giao hàng',
        };
    }
}
