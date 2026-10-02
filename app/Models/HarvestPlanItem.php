<?php

namespace App\Models;

use Database\Factories\HarvestPlanItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'harvest_plan_id', 'product_id', 'farmer_id', 'confirmed_order_kg', 'forecast_extra_kg',
    'available_stock_kg', 'suggested_kg', 'final_kg', 'received_kg', 'adjust_reason', 'farmer_confirmed_at',
])]
class HarvestPlanItem extends Model
{
    /** @use HasFactory<HarvestPlanItemFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'confirmed_order_kg' => 'decimal:2',
            'forecast_extra_kg' => 'decimal:2',
            'available_stock_kg' => 'decimal:2',
            'suggested_kg' => 'decimal:2',
            'final_kg' => 'decimal:2',
            'received_kg' => 'decimal:2',
            'farmer_confirmed_at' => 'datetime',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(HarvestPlan::class, 'harvest_plan_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function farmer(): BelongsTo
    {
        return $this->belongsTo(Farmer::class);
    }

    public function batch(): HasOne
    {
        return $this->hasOne(Batch::class);
    }
}
