<?php

namespace App\Models;

use Database\Factories\UnmetDemandFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['delivery_date', 'product_id', 'quantity_kg', 'note', 'created_by'])]
class UnmetDemand extends Model
{
    /** @use HasFactory<UnmetDemandFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'delivery_date' => 'date',
            'quantity_kg' => 'decimal:2',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
