<?php

namespace App\Models;

use App\Enums\SupplySource;
use Database\Factories\SupplyReportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['farmer_id', 'product_id', 'available_date', 'quantity_kg', 'source', 'note'])]
class SupplyReport extends Model
{
    /** @use HasFactory<SupplyReportFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'available_date' => 'date',
            'quantity_kg' => 'decimal:2',
            'source' => SupplySource::class,
        ];
    }

    public function farmer(): BelongsTo
    {
        return $this->belongsTo(Farmer::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
