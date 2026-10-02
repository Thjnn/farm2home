<?php

namespace App\Http\Controllers\Farmer;

use App\Enums\FarmerStatus;
use App\Enums\HarvestPlanStatus;
use App\Enums\SupplySource;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSupplyRequest;
use App\Models\Farmer;
use App\Models\HarvestPlan;
use App\Services\HarvestPlanService;
use App\Services\SupplyReportService;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class FarmerPortalController extends Controller
{
    public function show(Farmer $farmer, SupplyReportService $supply): View
    {
        $this->ensureActive($farmer);
        $dates = $this->dates();

        $reports = $farmer->supplyReports()
            ->whereDate('available_date', '>=', $dates[0])
            ->get()
            ->groupBy(fn ($report) => $report->available_date->toDateString())
            ->map(fn ($group) => $group->pluck('quantity_kg', 'product_id'));

        $ownRows = fn ($query) => $query->where('farmer_id', $farmer->id)->where('final_kg', '>', 0);

        return view('farmer.show', [
            'farmer' => $farmer,
            'products' => $farmer->products()->where('is_active', true)->orderBy('products.id')->get(),
            'dates' => $dates,
            'reports' => $reports,
            'locked' => collect($dates)->mapWithKeys(fn ($date) => [$date => $supply->isLocked($date)]),
            'harvests' => HarvestPlan::whereIn('status', [HarvestPlanStatus::Confirmed, HarvestPlanStatus::Sent])
                ->whereDate('harvest_date', '>=', today()->toDateString())
                ->whereHas('items', $ownRows)
                ->with(['items' => fn ($query) => $ownRows($query)->with('product')])
                ->orderBy('harvest_date')
                ->get(),
        ]);
    }

    public function storeSupply(StoreSupplyRequest $request, Farmer $farmer, SupplyReportService $supply): RedirectResponse
    {
        $this->ensureActive($farmer);
        $data = $request->validated('supply');

        if (array_diff(array_keys($data), $this->dates()) !== []) {
            throw ValidationException::withMessages(['supply' => 'Chỉ báo được sản lượng cho 7 ngày tới.']);
        }

        $supply->save($farmer, $data, SupplySource::Form);

        return redirect()->route('farmer.show', $farmer->access_token)->with('status', 'Đã lưu sản lượng. Cảm ơn cô chú!');
    }

    public function confirmHarvest(Farmer $farmer, HarvestPlan $plan, HarvestPlanService $plans): RedirectResponse
    {
        $this->ensureActive($farmer);

        try {
            $plans->farmerConfirm($plan, $farmer);
        } catch (DomainException $e) {
            return back()->withErrors(['action' => $e->getMessage()]);
        }

        return redirect()->route('farmer.show', $farmer->access_token)->with('status', 'Đã ghi nhận. Cảm ơn cô chú!');
    }

    private function ensureActive(Farmer $farmer): void
    {
        abort_if($farmer->status === FarmerStatus::Inactive, 404);
    }

    /**
     * @return list<string> ngày mai .. 7 ngày tới, dạng Y-m-d
     */
    private function dates(): array
    {
        $tomorrow = CarbonImmutable::tomorrow();

        return collect(CarbonPeriod::create($tomorrow, $tomorrow->addDays(6)))
            ->map(fn ($date) => $date->toDateString())
            ->all();
    }
}
