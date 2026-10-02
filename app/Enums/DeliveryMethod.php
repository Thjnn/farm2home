<?php

namespace App\Enums;

enum DeliveryMethod: string
{
    case Home = 'home';
    case Pickup = 'pickup';

    public function label(): string
    {
        return match ($this) {
            self::Home => 'Giao tận nhà',
            self::Pickup => 'Nhận tại điểm',
        };
    }
}
