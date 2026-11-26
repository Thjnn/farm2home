<?php

namespace App\Support;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;

/**
 * Số tiền người giao cần thu khi giao đơn. Chỗ duy nhất tính khoản này: khi có luật đặt cọc thì sửa ở đây.
 */
class AmountToCollect
{
    public static function for(Order $order): int
    {
        return $order->payment_method === PaymentMethod::Cod && $order->payment_status === PaymentStatus::Unpaid
            ? $order->total
            : 0;
    }
}
