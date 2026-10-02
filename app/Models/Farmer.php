<?php

namespace App\Models;

use App\Enums\FarmerStatus;
use Database\Factories\FarmerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'code', 'name', 'phone', 'zalo_phone', 'address', 'growing_area', 'area_m2', 'farming_method',
    'certificate_name', 'certificate_file', 'certificate_expires_at', 'access_token', 'status', 'note',
])]
class Farmer extends Model
{
    /** @use HasFactory<FarmerFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => FarmerStatus::class,
            'certificate_expires_at' => 'date',
            'area_m2' => 'integer',
        ];
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'farmer_products')->withPivot('typical_weekly_kg');
    }

    public function supplyReports(): HasMany
    {
        return $this->hasMany(SupplyReport::class);
    }

    public function harvestPlanItems(): HasMany
    {
        return $this->hasMany(HarvestPlanItem::class);
    }

    public function batches(): HasMany
    {
        return $this->hasMany(Batch::class);
    }

    /**
     * QR ≠ chứng nhận: chỉ coi là có chứng nhận khi có file và chưa hết hạn.
     */
    public function hasValidCertificate(): bool
    {
        return filled($this->certificate_file)
            && ($this->certificate_expires_at === null || today()->lte($this->certificate_expires_at));
    }

    public function zaloLink(): string
    {
        return 'https://zalo.me/'.($this->zalo_phone ?: $this->phone);
    }
}
