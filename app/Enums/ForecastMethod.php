<?php

namespace App\Enums;

enum ForecastMethod: string
{
    case MovingAverage = 'moving_average';
    case ExpSmoothing = 'exp_smoothing';
    case TreeRegression = 'tree_regression';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::MovingAverage => 'Trung bình trượt 7 ngày',
            self::ExpSmoothing => 'Làm mượt hàm mũ',
            self::TreeRegression => 'Cây hồi quy',
            self::Manual => 'Nhập tay',
        };
    }
}
