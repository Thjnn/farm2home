<?php

namespace App\Models;

use App\Enums\CustomerSource;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name', 'phone', 'zalo_phone', 'address', 'delivery_zone_id', 'household_size',
    'budget_per_week', 'preferences', 'source', 'data_consent', 'note',
])]
class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'address' => 'encrypted',
            'household_size' => 'integer',
            'budget_per_week' => 'integer',
            'preferences' => 'array',
            'source' => CustomerSource::class,
            'data_consent' => 'boolean',
        ];
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(DeliveryZone::class, 'delivery_zone_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function feedbacks(): HasMany
    {
        return $this->hasMany(Feedback::class);
    }

    public function pointTransactions(): HasMany
    {
        return $this->hasMany(PointTransaction::class);
    }

    public function zaloLink(): string
    {
        return 'https://zalo.me/'.($this->zalo_phone ?: $this->phone);
    }
}
