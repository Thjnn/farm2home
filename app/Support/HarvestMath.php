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
     * Chia việc thu hoạch theo tỉ lệ sản lượng mỗi nông hộ đã báo, để nhà nhỏ cũng có phần.
     * - Không ai bị giao quá số mình báo (làm tròn xuống 0,5 kg).
     * - Nhà có phần dưới $minKg bị bỏ ra (nhà nhỏ nhất trước) và phần đó chia lại, miễn các nhà còn lại vẫn đủ sức.
     * - Báo không đủ thì mọi nhà giao hết số mình báo, phần thiếu thành một dòng chưa có nông hộ.
     *
     * @param  array<int, float>  $supplyByFarmer  farmer_id => kg đã báo
     * @return list<array{farmer_id: ?int, kg: float}>
     */
    public static function allocate(float $neededKg, array $supplyByFarmer, float $minKg = 0.0): array
    {
        if ($neededKg <= 0) {
            return [];
        }

        $capacity = array_filter(array_map(fn ($kg) => self::floorToHalf((float) $kg), $supplyByFarmer), fn (float $kg) => $kg > 0);
        $total = array_sum($capacity);

        if ($total <= $neededKg) {
            $rows = self::rows($capacity);
            $shortage = round($neededKg - $total, 2);

            return $shortage > 0 ? [...$rows, ['farmer_id' => null, 'kg' => $shortage]] : $rows;
        }

        asort($capacity);
        foreach ($capacity as $farmerId => $kg) {
            $others = $total - $kg;
            if ($neededKg * $kg / $total >= $minKg || $others < $neededKg) {
                break;
            }
            unset($capacity[$farmerId]);
            $total = $others;
        }

        // Chia theo tỉ lệ, làm tròn xuống 0,5 kg; phần lẻ còn lại trao từng 0,5 kg cho nhà bị làm tròn hụt nhiều nhất.
        $shares = array_map(fn (float $kg) => $neededKg * $kg / $total, $capacity);
        $given = array_map(fn (float $share) => self::floorToHalf($share), $shares);
        $left = round($neededKg - array_sum($given), 2);

        while ($left > 0) {
            $step = min(0.5, $left);
            $open = array_filter(array_keys($given), fn ($id) => $capacity[$id] >= $given[$id] + $step);
            if ($open === []) {
                break;
            }
            usort($open, fn ($a, $b) => ($shares[$b] - $given[$b]) <=> ($shares[$a] - $given[$a]) ?: $capacity[$b] <=> $capacity[$a]);
            $given[$open[0]] += $step;
            $left = round($left - $step, 2);
        }

        $rows = self::rows($given);

        return $left > 0 ? [...$rows, ['farmer_id' => null, 'kg' => $left]] : $rows;
    }

    /**
     * @param  array<int, float>  $kgByFarmer
     * @return list<array{farmer_id: int, kg: float}>
     */
    private static function rows(array $kgByFarmer): array
    {
        arsort($kgByFarmer);
        $rows = [];
        foreach ($kgByFarmer as $farmerId => $kg) {
            if ($kg > 0) {
                $rows[] = ['farmer_id' => $farmerId, 'kg' => round($kg, 2)];
            }
        }

        return $rows;
    }
}
