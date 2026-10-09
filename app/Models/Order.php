<?php

namespace App\Models;

use App\Enums\DeliveryMethod;
use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\QualityStatus;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

#[Fillable([
    'code', 'customer_id', 'subscription_id', 'delivery_zone_id', 'order_type', 'channel',
    'delivery_date', 'time_slot', 'delivery_method', 'delivery_address',
    'subtotal', 'shipping_fee', 'discount', 'total',
    'payment_method', 'payment_status', 'status',
    'confirmed_at', 'delivered_at', 'cancel_reason', 'note', 'requested_reward',
])]
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'order_type' => OrderType::class,
            'channel' => OrderChannel::class,
            'delivery_method' => DeliveryMethod::class,
            'payment_method' => PaymentMethod::class,
            'payment_status' => PaymentStatus::class,
            'status' => OrderStatus::class,
            'delivery_date' => 'date',
            'confirmed_at' => 'datetime',
            'delivered_at' => 'datetime',
            'subtotal' => 'integer',
            'shipping_fee' => 'integer',
            'discount' => 'integer',
            'total' => 'integer',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(DeliveryZone::class, 'delivery_zone_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function feedbacks(): HasMany
    {
        return $this->hasMany(Feedback::class);
    }

    public function packagingReturns(): HasMany
    {
        return $this->hasMany(PackagingReturn::class);
    }

    /**
     * Các lô đạt chất lượng trong đơn đã giao, để khách mở trang lô và đánh giá. Cần load sẵn items.batches.product.
     *
     * @return Collection<int, Batch>
     */
    public function feedbackBatches(): Collection
    {
        if ($this->status !== OrderStatus::Delivered) {
            return collect();
        }

        return $this->items->flatMap->batches
            ->filter(fn (Batch $batch) => $batch->quality_status === QualityStatus::Passed)
            ->unique('id')
            ->values();
    }

    /**
     * Tin nhắn nhờ khách đánh giá, người điều phối chép và gửi qua Zalo (MVP chưa có Zalo OA).
     */
    public function feedbackRequestMessage(): string
    {
        $links = $this->feedbackBatches()
            ->map(fn (Batch $batch) => "• {$batch->product->name}: ".route('batches.public', $batch->code))
            ->implode("\n");

        return "FARM2HOME cảm ơn anh/chị {$this->customer->name} đã nhận đơn {$this->code}. "
            ."Anh/chị bấm vào link từng loại rau để xem nguồn gốc và đánh giá giúp tụi em nhé:\n{$links}";
    }
}
