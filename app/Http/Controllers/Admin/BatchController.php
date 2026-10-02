<?php

namespace App\Http\Controllers\Admin;

use App\Enums\QualityStatus;
use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Services\BatchService;
use App\Services\QrCodeService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BatchController extends Controller
{
    public function index(Request $request, BatchService $batches): View
    {
        $date = $request->validate(['date' => ['nullable', 'date_format:Y-m-d']])['date'] ?? today()->toDateString();

        $rows = Batch::with('product', 'farmer')->whereDate('harvested_at', $date)->orderBy('product_id')->get();

        return view('admin.batches.index', [
            'date' => $date,
            'batches' => $rows,
            'remaining' => $rows->mapWithKeys(fn (Batch $batch) => [$batch->id => $batches->remainingKg($batch)]),
        ]);
    }

    public function show(Batch $batch, BatchService $batches): View
    {
        return view('admin.batches.show', [
            'batch' => $batch->load('product', 'farmer', 'orderItems.order', 'wasteLogs', 'feedbacks'),
            'remainingKg' => $batches->remainingKg($batch),
            'qualityStatuses' => QualityStatus::cases(),
        ]);
    }

    public function update(Request $request, Batch $batch, BatchService $batches): RedirectResponse
    {
        $data = $request->validate([
            'quality_status' => ['required', Rule::enum(QualityStatus::class)],
            'qc_note' => ['nullable', 'string', 'max:255'],
            'processed_at' => ['nullable', 'date_format:Y-m-d\TH:i'],
            'packed_at' => ['nullable', 'date_format:Y-m-d\TH:i'],
            'processing_note' => ['nullable', 'string', 'max:255'],
        ]);

        $batch->update([
            'processed_at' => $data['processed_at'] ?? null,
            'packed_at' => $data['packed_at'] ?? null,
            'processing_note' => $data['processing_note'] ?? null,
        ]);
        $batches->setQuality($batch, QualityStatus::from($data['quality_status']), $data['qc_note'] ?? null);

        return redirect()->route('admin.batches.show', $batch)->with('status', 'Đã cập nhật lô hàng.');
    }

    public function allocate(Request $request, BatchService $batches): RedirectResponse
    {
        $data = $request->validate(['date' => ['required', 'date_format:Y-m-d']]);

        $result = $batches->allocate(CarbonImmutable::parse($data['date']));

        $message = "Đã gán {$result['allocated_packs']} gói.";
        if ($result['short_packs'] > 0) {
            $message .= " Còn thiếu {$result['short_packs']} gói.";
        }

        return back()->with('status', $message);
    }

    /**
     * Quyết định D8: QR chứa URL đầy đủ của trang lô.
     */
    public function labels(Request $request, Batch $batch, QrCodeService $qr): View|RedirectResponse
    {
        if ($batch->quality_status !== QualityStatus::Passed) {
            return redirect()->route('admin.batches.show', $batch)->withErrors(['action' => 'Chỉ in tem cho lô đã đạt kiểm tra chất lượng.']);
        }

        $count = (int) ($request->validate(['count' => ['nullable', 'integer', 'min:1', 'max:200']])['count'] ?? 12);

        return view('admin.batches.labels', [
            'batch' => $batch->load('product', 'farmer'),
            'count' => $count,
            'qrSvg' => $qr->svg(route('batches.public', $batch->code), 160),
        ]);
    }
}
