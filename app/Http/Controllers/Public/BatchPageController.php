<?php

namespace App\Http\Controllers\Public;

use App\Enums\QualityStatus;
use App\Http\Controllers\Controller;
use App\Models\Batch;
use Illuminate\View\View;

class BatchPageController extends Controller
{
    /**
     * Trang QR công khai: chỉ lô đã đạt kiểm tra và đúng khoá QR mới được hiển thị.
     */
    public function __invoke(Batch $batch, string $key): View
    {
        abort_unless($batch->quality_status === QualityStatus::Passed && hash_equals((string) $batch->qr_key, $key), 404);

        return view('public.batches.show', ['batch' => $batch->load('product', 'farmer')]);
    }
}
