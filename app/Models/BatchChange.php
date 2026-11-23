<?php

namespace App\Models;

use App\Enums\QualityStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

#[Fillable(['batch_id', 'user_id', 'changes'])]
class BatchChange extends Model
{
    public const UPDATED_AT = null;

    /**
     * Các trường của lô được ghi nhật ký khi sửa, kèm nhãn hiển thị.
     */
    public const FIELD_LABELS = [
        'quantity_kg' => 'Khối lượng (kg)',
        'grower_name' => 'Hộ trồng',
        'harvested_at' => 'Thu hoạch',
        'processed_at' => 'Sơ chế',
        'packed_at' => 'Đóng gói',
        'processing_note' => 'Ghi chú sơ chế',
        'storage_guide' => 'Cách bảo quản',
        'best_before' => 'Nên dùng trước',
        'quality_status' => 'Kiểm chất lượng',
        'qc_note' => 'Ghi chú kiểm tra',
        'photo' => 'Ảnh lô',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Giá trị lưu thô trong nhật ký, đổi sang chữ dễ đọc cho trang quản trị.
     */
    public static function display(string $field, mixed $value): string
    {
        return match (true) {
            blank($value) => '(trống)',
            $field === 'photo' => 'ảnh',
            $field === 'quality_status' => QualityStatus::tryFrom((string) $value)?->label() ?? (string) $value,
            in_array($field, ['harvested_at', 'processed_at', 'packed_at'], true) => Carbon::parse($value)->format('d/m/Y H:i'),
            $field === 'best_before' => Carbon::parse($value)->format('d/m/Y'),
            default => (string) $value,
        };
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
