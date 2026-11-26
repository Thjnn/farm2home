<?php

namespace App\Support;

/**
 * Chia cụm giao cho người giao: cụm đã có người trong ngày giữ người đó; cụm còn lại, nhiều đơn trước,
 * vào tay người đang ít đơn nhất (bằng nhau thì người có id nhỏ hơn).
 */
class ShipperSplit
{
    /**
     * @param  array<int, int>  $zoneCounts  zoneId => số đơn cần gán
     * @param  array<int, int>  $stickyZones  zoneId => shipperId đã giao cụm đó trong ngày
     * @param  array<int, int>  $loads  shipperId => số đơn đang giữ (chỉ người đang làm)
     * @return array<int, int> zoneId => shipperId, sắp theo zoneId
     */
    public static function assign(array $zoneCounts, array $stickyZones, array $loads): array
    {
        if ($loads === []) {
            return [];
        }

        $result = [];
        $open = [];

        foreach ($zoneCounts as $zoneId => $count) {
            $shipperId = $stickyZones[$zoneId] ?? null;
            if ($shipperId !== null && array_key_exists($shipperId, $loads)) {
                $result[$zoneId] = $shipperId;
                $loads[$shipperId] += $count;
            } else {
                $open[$zoneId] = $count;
            }
        }

        uksort($open, fn (int $a, int $b) => [$open[$b], $a] <=> [$open[$a], $b]);

        foreach ($open as $zoneId => $count) {
            $shipperId = self::leastLoaded($loads);
            $result[$zoneId] = $shipperId;
            $loads[$shipperId] += $count;
        }

        ksort($result);

        return $result;
    }

    /**
     * @param  array<int, int>  $loads
     */
    private static function leastLoaded(array $loads): int
    {
        $best = null;
        foreach ($loads as $shipperId => $load) {
            if ($best === null || [$load, $shipperId] < [$loads[$best], $best]) {
                $best = $shipperId;
            }
        }

        return $best;
    }
}
