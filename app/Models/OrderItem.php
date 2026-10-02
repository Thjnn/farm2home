<?php

namespace App\Models;

use Database\Factories\OrderItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['order_id', 'product_id', 'basket_id', 'quantity', 'weight_kg', 'unit_price', 'line_total', 'fulfilled_quantity'])]
class OrderItem extends Model
{
    /** @use HasFactory<OrderItemFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'weight_kg' => 'decimal:3',
            'unit_price' => 'integer',
            'line_total' => 'integer',
            'fulfilled_quantity' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function basket(): BelongsTo
    {
        return $this->belongsTo(Basket::class);
    }

    public function batches(): BelongsToMany
    {
        return $this->belongsToMany(Batch::class, 'order_item_batches')->withPivot('quantity');
    }
}
