<?php

namespace App\Http\Controllers\Public;

use App\Enums\QualityStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFeedbackRequest;
use App\Models\Batch;
use App\Services\FeedbackService;
use Illuminate\Http\RedirectResponse;

class FeedbackController extends Controller
{
    public function store(StoreFeedbackRequest $request, Batch $batch, string $key, FeedbackService $feedbacks): RedirectResponse
    {
        abort_unless($batch->quality_status === QualityStatus::Passed && hash_equals((string) $batch->qr_key, $key), 404);

        $feedbacks->submit($batch, $request->validated());

        return redirect()->to($batch->publicUrl())->with('status', 'Cảm ơn bạn đã phản hồi. Chúng tôi sẽ xem và xử lý.');
    }
}
