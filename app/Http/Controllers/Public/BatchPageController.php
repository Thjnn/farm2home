<?php

namespace App\Http\Controllers\Public;

use App\Enums\QualityStatus;
use App\Http\Controllers\Controller;
use App\Models\Batch;
use Illuminate\View\View;

class BatchPageController extends Controller
{
    /**
     * Trang QR công khai: chỉ lô đã đạt kiểm tra mới được hiển thị.
     */
    public function __invoke(Batch $batch): View
    {
        abort_unless($batch->quality_status === QualityStatus::Passed, 404);

        return view('public.batches.show', ['batch' => $batch->load('product', 'farmer')]);
    }
}
