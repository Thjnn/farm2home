<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ForecastMethod;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\UnmetDemand;
use App\Services\ForecastService;
use App\Services\KpiService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Đối chiếu buổi giao: dự báo của mô hình so với cách tính thông thường, và kết quả thật từng buổi (dư, thiếu, tiền).
 */
class SessionReportController extends Controller
{
    public function index(Request $request, ForecastService $forecasts, KpiService $kpi): View
    {
        $range = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);
        $to = CarbonImmutable::parse($range['to'] ?? today());
        $from = CarbonImmutable::parse($range['from'] ?? $to->subDays(27));
        $model = ForecastMethod::from(config('farm2home.forecast.default_method'));

        return view('admin.sessions.index', [
            'from' => $from,
            'to' => $to,
            'model' => $model,
            'leadDays' => (int) config('farm2home.forecast.evaluation_lead_days'),
            'comparison' => $forecasts->sessionComparison($from, $to, $model),
            'accuracy' => $forecasts->accuracy($from, $to),
            'sessions' => $kpi->sessions($from, $to),
            'products' => Product::where('is_active', true)->orderBy('id')->get(),
        ]);
    }

    /**
     * Ghi phần khách muốn mua mà hết hàng cho một buổi giao. 0 kg = xóa dòng đã ghi.
     */
    public function storeUnmet(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'delivery_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.today()->addDay()->toDateString()],
            'product_id' => ['required', 'exists:products,id'],
            'quantity_kg' => ['required', 'numeric', 'min:0', 'max:1000'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);
        $key = ['delivery_date' => $data['delivery_date'], 'product_id' => $data['product_id']];

        if ((float) $data['quantity_kg'] === 0.0) {
            UnmetDemand::where($key)->delete();
        } else {
            UnmetDemand::updateOrCreate($key, [
                'quantity_kg' => $data['quantity_kg'],
                'note' => $data['note'] ?? null,
                'created_by' => $request->user()->id,
            ]);
        }

        return back()->with('status', 'Đã ghi phần khách muốn mua mà hết hàng.');
    }
}
