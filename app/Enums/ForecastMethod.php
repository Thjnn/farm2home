<?php

namespace App\Enums;

enum ForecastMethod: string
{
    case MovingAverage = 'moving_average';
    case ExpSmoothing = 'exp_smoothing';
    case TreeRegression = 'tree_regression';
    case Manual = 'manual';
    case WeekdayAverage = 'weekday_average';

    public function label(): string
    {
        return match ($this) {
            self::MovingAverage => 'Trung bình trượt 7 ngày',
            self::ExpSmoothing => 'Làm mượt hàm mũ',
            self::TreeRegression => 'Cây hồi quy',
            self::Manual => 'Nhập tay',
            self::WeekdayAverage => 'Trung bình cùng thứ (4 tuần)',
        };
    }

    /**
     * Cách đoán, nói bằng lời thường cho người không chuyên.
     */
    public function plainExplanation(): string
    {
        return match ($this) {
            self::MovingAverage => 'Lấy trung bình số rau bán được trong 7 ngày gần nhất.',
            self::ExpSmoothing => 'Xem các ngày đã qua, nhưng coi trọng mấy ngày gần đây hơn.',
            self::TreeRegression => 'Mô hình học máy, sẽ dùng khi có đủ dữ liệu.',
            self::Manual => 'Người điều phối tự nhập.',
            self::WeekdayAverage => 'Lấy trung bình của cùng thứ trong 4 tuần trước (ví dụ các ngày Thứ Bảy).',
        };
    }
}
