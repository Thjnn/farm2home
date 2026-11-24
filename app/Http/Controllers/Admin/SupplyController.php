<?php

namespace App\Http\Controllers\Admin;

use App\Enums\FarmerStatus;
use App\Enums\SupplySource;
use App\Http\Controllers\Controller;
use App\Models\Farmer;
use App\Models\Product;
use App\Models\SupplyReport;
use App\Services\SupplyMessageService;
use App\Services\SupplyReportService;
use Carbon\CarbonImmutable;
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
            'unusual' => $supply->unusualReports($date),
            'allProducts' => Product::where('is_active', true)->orderBy('id')->get(['id', 'name']),
        ]);
    }

    /**
     * Đọc tin nhắn Zalo của HTX thành các dòng nháp; chưa lưu gì, người vận hành xem rồi mới xác nhận.
     */
    public function read(Request $request, SupplyMessageService $reader): RedirectResponse
    {
        $data = $request->validate([
            'farmer_id' => ['required', Rule::exists('farmers', 'id')->where('status', FarmerStatus::Active->value)],
            'message' => ['required', 'string', 'max:1000'],
        ]);
        $farmer = Farmer::findOrFail($data['farmer_id']);
        $now = CarbonImmutable::now();
        $result = $reader->read($data['message'], $now);
        $codes = Product::pluck('id', 'code');

        $items = array_map(fn (array $item) => [
            'product_id' => $item['product'] === null ? null : $codes[$item['product']],
            'kg' => $item['kg'],
            'date' => $item['date'],
            'reason' => $reader->reviewReason($item, $now, $farmer),
        ], $result['items']);

        // Giữ trong session (không flash) để còn thấy bản nháp khi lần xác nhận bị lỗi nhập liệu.
        $request->session()->put('parsed', [
            'farmer_id' => $farmer->id,
            'message' => $data['message'],
            'method' => $result['method'],
            'items' => $items,
        ]);

        return redirect()->route('admin.supply.index', ['date' => $request->query('date')]);
    }

    /**
     * Lưu các dòng người vận hành đã xem và đánh dấu từ tin nhắn đã đọc.
     */
    public function confirmMessage(Request $request, SupplyReportService $supply): RedirectResponse
    {
        $data = $request->validate([
            'farmer_id' => ['required', 'exists:farmers,id'],
            'rows' => ['required', 'array'],
            'rows.*.keep' => ['nullable', 'boolean'],
            'rows.*.product_id' => ['required_if_accepted:rows.*.keep', 'nullable', 'exists:products,id'],
            'rows.*.kg' => ['required_if_accepted:rows.*.keep', 'nullable', 'numeric', 'min:0.5', 'max:'.SupplyMessageService::MAX_KG],
            'rows.*.date' => ['required_if_accepted:rows.*.keep', 'nullable', 'date_format:Y-m-d', 'after_or_equal:'.today()->toDateString()],
        ], [], ['rows.*.product_id' => 'loại rau', 'rows.*.kg' => 'số kg', 'rows.*.date' => 'ngày']);

        $byDate = [];
        foreach ($data['rows'] as $row) {
            if ($row['keep'] ?? false) {
                $byDate[$row['date']][$row['product_id']] = $row['kg'];
            }
        }

        if ($byDate === []) {
            return back()->withErrors(['supply' => 'Chưa chọn dòng nào để lưu.']);
        }

        $count = $supply->save(Farmer::findOrFail($data['farmer_id']), $byDate, SupplySource::Zalo);
        $request->session()->forget('parsed');

        return redirect()->route('admin.supply.index', ['date' => array_key_first($byDate)])->with('status', "Đã lưu {$count} dòng sản lượng từ tin nhắn Zalo.");
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
