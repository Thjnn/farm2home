<?php

namespace App\Models;

use Database\Factories\DeliveryZoneFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'description', 'shipping_fee', 'free_ship_from', 'pickup_point', 'is_active'])]
class DeliveryZone extends Model
{
    /** @use HasFactory<DeliveryZoneFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'shipping_fee' => 'integer',
            'free_ship_from' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function isPickup(): bool
    {
        return $this->pickup_point !== null;
    }
}
