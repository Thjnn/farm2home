<?php

namespace App\Models;

use App\Enums\HarvestPlanStatus;
use Database\Factories\HarvestPlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'harvest_date', 'delivery_date', 'safety_margin_pct', 'status',
    'created_by', 'confirmed_by', 'confirmed_at', 'note',
])]
class HarvestPlan extends Model
{
    /** @use HasFactory<HarvestPlanFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'harvest_date' => 'date',
            'delivery_date' => 'date',
            'safety_margin_pct' => 'decimal:2',
            'status' => HarvestPlanStatus::class,
            'confirmed_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(HarvestPlanItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }
}
