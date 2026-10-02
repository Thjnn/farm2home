<?php

namespace App\Models;

use App\Enums\QualityStatus;
use Database\Factories\BatchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'code', 'harvest_plan_item_id', 'farmer_id', 'product_id', 'quantity_kg',
    'harvested_at', 'received_at', 'processed_at', 'packed_at', 'processing_note',
    'storage_guide', 'best_before', 'quality_status', 'qc_note',
])]
class Batch extends Model
{
    /** @use HasFactory<BatchFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity_kg' => 'decimal:2',
            'harvested_at' => 'datetime',
            'received_at' => 'datetime',
            'processed_at' => 'datetime',
            'packed_at' => 'datetime',
            'best_before' => 'date',
            'quality_status' => QualityStatus::class,
        ];
    }

    public function planItem(): BelongsTo
    {
        return $this->belongsTo(HarvestPlanItem::class, 'harvest_plan_item_id');
    }

    public function farmer(): BelongsTo
    {
        return $this->belongsTo(Farmer::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function orderItems(): BelongsToMany
    {
        return $this->belongsToMany(OrderItem::class, 'order_item_batches')->withPivot('quantity');
    }

    public function feedbacks(): HasMany
    {
        return $this->hasMany(Feedback::class);
    }

    public function wasteLogs(): HasMany
    {
        return $this->hasMany(WasteLog::class);
    }
}
