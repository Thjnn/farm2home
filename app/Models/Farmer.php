<?php

namespace App\Models;

use App\Enums\FarmerStatus;
use Database\Factories\FarmerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'code', 'name', 'phone', 'zalo_phone', 'address', 'growing_area', 'area_m2', 'farming_method',
    'bank_name', 'bank_account_number', 'bank_account_holder', 'farm_photos',
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
            'bank_account_number' => 'encrypted',
            'farm_photos' => 'array',
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

    /**
     * Số ngày tới khi chứng nhận hết hạn (âm = đã hết hạn); null khi không có file hoặc không ghi hạn.
     */
    public function certificateDaysLeft(): ?int
    {
        if (blank($this->certificate_file) || $this->certificate_expires_at === null) {
            return null;
        }

        return (int) today()->diffInDays($this->certificate_expires_at, false);
    }

    /**
     * Nông hộ đang hợp tác có chứng nhận sắp hết hạn hoặc đã hết hạn, cần nhắc gia hạn.
     *
     * @return Collection<int, Farmer>
     */
    public static function certificatesNeedingRenewal(): Collection
    {
        return static::query()
            ->where('status', '!=', FarmerStatus::Inactive)
            ->whereNotNull('certificate_file')
            ->whereDate('certificate_expires_at', '<=', today()->addDays((int) config('farm2home.certificate_warning_days')))
            ->orderBy('certificate_expires_at')
            ->get();
    }

    public function feedbacks(): HasManyThrough
    {
        return $this->hasManyThrough(Feedback::class, Batch::class);
    }

    /**
     * Điểm trung bình khách chấm cho các lô của nông hộ. Độ tươi tính riêng vì sao chung có thể bị kéo xuống do giao trễ.
     *
     * @return array{count: int, rating: ?float, freshness: ?float}
     */
    public function ratingSummary(): array
    {
        $row = $this->feedbacks()
            ->toBase()
            ->selectRaw('COUNT(*) as total, AVG(feedbacks.rating) as rating, AVG(feedbacks.freshness_rating) as freshness')
            ->first();

        return [
            'count' => (int) $row->total,
            'rating' => $row->rating === null ? null : round((float) $row->rating, 1),
            'freshness' => $row->freshness === null ? null : round((float) $row->freshness, 1),
        ];
    }

    /**
     * Trang QR chỉ hiện sao khi đủ lượt đánh giá, để vài lượt đầu không quyết định ấn tượng về nông hộ.
     */
    public function hasEnoughRatingsToShow(int $count): bool
    {
        return $count >= (int) config('farm2home.farmer_rating_min_count');
    }

    /**
     * Ví dụ "Agribank ••• 6789": đủ để nông hộ nhận ra tài khoản của mình mà không lộ số đầy đủ.
     */
    public function maskedBankAccount(): ?string
    {
        if (blank($this->bank_account_number)) {
            return null;
        }

        return trim($this->bank_name.' ••• '.substr($this->bank_account_number, -4));
    }

    /**
     * @return list<string>
     */
    public function farmPhotoUrls(): array
    {
        return array_map(fn (string $path) => Storage::disk('public')->url($path), $this->farm_photos ?? []);
    }

    public function zaloLink(): string
    {
        return 'https://zalo.me/'.($this->zalo_phone ?: $this->phone);
    }
}
