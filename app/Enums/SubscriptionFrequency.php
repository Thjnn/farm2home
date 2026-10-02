<?php

namespace App\Enums;

enum SubscriptionFrequency: string
{
    case Weekly = 'weekly';
    case Biweekly = 'biweekly';
    case Monthly = 'monthly';

    public function label(): string
    {
        return match ($this) {
            self::Weekly => 'Hằng tuần',
            self::Biweekly => '2 tuần/lần',
            self::Monthly => '4 tuần/lần',
        };
    }
}
