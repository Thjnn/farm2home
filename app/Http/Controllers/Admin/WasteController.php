<?php

namespace App\Http\Controllers\Admin;

use App\Enums\QualityStatus;
use App\Enums\WasteReason;
use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\WasteLog;
use App\Services\BatchService;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class WasteController extends Controller
{
    /**
     * Màn hình chốt ngày: lô thu hoạch trong 7 ngày đến hết ngày đang xem, chưa bị loại, còn tồn.
     */
    public function index(Request $request, BatchService $batches): View
    {
        $date = CarbonImmutable::parse($request->validate(['date' => ['nullable', 'date_format:Y-m-d']])['date'] ?? today());

        $leftovers = Batch::with('product', 'farmer')
            ->where('quality_status', '!=', QualityStatus::Rejected)
            ->whereBetween('harvested_at', [$date->subDays(6)->startOfDay(), $date->endOfDay()])
            ->orderBy('best_before')
            ->get()
            ->map(fn (Batch $batch) => ['batch' => $batch, 'remaining' => $batches->remainingKg($batch)])
            ->filter(fn (array $row) => $row['remaining'] > 0)
            ->values();

        return view('admin.waste.index', [
            'date' => $date->toDateString(),
            'leftovers' => $leftovers,
            'reasons' => WasteReason::cases(),
            'logs' => WasteLog::with('product', 'batch', 'creator')->whereDate('log_date', $date->toDateString())->orderByDesc('id')->get(),
        ]);
    }

    public function store(Request $request, BatchService $batches): RedirectResponse
    {
        $data = $request->validate([
            'batch_id' => ['required', 'exists:batches,id'],
            'quantity_kg' => ['required', 'numeric', 'min:0.01', 'max:1000'],
            'reason' => ['required', Rule::enum(WasteReason::class)],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $batches->logWaste(Batch::findOrFail($data['batch_id']), (float) $data['quantity_kg'], WasteReason::from($data['reason']), $request->user(), $data['note'] ?? null);
        } catch (DomainException $e) {
            return back()->withErrors(['action' => $e->getMessage()]);
        }

        return back()->with('status', 'Đã ghi nhận rau dư.');
    }
}
