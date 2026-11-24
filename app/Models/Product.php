<?php

namespace App\Models;

use App\Enums\ProductCategory;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'code', 'name', 'slug', 'category', 'pack_weight_kg', 'price', 'cost_price_per_kg',
    'shelf_life_days', 'storage_guide', 'image', 'is_active',
])]
class Product extends Model
{
    public const UPLOAD_DIRECTORY = 'product-photos';

    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'pack_weight_kg' => 'decimal:3',
            'price' => 'integer',
            'cost_price_per_kg' => 'integer',
            'shelf_life_days' => 'integer',
            'is_active' => 'boolean',
            'category' => ProductCategory::class,
        ];
    }

    public function farmers(): BelongsToMany
    {
        return $this->belongsToMany(Farmer::class, 'farmer_products')->withPivot('typical_weekly_kg');
    }

    public function basketItems(): HasMany
    {
        return $this->hasMany(BasketItem::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function supplyReports(): HasMany
    {
        return $this->hasMany(SupplyReport::class);
    }

    /**
     * Ảnh admin tải lên nằm trên disk "public" (thư mục product-photos); ảnh mẫu đi kèm code nằm trong public/images.
     */
    public function imageUrl(): ?string
    {
        if (blank($this->image)) {
            return null;
        }

        return str_starts_with($this->image, self::UPLOAD_DIRECTORY.'/') ? asset('storage/'.$this->image) : asset('images/'.$this->image);
    }

    public function forecasts(): HasMany
    {
        return $this->hasMany(Forecast::class);
    }

    public function batches(): HasMany
    {
        return $this->hasMany(Batch::class);
    }
}
