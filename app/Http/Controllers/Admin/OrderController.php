<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAdminOrderRequest;
use App\Models\Basket;
use App\Models\DeliveryZone;
use App\Models\HarvestPlan;
use App\Models\Order;
use App\Models\Product;
use App\Services\LoyaltyService;
use App\Services\OrderService;
use App\Support\Reward;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'delivery_date' => ['nullable', 'date_format:Y-m-d'],
            'status' => ['nullable', Rule::enum(OrderStatus::class)],
            'q' => ['nullable', 'string', 'max:50'],
        ]);
        $date = $filters['delivery_date'] ?? today()->toDateString();
        $search = trim($filters['q'] ?? '');

        if ($search !== '') {
            // Tìm theo SĐT / mã đơn / tên khách: bỏ lọc ngày, lấy 50 đơn mới nhất.
            $like = '%'.addcslashes($search, '%_\\').'%';
            $digits = preg_replace('/\D/', '', $search);

            $orders = Order::with('customer', 'zone')
                ->where(fn ($query) => $query
                    ->whereLike('code', $like)
                    ->orWhereHas('customer', fn ($customer) => $customer
                        ->whereLike('name', $like)
                        ->when($digits !== '', fn ($query) => $query->orWhereLike('phone', "%{$digits}%"))))
                ->orderByDesc('delivery_date')
                ->orderByDesc('id')
                ->limit(50)
                ->get();
            $demand = collect();
            $statusCounts = collect();
        } else {
            $orders = Order::with('customer', 'zone')
                ->whereDate('delivery_date', $date)
                ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
                ->orderBy('time_slot')
                ->orderBy('id')
                ->get();

            $demand = DB::table('order_items')
                ->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->join('products', 'products.id', '=', 'order_items.product_id')
                ->whereDate('orders.delivery_date', $date)
                ->where('orders.status', '!=', OrderStatus::Cancelled->value)
                ->groupBy('products.id', 'products.name')
                ->orderBy('products.id')
                ->selectRaw('products.name as name, SUM(order_items.weight_kg) as kg')
                ->get();

            // Số đơn theo trạng thái trong ngày, cho các chip lọc.
            $statusCounts = Order::whereDate('delivery_date', $date)
                ->selectRaw('status, COUNT(*) as total')
                ->groupBy('status')
                ->toBase()
                ->pluck('total', 'status')
                ->map(fn ($total) => (int) $total);
        }

        return view('admin.orders.index', [
            'statusCounts' => $statusCounts,
            'orders' => $orders,
            'demand' => $demand,
            'date' => $date,
            'status' => $filters['status'] ?? null,
            'search' => $search !== '' ? $search : null,
            // Khi tìm (nhiều ngày) không ẩn nút; guard trong OrderService chặn từng đơn.
            'planLocked' => $search !== '' || HarvestPlan::isLockedFor($date),
            'statuses' => OrderStatus::cases(),
            'bulkStatuses' => [OrderStatus::Confirmed, OrderStatus::Packing, OrderStatus::Delivering, OrderStatus::Delivered],
        ]);
    }

    public function create(): View
    {
        return view('admin.orders.create', [
            'baskets' => Basket::with('items.product')->where('is_active', true)->orderBy('price')->get(),
            'products' => Product::where('is_active', true)->orderBy('id')->get(),
            'zones' => DeliveryZone::where('is_active', true)->orderBy('id')->get(),
            'timeSlots' => config('farm2home.order.time_slots'),
            'orderTypes' => OrderType::cases(),
            'paymentMethods' => PaymentMethod::cases(),
            'channels' => OrderChannel::cases(),
        ]);
    }

    public function store(StoreAdminOrderRequest $request, OrderService $orders): RedirectResponse
    {
        $data = $request->validated();
        $order = $orders->place($data, OrderChannel::from($data['channel']));

        return redirect()->route('admin.orders.show', $order)->with('status', "Đã tạo đơn {$order->code}.");
    }

    public function show(Order $order, LoyaltyService $loyalty): View
    {
        $order->load('customer', 'zone', 'shipper', 'items.product', 'items.batches.product');

        return view('admin.orders.show', ['order' => $order] + $this->loyaltyPanel($order, $loyalty));
    }

    /**
     * Người điều phối duyệt quà đổi điểm cho đơn (con người xác nhận, D24).
     */
    public function redeem(Request $request, Order $order, LoyaltyService $loyalty): RedirectResponse
    {
        $data = $request->validate([
            'reward' => ['required', Rule::in(Reward::keys())],
            'products' => ['nullable', 'array', 'max:10'],
            'products.*' => ['nullable', 'integer'],
        ], [
            'reward.required' => 'Vui lòng chọn một quà.',
        ]);

        try {
            $loyalty->redeem($order, Reward::find($data['reward']), $data['products'] ?? [], $request->user());
        } catch (DomainException $e) {
            return back()->withErrors(['action' => $e->getMessage()]);
        }

        return redirect()->route('admin.orders.show', $order)->with('status', 'Đã áp dụng quà đổi điểm.');
    }

    /**
     * @return array<string, mixed>
     */
    private function loyaltyPanel(Order $order, LoyaltyService $loyalty): array
    {
        $points = $loyalty->balance($order->customer);
        $rewards = Reward::all();
        $requested = Reward::find($order->requested_reward);
        $redemption = $loyalty->redemptionFor($order)?->load('creator');

        return [
            'points' => $points,
            'rewards' => $rewards,
            'requestedReward' => $requested,
            'redemption' => $redemption,
            'appliedReward' => Reward::find($redemption?->reward),
            'canRedeem' => $loyalty->canRedeemOn($order),
            'anyAffordable' => collect($rewards)->contains(fn (Reward $reward) => $points >= $reward->points),
            'defaultReward' => $requested !== null && $points >= $requested->points ? $requested->key : '',
            'packsByReward' => collect($rewards)->mapWithKeys(fn (Reward $reward) => [$reward->key => $reward->packs])->all(),
            'maxPacks' => (int) collect($rewards)->max('packs'),
            'giftProducts' => Product::where('is_active', true)->orderBy('id')->get(['id', 'name']),
            'harvestPlanLocked' => HarvestPlan::isLockedFor($order->delivery_date),
        ];
    }

    public function transition(Request $request, Order $order, OrderService $orders): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::enum(OrderStatus::class)],
            'cancel_reason' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $orders->transition($order, OrderStatus::from($data['status']), $data['cancel_reason'] ?? null);
        } catch (DomainException $e) {
            return back()->withErrors(['action' => $e->getMessage()]);
        }

        return redirect()->route('admin.orders.show', $order)->with('status', 'Đã cập nhật trạng thái đơn.');
    }

    public function markPaid(Order $order, OrderService $orders): RedirectResponse
    {
        try {
            $orders->markPaid($order);
        } catch (DomainException $e) {
            return back()->withErrors(['action' => $e->getMessage()]);
        }

        return redirect()->route('admin.orders.show', $order)->with('status', 'Đã ghi nhận tiền chuyển khoản.');
    }

    public function bulkTransition(Request $request, OrderService $orders): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::enum(OrderStatus::class)->except([OrderStatus::Pending, OrderStatus::Cancelled])],
            'order_ids' => ['required', 'array', 'max:500'],
            'order_ids.*' => ['integer'],
        ], ['order_ids.required' => 'Chưa chọn đơn nào.']);

        $to = OrderStatus::from($data['status']);
        $result = $orders->transitionMany(Order::with('items')->whereIn('id', $data['order_ids'])->orderBy('id')->get(), $to);

        $message = "Đã chuyển {$result['moved']} đơn sang \"{$to->label()}\".";
        if ($result['skipped'] > 0) {
            $message .= " Bỏ qua {$result['skipped']} đơn không ở bước liền trước.";
        }
        if ($result['blocked'] > 0) {
            $message .= " Bỏ qua {$result['blocked']} đơn vì kế hoạch thu hoạch chưa chốt hoặc còn túi chưa đóng.";
        }

        return back()->with('status', $message);
    }
}
