<?php

namespace App\Support;

final class HarvestMath
{
    public static function ceilToHalf(float $kg): float
    {
        return ceil(round(max(0.0, $kg) * 2, 6)) / 2;
    }

    public static function floorToHalf(float $kg): float
    {
        return floor(round(max(0.0, $kg) * 2, 6)) / 2;
    }

    /**
     * suggested = ceil_to_0.5( max(0, confirmed + extra − stock) × (1 + margin/100) )
     */
    public static function suggestedKg(float $confirmedKg, float $forecastExtraKg, float $stockKg, float $marginPct): float
    {
        return self::ceilToHalf(max(0.0, $confirmedKg + $forecastExtraKg - $stockKg) * (1 + $marginPct / 100));
    }

    /**
     * Quyết định D5: nông hộ báo nhiều nhất trước, mỗi phần làm tròn xuống 0,5 kg;
     * phần thiếu trở thành một dòng chưa có nông hộ.
     *
     * @param  array<int, float>  $supplyByFarmer  farmer_id => kg đã báo
     * @return list<array{farmer_id: ?int, kg: float}>
     */
    public static function allocate(float $neededKg, array $supplyByFarmer): array
    {
        if ($neededKg <= 0) {
            return [];
        }

        arsort($supplyByFarmer);

        $rows = [];
        $remaining = $neededKg;

        foreach ($supplyByFarmer as $farmerId => $availableKg) {
            if ($remaining <= 0) {
                break;
            }

            $take = min($remaining, self::floorToHalf((float) $availableKg));

            if ($take <= 0) {
                continue;
            }

            $rows[] = ['farmer_id' => $farmerId, 'kg' => $take];
            $remaining = round($remaining - $take, 2);
        }

        if ($remaining > 0) {
            $rows[] = ['farmer_id' => null, 'kg' => $remaining];
        }

        return $rows;
    }
}
