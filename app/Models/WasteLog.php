<?php

namespace App\Models;

use App\Enums\WasteReason;
use Database\Factories\WasteLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['log_date', 'product_id', 'batch_id', 'quantity_kg', 'reason', 'note', 'created_by'])]
class WasteLog extends Model
{
    /** @use HasFactory<WasteLogFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'log_date' => 'date',
            'quantity_kg' => 'decimal:2',
            'reason' => WasteReason::class,
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
