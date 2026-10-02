<?php

namespace App\Models;

use Database\Factories\BasketItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['basket_id', 'product_id', 'quantity', 'allow_substitute'])]
class BasketItem extends Model
{
    /** @use HasFactory<BasketItemFactory> */
    use HasFactory;

    /**
     * The basket_items table has no created_at/updated_at columns.
     */
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'allow_substitute' => 'boolean',
        ];
    }

    public function basket(): BelongsTo
    {
        return $this->belongsTo(Basket::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
