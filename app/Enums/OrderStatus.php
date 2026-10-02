<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Packing = 'packing';
    case Delivering = 'delivering';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Chờ xác nhận',
            self::Confirmed => 'Đã xác nhận',
            self::Packing => 'Đang đóng gói',
            self::Delivering => 'Đang giao',
            self::Delivered => 'Đã giao',
            self::Cancelled => 'Đã hủy',
        };
    }

    /** @return list<self> */
    public function next(): array
    {
        return match ($this) {
            self::Pending => [self::Confirmed, self::Cancelled],
            self::Confirmed => [self::Packing, self::Cancelled],
            self::Packing => [self::Delivering, self::Cancelled],
            self::Delivering => [self::Delivered, self::Cancelled],
            self::Delivered, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->next(), true);
    }

    /**
     * Đơn chưa hủy đều được tính vào nhu cầu (quyết định D3).
     */
    public function countsAsDemand(): bool
    {
        return $this !== self::Cancelled;
    }
}
