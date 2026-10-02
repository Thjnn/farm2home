<?php

namespace App\Support;

use App\Enums\SubscriptionFrequency;
use Carbon\CarbonImmutable;

final class SubscriptionSchedule
{
    /**
     * Giỏ định kỳ có đến lịch giao vào $date không. Weekly: mỗi tuần; biweekly: cách tuần;
     * monthly: mỗi 4 tuần — đều tính từ lần giao đầu tiên (đúng thứ giao, vào hoặc sau ngày bắt đầu).
     *
     * @param  int  $weekday  1 = Thứ Hai … 7 = Chủ nhật
     */
    public static function isDue(SubscriptionFrequency $frequency, int $weekday, CarbonImmutable $start, ?CarbonImmutable $end, CarbonImmutable $date): bool
    {
        $date = $date->startOfDay();
        $start = $start->startOfDay();

        if ($date->dayOfWeekIso !== $weekday || $date->lt($start) || ($end !== null && $date->gt($end->startOfDay()))) {
            return false;
        }

        // Carbon::next() nhận 0 = Chủ nhật.
        $first = $start->dayOfWeekIso === $weekday ? $start : $start->next($weekday % 7);
        $weeks = intdiv((int) $first->diffInDays($date), 7);

        return match ($frequency) {
            SubscriptionFrequency::Weekly => true,
            SubscriptionFrequency::Biweekly => $weeks % 2 === 0,
            SubscriptionFrequency::Monthly => $weeks % 4 === 0,
        };
    }
}
