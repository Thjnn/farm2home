<?php

return [
    // Biên an toàn mặc định (%) khi gợi ý thu hoạch.
    'safety_margin_pct' => 10,

    'forecast' => [
        'default_method' => 'moving_average',
        'window_days' => 7,
        'alpha' => 0.3,
        'horizon_days' => 7,
        'history_days' => 28,
    ],

    'order' => [
        // Đơn cho ngày mai đóng lúc 18:00 hôm nay.
        'cutoff_hour' => 18,
        'max_days_ahead' => 7,
        'time_slots' => ['06:00-08:00', '10:00-12:00', '17:00-19:00'],
    ],

    'batch' => [
        'harvest_time' => '05:30',
    ],
];
