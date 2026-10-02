<?php

namespace App\Models;

use Database\Factories\PackagingReturnFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['return_date', 'customer_id', 'order_id', 'quantity_returned', 'quantity_reused', 'note'])]
class PackagingReturn extends Model
{
    /** @use HasFactory<PackagingReturnFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'return_date' => 'date',
            'quantity_returned' => 'integer',
            'quantity_reused' => 'integer',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
