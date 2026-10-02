<?php

namespace App\Support;

use App\Models\DeliveryZone;

final class ShippingCalculator
{
    public static function fee(int $shippingFee, ?int $freeShipFrom, bool $isPickup, int $subtotal): int
    {
        if ($isPickup) {
            return 0;
        }

        if ($freeShipFrom !== null && $subtotal >= $freeShipFrom) {
            return 0;
        }

        return $shippingFee;
    }

    public static function forZone(DeliveryZone $zone, int $subtotal): int
    {
        return self::fee((int) $zone->shipping_fee, $zone->free_ship_from, $zone->isPickup(), $subtotal);
    }
}
