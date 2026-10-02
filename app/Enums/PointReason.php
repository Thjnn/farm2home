<?php

namespace App\Enums;

enum PointReason: string
{
    case OrderDelivered = 'order_delivered';
    case PackagingReturned = 'packaging_returned';
    case Redeemed = 'redeemed';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::OrderDelivered => 'Đơn đã giao',
            self::PackagingReturned => 'Trả bao bì',
            self::Redeemed => 'Đổi quà',
            self::Refunded => 'Hoàn điểm (hủy đơn)',
        };
    }
}
