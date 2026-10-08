<?php

namespace Tests\Unit\Support;

use App\Support\ForecastCalculator;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class ForecastCalculatorTest extends TestCase
{
    public function test_moving_average_with_standard_deviation_band(): void
    {
        $this->assertSame(
            ['predicted' => 8.0, 'lower' => 4.0, 'upper' => 12.0],
            ForecastCalculator::movingAverage([2, 4, 6, 8, 10, 12, 14]),
        );
    }

    public function test_moving_average_only_uses_the_last_window(): void
    {
        $this->assertSame(
            ['predicted' => 8.0, 'lower' => 4.0, 'upper' => 12.0],
            ForecastCalculator::movingAverage([100, 2, 4, 6, 8, 10, 12, 14]),
        );
    }

    public function test_moving_average_with_short_history_and_zero_days(): void
    {
        $this->assertSame(['predicted' => 4.0, 'lower' => 3.0, 'upper' => 5.0], ForecastCalculator::movingAverage([3, 5]));
        $this->assertSame(['predicted' => 1.0, 'lower' => 0.18, 'upper' => 1.82], ForecastCalculator::movingAverage([2, 0, 1]));
        $this->assertSame(['predicted' => 0.0, 'lower' => 0.0, 'upper' => 0.0], ForecastCalculator::movingAverage([]));
    }

    public function test_exponential_smoothing_level_and_rmse_band(): void
    {
        $this->assertSame(['predicted' => 13.0, 'lower' => 3.0, 'upper' => 23.0], ForecastCalculator::expSmoothing([10, 20]));
        $this->assertSame(['predicted' => 12.1, 'lower' => 4.72, 'upper' => 19.48], ForecastCalculator::expSmoothing([10, 20, 10]));
        $this->assertSame(['predicted' => 1.28, 'lower' => 0.0, 'upper' => 2.72], ForecastCalculator::expSmoothing([2, 0, 1]));
    }

    public function test_exponential_smoothing_edge_cases(): void
    {
        $this->assertSame(['predicted' => 7.0, 'lower' => 7.0, 'upper' => 7.0], ForecastCalculator::expSmoothing([7]));
        $this->assertSame(['predicted' => 0.0, 'lower' => 0.0, 'upper' => 0.0], ForecastCalculator::expSmoothing([]));
    }

    public function test_weekday_average_only_uses_the_same_weekday(): void
    {
        // 03/10/2026 là Thứ Bảy. Các Thứ Bảy trước: 12/09 = 20, 19/09 = 28, 26/09 = 24.
        $history = [
            '2026-09-12' => 20.0, '2026-09-14' => 2.0, '2026-09-19' => 28.0,
            '2026-09-23' => 5.0, '2026-09-26' => 24.0, '2026-10-01' => 4.0,
        ];

        $result = ForecastCalculator::weekdayAverage($history, CarbonImmutable::parse('2026-10-03'));

        // Độ lệch chuẩn của 20, 28, 24 là √(32/3) ≈ 3,27.
        $this->assertSame(['predicted' => 24.0, 'lower' => 20.73, 'upper' => 27.27], $result);
    }

    public function test_weekday_average_without_matching_days_is_zero(): void
    {
        $this->assertSame(
            ['predicted' => 0.0, 'lower' => 0.0, 'upper' => 0.0],
            ForecastCalculator::weekdayAverage(['2026-10-01' => 4.0], CarbonImmutable::parse('2026-10-03')),
        );
    }

    public function test_error_metrics(): void
    {
        // |2-4|=2, |3-2|=1, |1-0|=1 → MAE 4/3; MAPE bỏ qua actual = 0: (0.5 + 0.5)/2; WAPE 4/6.
        $this->assertSame(
            ['n' => 3, 'mae' => 1.33, 'mape' => 50.0, 'wape' => 66.7, 'bias' => 0.0],
            ForecastCalculator::errors([[2.0, 4.0], [3.0, 2.0], [1.0, 0.0]]),
        );
    }

    public function test_error_metrics_with_no_data_or_zero_actuals(): void
    {
        $this->assertSame(['n' => 0, 'mae' => null, 'mape' => null, 'wape' => null, 'bias' => null], ForecastCalculator::errors([]));
        $this->assertSame(['n' => 1, 'mae' => 1.0, 'mape' => null, 'wape' => null, 'bias' => 1.0], ForecastCalculator::errors([[1.0, 0.0]]));
    }
}
