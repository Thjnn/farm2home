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
use App\Models\Product;
use App\Services\BatchService;
use App\Services\ForecastService;
use App\Services\HarvestPlanService;
use App\Services\SupplyReportService;
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

    public function show(HarvestPlan $plan, HarvestPlanService $plans, SupplyReportService $supply): View
    {
        $plan->load('items.product', 'items.farmer', 'items.batch', 'creator', 'confirmer');
        $plan->setRelation('items', $plan->items->sortBy([['product_id', 'asc'], ['id', 'asc']])->values());

        $isConfirmed = $plan->status !== HarvestPlanStatus::Draft;
        $farmers = $plan->items->pluck('farmer')->filter()->unique('id')->values();

        return view('admin.harvest-plans.show', [
            'plan' => $plan,
            'pendingOrders' => Order::whereDate('delivery_date', $plan->delivery_date)->where('status', OrderStatus::Pending)->count(),
            'allFarmers' => Farmer::where('status', FarmerStatus::Active)->with('products:id')->orderBy('code')->get(),
            'messages' => $isConfirmed
                ? $farmers->mapWithKeys(fn (Farmer $farmer) => [$farmer->id => $plans->zaloMessage($plan, $farmer)])
                : collect(),
            'farmers' => $farmers,
            'unusual' => $supply->unusualReports($plan->delivery_date->toDateString()),
        ]);
    }

    public function updateItems(Request $request, HarvestPlan $plan, HarvestPlanService $plans): RedirectResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array'],
            'items.*.final_kg' => ['required', 'numeric', 'min:0', 'max:1000'],
            'items.*.adjust_reason' => ['nullable', 'string', 'max:255'],
            'items.*.farmer_id' => ['nullable', 'exists:farmers,id'],
            'add_product_id' => ['nullable', 'exists:products,id'],
            'remove_item_id' => ['nullable', 'integer'],
        ]);

        // Nút thêm/xoá dòng cũng gửi kèm cả bảng để không mất số đang nhập dở.
        return $this->attempt(function () use ($data, $plan, $plans) {
            $plans->updateItems($plan, $data['items']);

            if (filled($data['add_product_id'] ?? null)) {
                $plans->addItem($plan, Product::findOrFail($data['add_product_id']));
            }

            if (filled($data['remove_item_id'] ?? null)) {
                $plans->removeItem($plan->items()->findOrFail($data['remove_item_id']));
            }

            return redirect()->route('admin.harvest-plans.show', $plan)->with('status', 'Đã lưu tất cả số chốt.');
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

    public function receive(Request $request, HarvestPlanItem $item, BatchService $batches): RedirectResponse
    {
        $data = $request->validate(['received_kg' => ['required', 'numeric', 'min:0', 'max:1000']]);

        return $this->attempt(function () use ($data, $item, $batches) {
            $batch = $batches->receive($item, (float) $data['received_kg']);

            return redirect()->route('admin.harvest-plans.show', $item->harvest_plan_id)->with('status', "Đã nhận hàng, tạo lô {$batch->code}.");
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
            return back()->withInput()->withErrors(['action' => $e->getMessage()]);
        }
    }
}
