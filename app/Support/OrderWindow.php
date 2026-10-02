<?php

namespace App\Support;

use Carbon\CarbonImmutable;

final class OrderWindow
{
    /**
     * Trước giờ chốt: còn nhận đơn cho ngày mai. Từ giờ chốt: sớm nhất là ngày kia.
     */
    public static function earliest(CarbonImmutable $now, int $cutoffHour): CarbonImmutable
    {
        return $now->startOfDay()->addDays($now->hour < $cutoffHour ? 1 : 2);
    }

    public static function latest(CarbonImmutable $now, int $maxDaysAhead): CarbonImmutable
    {
        return $now->startOfDay()->addDays($maxDaysAhead);
    }
}
