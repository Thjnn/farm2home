<?php

/*
 * Menu bên trái khu quản trị (layouts/admin). Sắp theo luồng: khách đặt → thu hoạch → đóng gói & giao → dữ liệu gốc.
 * Mỗi mục: route => [nhãn, nét SVG lucide (viewBox 24)]; badge = key trong NotificationService::badges().
 * Nhóm admin_only chỉ hiện với vai trò admin. open = mở sẵn khi người dùng chưa tự đóng/mở.
 */

return [
    'home' => [
        'admin.dashboard' => ['Tổng quan', '<rect width="7" height="9" x="3" y="3" rx="1"/><rect width="7" height="5" x="14" y="3" rx="1"/><rect width="7" height="9" x="14" y="12" rx="1"/><rect width="7" height="5" x="3" y="16" rx="1"/>'],
    ],

    'groups' => [
        'sales' => [
            'label' => 'Bán hàng',
            'open' => true,
            'items' => [
                'admin.orders.index' => ['Đơn hàng', '<circle cx="8" cy="21" r="1"/><circle cx="19" cy="21" r="1"/><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"/>'],
                'admin.subscriptions.index' => ['Giỏ định kỳ', '<path d="m17 2 4 4-4 4"/><path d="M3 11v-1a4 4 0 0 1 4-4h14"/><path d="m7 22-4-4 4-4"/><path d="M21 13v1a4 4 0 0 1-4 4H3"/>'],
                'admin.feedbacks.index' => ['Phản hồi', '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>'],
                'admin.loyalty.index' => ['Điểm thành viên', '<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>'],
            ],
        ],
        'harvest' => [
            'label' => 'Thu hoạch',
            'open' => true,
            'items' => [
                'admin.forecasts.index' => ['Dự báo', '<polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/><polyline points="16 7 22 7 22 13"/>'],
                'admin.harvest-plans.index' => ['Kế hoạch thu hoạch', '<rect width="8" height="4" x="8" y="2" rx="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><path d="M12 11h4"/><path d="M12 16h4"/><path d="M8 11h.01"/><path d="M8 16h.01"/>'],
                'admin.supply.index' => ['Sản lượng', '<path d="M7 20h10"/><path d="M10 20c5.5-2.5.8-6.4 3-10"/><path d="M9.5 9.4c1.1.8 1.8 2.2 2.3 3.7-2 .4-3.5.4-4.8-.3-1.2-.6-2.3-1.9-3-4.2 2.8-.5 4.4 0 5.5.8z"/><path d="M14.1 6a7 7 0 0 0-1.1 4c1.9-.1 3.3-.6 4.3-1.4 1-1 1.6-2.3 1.7-4.6-2.7.1-4 1-4.9 2z"/>'],
            ],
        ],
        'warehouse' => [
            'label' => 'Kho & Giao',
            'open' => true,
            'items' => [
                'admin.batches.index' => ['Lô hàng', '<rect width="5" height="5" x="3" y="3" rx="1"/><rect width="5" height="5" x="16" y="3" rx="1"/><rect width="5" height="5" x="3" y="16" rx="1"/><path d="M21 16h-3a2 2 0 0 0-2 2v3"/><path d="M21 21v.01"/><path d="M12 7v3a2 2 0 0 1-2 2H7"/><path d="M3 12h.01"/><path d="M12 3h.01"/><path d="M12 16v.01"/><path d="M16 12h1"/><path d="M21 12v.01"/><path d="M12 21v-1"/>'],
                'admin.packaging-returns.index' => ['Bao bì', '<path d="m7.5 4.27 9 5.15"/><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/>'],
                'admin.sessions.index' => ['Đối chiếu buổi giao', '<path d="M3 3v18h18"/><path d="M7 16v-3"/><path d="M11 16V9"/><path d="M15 16v-5"/><path d="M19 16V6"/>'],
                'admin.waste.index' => ['Rau dư', '<path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/>'],
            ],
        ],
        'catalog' => [
            'label' => 'Danh mục',
            'open' => false,
            'admin_only' => true,
            'items' => [
                'admin.products.index' => ['Sản phẩm', '<path d="M11 20A7 7 0 0 1 9.8 6.1C15.5 5 17 4.48 19 2c1 2 2 4.18 2 8 0 5.5-4.78 10-10 10Z"/><path d="M2 21c0-3 1.85-5.36 5.08-6C9.5 14.52 12 13 13 12"/>'],
                'admin.baskets.index' => ['Giỏ rau', '<path d="m15 11-1 9"/><path d="m19 11-4-7"/><path d="M2 11h20"/><path d="m3.5 11 1.6 7.4a2 2 0 0 0 2 1.6h9.8a2 2 0 0 0 2-1.6l1.7-7.4"/><path d="M4.5 15.5h15"/><path d="m5 11 4-7"/><path d="m9 11 1 9"/>'],
                'admin.farmers.index' => ['HTX', '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>'],
                'admin.zones.index' => ['Cụm giao', '<path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/>'],
                'admin.users.index' => ['Tài khoản', '<path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>'],
            ],
        ],
    ],

    'badges' => [
        'admin.orders.index' => ['key' => 'orders', 'label' => 'đơn chờ xử lý'],
        'admin.feedbacks.index' => ['key' => 'feedbacks', 'label' => 'phản hồi mới'],
    ],
];
