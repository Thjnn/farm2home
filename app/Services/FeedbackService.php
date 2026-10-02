<?php

namespace App\Services;

use App\Enums\FeedbackStatus;
use App\Models\Batch;
use App\Models\Customer;
use App\Models\Feedback;
use App\Models\Order;
use App\Models\User;
use DomainException;

class FeedbackService
{
    /**
     * Số điện thoại chỉ dùng để nối với khách và đơn đã có; không lưu lại.
     *
     * @param  array{rating: int|string, freshness_rating?: int|string|null, comment?: ?string, phone?: ?string}  $data
     */
    public function submit(Batch $batch, array $data): Feedback
    {
        $customer = filled($data['phone'] ?? null) ? Customer::where('phone', $data['phone'])->first() : null;

        $order = $customer === null ? null : Order::where('customer_id', $customer->id)
            ->whereHas('items.batches', fn ($query) => $query->where('batches.id', $batch->id))
            ->orderByDesc('delivery_date')
            ->first();

        return Feedback::create([
            'batch_id' => $batch->id,
            'order_id' => $order?->id,
            'customer_id' => $customer?->id,
            'rating' => (int) $data['rating'],
            'freshness_rating' => isset($data['freshness_rating']) ? (int) $data['freshness_rating'] : null,
            'comment' => $data['comment'] ?? null,
            'status' => FeedbackStatus::New,
        ]);
    }

    /**
     * @throws DomainException khi đóng phản hồi mà chưa ghi cách xử lý
     */
    public function respond(Feedback $feedback, FeedbackStatus $status, ?string $response, User $by): Feedback
    {
        if ($status === FeedbackStatus::Resolved && blank($response)) {
            throw new DomainException('Cần ghi cách xử lý trước khi đóng phản hồi.');
        }

        $feedback->update([
            'status' => $status,
            'response' => $response,
            'resolved_by' => $status === FeedbackStatus::Resolved ? $by->id : null,
            'resolved_at' => $status === FeedbackStatus::Resolved ? now() : null,
        ]);

        return $feedback;
    }
}
