<?php

namespace App\Http\Controllers\Admin;

use App\Enums\FarmerStatus;
use App\Enums\SupplySource;
use App\Http\Controllers\Controller;
use App\Models\Farmer;
use App\Models\Product;
use App\Models\SupplyReport;
use App\Services\SupplyReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SupplyController extends Controller
{
    public function index(Request $request, SupplyReportService $supply): View
    {
        $date = $request->validate(['date' => ['nullable', 'date_format:Y-m-d']])['date'] ?? today()->addDay()->toDateString();

        $reports = SupplyReport::whereDate('available_date', $date)->get();

        return view('admin.supply.index', [
            'date' => $date,
            'locked' => $supply->isLocked($date),
            'farmers' => Farmer::with(['products' => fn ($query) => $query->orderBy('products.id')])
                ->where('status', '!=', FarmerStatus::Inactive)
                ->orderBy('code')
                ->get(),
            'reports' => $reports->groupBy('farmer_id')->map(fn ($group) => $group->pluck('quantity_kg', 'product_id')),
            'totals' => $reports->groupBy('product_id')->map(fn ($group) => $group->sum(fn ($report) => (float) $report->quantity_kg)),
            'productNames' => Product::pluck('name', 'id'),
        ]);
    }

    public function store(Request $request, SupplyReportService $supply): RedirectResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.today()->toDateString()],
            'source' => ['required', Rule::in([SupplySource::Zalo->value, SupplySource::Operator->value])],
            'supply' => ['nullable', 'array'],
            'supply.*' => ['array'],
            'supply.*.*' => ['nullable', 'numeric', 'min:0', 'max:500'],
        ]);

        $farmers = Farmer::findMany(array_keys($data['supply'] ?? []))->keyBy('id');

        foreach ($data['supply'] ?? [] as $farmerId => $byProduct) {
            if ($farmers->has($farmerId)) {
                $supply->save($farmers[$farmerId], [$data['date'] => $byProduct], SupplySource::from($data['source']));
            }
        }

        return redirect()->route('admin.supply.index', ['date' => $data['date']])->with('status', 'Đã lưu sản lượng.');
    }
}
