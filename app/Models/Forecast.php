<?php

namespace App\Models;

use App\Enums\ForecastMethod;
use Database\Factories\ForecastFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'product_id', 'target_date', 'generated_on', 'method', 'confirmed_kg', 'predicted_extra_kg',
    'predicted_total_kg', 'lower_kg', 'upper_kg', 'actual_kg',
])]
class Forecast extends Model
{
    /** @use HasFactory<ForecastFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'target_date' => 'date',
            'generated_on' => 'date',
            'method' => ForecastMethod::class,
            'confirmed_kg' => 'decimal:2',
            'predicted_extra_kg' => 'decimal:2',
            'predicted_total_kg' => 'decimal:2',
            'lower_kg' => 'decimal:2',
            'upper_kg' => 'decimal:2',
            'actual_kg' => 'decimal:2',
        ];
    }

    /**
     * Phần khách thực sự đặt thêm sau lúc dự báo (thực tế − đã đặt lúc đó). Đơn bị hủy sau đó không làm số này âm.
     */
    public function actualExtraKg(): ?float
    {
        return $this->actual_kg === null ? null : max(0.0, round((float) $this->actual_kg - (float) $this->confirmed_kg, 2));
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
