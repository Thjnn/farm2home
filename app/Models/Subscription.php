<?php

namespace App\Models;

use App\Enums\SubscriptionFrequency;
use App\Enums\SubscriptionStatus;
use Database\Factories\SubscriptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'customer_id', 'basket_id', 'frequency', 'delivery_weekday', 'time_slot',
    'start_date', 'end_date', 'status',
])]
class Subscription extends Model
{
    /** @use HasFactory<SubscriptionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'frequency' => SubscriptionFrequency::class,
            'status' => SubscriptionStatus::class,
            'delivery_weekday' => 'integer',
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function basket(): BelongsTo
    {
        return $this->belongsTo(Basket::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
