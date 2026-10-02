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

    'loyalty' => [
        // +điểm khi đơn giao xong và khi khách trả bao bì.
        'order_points' => 5,
        'packaging_points' => 5,
        // Đơn có tiền hàng dưới ngưỡng này không được cộng điểm đơn (D27).
        'min_order_subtotal' => 40000,
        'rewards' => [
            'small_bunch' => ['label' => 'Tặng bó rau nhỏ', 'points' => 20, 'type' => 'gift', 'packs' => 1],
            'free_shipping' => ['label' => 'Miễn phí 1 lần giao hàng', 'points' => 30, 'type' => 'free_shipping'],
            'discount_20k' => ['label' => 'Giảm 20.000đ cho đơn', 'points' => 50, 'type' => 'discount', 'amount' => 20000],
            // D26: combo = 3 gói rau do người điều phối chọn.
            'small_combo' => ['label' => 'Tặng combo rau nhỏ', 'points' => 100, 'type' => 'gift', 'packs' => 3],
        ],
    ],
];
