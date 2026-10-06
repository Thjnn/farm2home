<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

final class ForecastCalculator
{
    /**
     * @param  list<float|int>  $history  kg mỗi ngày, cũ → mới, ngày không có đơn là 0
     * @return array{predicted: float, lower: float, upper: float}
     */
    public static function movingAverage(array $history, int $window = 7): array
    {
        $slice = array_slice(array_values($history), -$window);

        if ($slice === []) {
            return ['predicted' => 0.0, 'lower' => 0.0, 'upper' => 0.0];
        }

        $mean = array_sum($slice) / count($slice);
        $sd = sqrt(array_sum(array_map(fn ($x) => ($x - $mean) ** 2, $slice)) / count($slice));

        return self::band($mean, $sd);
    }

    /**
     * Dải ± là RMSE của sai số dự báo một bước.
     *
     * @param  list<float|int>  $history
     * @return array{predicted: float, lower: float, upper: float}
     */
    public static function expSmoothing(array $history, float $alpha = 0.3): array
    {
        $values = array_values($history);

        if ($values === []) {
            return ['predicted' => 0.0, 'lower' => 0.0, 'upper' => 0.0];
        }

        $level = (float) $values[0];
        $squaredErrors = [];

        foreach (array_slice($values, 1) as $x) {
            $squaredErrors[] = ($x - $level) ** 2;
            $level = $alpha * $x + (1 - $alpha) * $level;
        }

        $sd = $squaredErrors === [] ? 0.0 : sqrt(array_sum($squaredErrors) / count($squaredErrors));

        return self::band($level, $sd);
    }

    /**
     * Trung bình của $weeks ngày cùng thứ gần nhất với ngày đích (bắt nhịp cuối tuần).
     *
     * @param  array<string, float|int>  $historyByDate  ['Y-m-d' => kg], cũ → mới
     * @return array{predicted: float, lower: float, upper: float}
     */
    public static function weekdayAverage(array $historyByDate, CarbonInterface $target, int $weeks = 4): array
    {
        $sameWeekday = [];

        foreach ($historyByDate as $date => $kg) {
            if (CarbonImmutable::parse($date)->dayOfWeekIso === $target->dayOfWeekIso) {
                $sameWeekday[] = $kg;
            }
        }

        return self::movingAverage($sameWeekday, $weeks);
    }

    /**
     * MAPE bỏ qua các điểm có thực tế bằng 0; WAPE = Σ|sai số| / Σ thực tế. Cả hai tính bằng %.
     *
     * @param  list<array{0: float, 1: float}>  $pairs  [predicted, actual]
     * @return array{n: int, mae: ?float, mape: ?float, wape: ?float}
     */
    public static function errors(array $pairs): array
    {
        $n = count($pairs);

        if ($n === 0) {
            return ['n' => 0, 'mae' => null, 'mape' => null, 'wape' => null];
        }

        $absolute = array_map(fn ($pair) => abs($pair[0] - $pair[1]), $pairs);
        $sumActual = array_sum(array_map(fn ($pair) => $pair[1], $pairs));

        $percentages = [];
        foreach ($pairs as $i => $pair) {
            if ($pair[1] > 0) {
                $percentages[] = $absolute[$i] / $pair[1];
            }
        }

        return [
            'n' => $n,
            'mae' => round(array_sum($absolute) / $n, 2),
            'mape' => $percentages === [] ? null : round(100 * array_sum($percentages) / count($percentages), 1),
            'wape' => $sumActual > 0 ? round(100 * array_sum($absolute) / $sumActual, 1) : null,
        ];
    }

    /**
     * @return array{predicted: float, lower: float, upper: float}
     */
    private static function band(float $mean, float $sd): array
    {
        return [
            'predicted' => round($mean, 2),
            'lower' => round(max(0.0, $mean - $sd), 2),
            'upper' => round($mean + $sd, 2),
        ];
    }
}
