<?php

/*
 * Menu "+ Tạo mới" ở thanh trên khu quản trị (components/quick-create). Chỉ gồm hành động đã có form.
 * route + anchor: anchor nhảy tới form nằm sẵn trên trang danh sách.
 * color: green = tạo mới, blue = thu hoạch / giao, rose = rau dư, amber = bao bì.
 * roles: vai trò được thấy mục (danh mục chỉ admin sửa được, khớp middleware role:admin).
 * Icon: nét SVG lucide (viewBox 24).
 */

return [
    [
        'group' => 'Bán hàng',
        'items' => [
            [
                'label' => 'Tạo đơn thủ công',
                'description' => 'Cho khách đặt qua điện thoại/Zalo',
                'route' => 'admin.orders.create',
                'color' => 'green',
                'roles' => ['admin', 'operator'],
                'icon' => '<circle cx="8" cy="21" r="1"/><circle cx="19" cy="21" r="1"/><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"/>',
            ],
            [
                'label' => 'Tạo giỏ định kỳ',
                'description' => 'Đăng ký giao rau hằng tuần',
                'route' => 'admin.subscriptions.create',
                'color' => 'green',
                'roles' => ['admin', 'operator'],
                'icon' => '<path d="m17 2 4 4-4 4"/><path d="M3 11v-1a4 4 0 0 1 4-4h14"/><path d="m7 22-4-4 4-4"/><path d="M21 13v1a4 4 0 0 1-4 4H3"/>',
            ],
        ],
    ],
    [
        'group' => 'Thu hoạch & giao',
        'items' => [
            [
                'label' => 'Tạo kế hoạch thu hoạch',
                'description' => 'Tính số kg cần thu, rồi báo HTX',
                'route' => 'admin.harvest-plans.index',
                'anchor' => 'tao-ke-hoach',
                'color' => 'blue',
                'roles' => ['admin', 'operator'],
                'icon' => '<rect width="8" height="4" x="8" y="2" rx="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><path d="M12 11h4"/><path d="M12 16h4"/><path d="M8 11h.01"/><path d="M8 16h.01"/>',
            ],
            [
                'label' => 'Ghi sản lượng HTX',
                'description' => 'Dán tin Zalo HTX báo số kg có thể thu',
                'route' => 'admin.supply.index',
                'anchor' => 'ghi-san-luong',
                'color' => 'blue',
                'roles' => ['admin', 'operator'],
                'icon' => '<path d="M7 20h10"/><path d="M10 20c5.5-2.5.8-6.4 3-10"/><path d="M9.5 9.4c1.1.8 1.8 2.2 2.3 3.7-2 .4-3.5.4-4.8-.3-1.2-.6-2.3-1.9-3-4.2 2.8-.5 4.4 0 5.5.8z"/><path d="M14.1 6a7 7 0 0 0-1.1 4c1.9-.1 3.3-.6 4.3-1.4 1-1 1.6-2.3 1.7-4.6-2.7.1-4 1-4.9 2z"/>',
            ],
            [
                'label' => 'Ghi rau dư',
                'description' => 'Phần dư, hỏng, hết hạn cuối ngày',
                'route' => 'admin.waste.index',
                'anchor' => 'ghi-rau-du',
                'color' => 'rose',
                'roles' => ['admin', 'operator'],
                'icon' => '<path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/>',
            ],
            [
                'label' => 'Ghi bao bì thu hồi',
                'description' => 'Túi, hộp khách trả lại',
                'route' => 'admin.packaging-returns.index',
                'anchor' => 'ghi-bao-bi',
                'color' => 'amber',
                'roles' => ['admin', 'operator'],
                'icon' => '<path d="m7.5 4.27 9 5.15"/><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/>',
            ],
        ],
    ],
    [
        'group' => 'Danh mục',
        'items' => [
            [
                'label' => 'Thêm sản phẩm',
                'description' => 'Một loại rau mới',
                'route' => 'admin.products.create',
                'color' => 'green',
                'roles' => ['admin'],
                'icon' => '<path d="M11 20A7 7 0 0 1 9.8 6.1C15.5 5 17 4.48 19 2c1 2 2 4.18 2 8 0 5.5-4.78 10-10 10Z"/><path d="M2 21c0-3 1.85-5.36 5.08-6C9.5 14.52 12 13 13 12"/>',
            ],
            [
                'label' => 'Tạo giỏ rau',
                'description' => 'Gói combo từ rau lẻ',
                'route' => 'admin.baskets.create',
                'color' => 'green',
                'roles' => ['admin'],
                'icon' => '<path d="m15 11-1 9"/><path d="m19 11-4-7"/><path d="M2 11h20"/><path d="m3.5 11 1.6 7.4a2 2 0 0 0 2 1.6h9.8a2 2 0 0 0 2-1.6l1.7-7.4"/><path d="M4.5 15.5h15"/><path d="m5 11 4-7"/><path d="m9 11 1 9"/>',
            ],
            [
                'label' => 'Thêm HTX',
                'description' => 'Nguồn cung mới',
                'route' => 'admin.farmers.create',
                'color' => 'green',
                'roles' => ['admin'],
                'icon' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
            ],
        ],
    ],
];
