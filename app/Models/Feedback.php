<?php

namespace App\Models;

use App\Enums\FeedbackStatus;
use Database\Factories\FeedbackFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['batch_id', 'order_id', 'customer_id', 'rating', 'freshness_rating', 'comment', 'status', 'response', 'resolved_by', 'resolved_at'])]
class Feedback extends Model
{
    /** @use HasFactory<FeedbackFactory> */
    use HasFactory;

    /**
     * Laravel would infer "feedback" (uncountable); the real table is "feedbacks".
     *
     * @var string
     */
    protected $table = 'feedbacks';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => FeedbackStatus::class,
            'rating' => 'integer',
            'freshness_rating' => 'integer',
            'resolved_at' => 'datetime',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
