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
    public function store(StoreFeedbackRequest $request, Batch $batch, FeedbackService $feedbacks): RedirectResponse
    {
        abort_unless($batch->quality_status === QualityStatus::Passed, 404);

        $feedbacks->submit($batch, $request->validated());

        return redirect()->route('batches.public', $batch->code)->with('status', 'Cảm ơn bạn đã phản hồi. Chúng tôi sẽ xem và xử lý.');
    }
}
