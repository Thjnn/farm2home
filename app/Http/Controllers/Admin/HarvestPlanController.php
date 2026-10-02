<?php

namespace App\Http\Controllers\Admin;

use App\Enums\FarmerStatus;
use App\Enums\HarvestPlanStatus;
use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Farmer;
use App\Models\HarvestPlan;
use App\Models\HarvestPlanItem;
use App\Models\Order;
use App\Services\ForecastService;
use App\Services\HarvestPlanService;
use Carbon\CarbonImmutable;
use Closure;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HarvestPlanController extends Controller
{
    public function index(): View
    {
        return view('admin.harvest-plans.index', [
            'plans' => HarvestPlan::withCount('items')->orderByDesc('delivery_date')->paginate(20),
            'defaultDate' => today()->addDay()->toDateString(),
        ]);
    }

    public function generate(Request $request, ForecastService $forecasts, HarvestPlanService $plans): RedirectResponse
    {
        $data = $request->validate(['delivery_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.today()->toDateString()]]);

        return $this->attempt(function () use ($data, $forecasts, $plans, $request) {
            $forecasts->run(today());
            $plan = $plans->generateDraft(CarbonImmutable::parse($data['delivery_date']), $request->user());

            return redirect()->route('admin.harvest-plans.show', $plan)->with('status', 'Đã tạo gợi ý thu hoạch. Hãy xem lại trước khi chốt.');
        });
    }

    public function show(HarvestPlan $plan, HarvestPlanService $plans): View
    {
        $plan->load('items.product', 'items.farmer', 'items.batch', 'creator', 'confirmer');

        $isConfirmed = $plan->status !== HarvestPlanStatus::Draft;
        $farmers = $plan->items->pluck('farmer')->filter()->unique('id')->values();

        return view('admin.harvest-plans.show', [
            'plan' => $plan,
            'pendingOrders' => Order::whereDate('delivery_date', $plan->delivery_date)->where('status', OrderStatus::Pending)->count(),
            'allFarmers' => Farmer::where('status', FarmerStatus::Active)->orderBy('code')->get(),
            'messages' => $isConfirmed
                ? $farmers->mapWithKeys(fn (Farmer $farmer) => [$farmer->id => $plans->zaloMessage($plan, $farmer)])
                : collect(),
            'farmers' => $farmers,
        ]);
    }

    public function updateItem(Request $request, HarvestPlanItem $item, HarvestPlanService $plans): RedirectResponse
    {
        $data = $request->validate([
            'final_kg' => ['required', 'numeric', 'min:0', 'max:1000'],
            'adjust_reason' => ['nullable', 'string', 'max:255'],
            'farmer_id' => ['nullable', 'exists:farmers,id'],
        ]);

        return $this->attempt(function () use ($data, $item, $plans) {
            $plans->updateItem($item, (float) $data['final_kg'], $data['adjust_reason'] ?? null, isset($data['farmer_id']) ? (int) $data['farmer_id'] : null);

            return redirect()->route('admin.harvest-plans.show', $item->harvest_plan_id)->with('status', 'Đã lưu số chốt.');
        });
    }

    public function confirm(Request $request, HarvestPlan $plan, HarvestPlanService $plans): RedirectResponse
    {
        return $this->attempt(function () use ($request, $plan, $plans) {
            $plans->confirm($plan, $request->user());

            return redirect()->route('admin.harvest-plans.show', $plan)->with('status', 'Đã chốt kế hoạch. Gửi tin Zalo cho từng nông hộ bên dưới.');
        });
    }

    public function sent(HarvestPlan $plan, HarvestPlanService $plans): RedirectResponse
    {
        return $this->attempt(function () use ($plan, $plans) {
            $plans->markSent($plan);

            return redirect()->route('admin.harvest-plans.show', $plan)->with('status', 'Đã đánh dấu là đã gửi nông hộ.');
        });
    }

    public function complete(HarvestPlan $plan, HarvestPlanService $plans): RedirectResponse
    {
        return $this->attempt(function () use ($plan, $plans) {
            $plans->complete($plan);

            return redirect()->route('admin.harvest-plans.show', $plan)->with('status', 'Kế hoạch đã hoàn tất.');
        });
    }

    /**
     * Run a service action and turn a business-rule violation into a flash error.
     *
     * @param  Closure(): RedirectResponse  $action
     */
    private function attempt(Closure $action): RedirectResponse
    {
        try {
            return $action();
        } catch (DomainException $e) {
            return back()->withErrors(['action' => $e->getMessage()]);
        }
    }
}
