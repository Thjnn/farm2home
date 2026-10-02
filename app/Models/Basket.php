<?php

namespace App\Models;

use Database\Factories\BasketFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'description', 'household_min', 'household_max', 'price', 'is_active'])]
class Basket extends Model
{
    /** @use HasFactory<BasketFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'household_min' => 'integer',
            'household_max' => 'integer',
            'price' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(BasketItem::class);
    }
}
