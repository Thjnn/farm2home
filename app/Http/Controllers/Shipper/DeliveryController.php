<?php

namespace App\Http\Controllers\Shipper;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderService;
use App\Services\PackingService;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Màn hình người giao (điện thoại): đơn của mình trong ngày, nhận hàng, báo đã giao / không giao được.
 */
class DeliveryController extends Controller
{
    public const ISSUE_REASONS = ['Không liên lạc được', 'Không có nhà', 'Khách từ chối nhận', 'Khác'];

    public function index(Request $request, PackingService $packing): View
    {
        $date = $this->date($request);
        $orders = $this->myOrders($request, $date)
            ->with(['customer', 'zone', 'items.product', 'items.batches'])
            ->get()
            ->sortBy(fn (Order $order) => [$order->time_slot, $order->zone->name, $order->id])
            ->values();

        $bags = $orders->mapWithKeys(fn (Order $order) => [$order->id => $packing->bagsFor($order)->reject(fn (array $bag) => $bag['short'])->count()]);
        $ready = $orders->filter(fn (Order $order) => $order->status === OrderStatus::Packing && $packing->isFullyPacked($order));

        return view('shipper.index', [
            'date' => $date,
            'groups' => $orders->groupBy(fn (Order $order) => $order->time_slot.' · '.$order->zone->name),
            'orders' => $orders,
            'bags' => $bags,
            'readyCount' => $ready->count(),
            'reasons' => self::ISSUE_REASONS,
        ]);
    }

    /**
     * "Nhận hàng đi giao": đơn đã đủ túi chuyển sang Đang giao; đơn chưa đủ túi để lại.
     */
    public function pickup(Request $request, PackingService $packing, OrderService $orders): RedirectResponse
    {
        $date = $this->date($request);
        $packingOrders = $this->myOrders($request, $date)->where('status', OrderStatus::Packing)->with('items.batches')->get();
        [$ready, $notReady] = $packingOrders->partition(fn (Order $order) => $packing->isFullyPacked($order));

        $moved = $orders->transitionMany($ready, OrderStatus::Delivering)['moved'];
        $message = "Đã nhận {$moved} đơn đi giao.".($notReady->isNotEmpty() ? " {$notReady->count()} đơn chưa đủ túi, chưa nhận." : '');

        return redirect()->route('shipper.index', ['date' => $date->toDateString()])->with('status', $message);
    }

    public function delivered(Request $request, Order $order, OrderService $orders): RedirectResponse
    {
        $this->ensureMine($request, $order);

        try {
            $orders->transition($order, OrderStatus::Delivered);
        } catch (DomainException $e) {
            return back()->withErrors(['action' => $e->getMessage()]);
        }

        return $this->backToDay($order)->with('status', "Đã giao {$order->code}.");
    }

    /**
     * Báo không giao được: đơn vẫn Đang giao, người vận hành nhận thông báo và quyết định.
     */
    public function issue(Request $request, Order $order): RedirectResponse
    {
        $this->ensureMine($request, $order);

        $data = $request->validate([
            'reason' => ['required', Rule::in(self::ISSUE_REASONS)],
            'note' => ['nullable', 'string', 'max:120'],
        ]);

        if ($order->status !== OrderStatus::Delivering) {
            return back()->withErrors(['action' => 'Chỉ báo được với đơn đang giao.']);
        }

        $order->update([
            'delivery_issue' => $data['reason'].(filled($data['note'] ?? null) ? ': '.$data['note'] : ''),
            'delivery_issue_at' => now(),
        ]);

        return $this->backToDay($order)->with('status', "Đã báo {$order->code} không giao được.");
    }

    private function date(Request $request): CarbonImmutable
    {
        $request->validate(['date' => ['nullable', 'date']]);

        return CarbonImmutable::parse($request->input('date', today()->toDateString()))->startOfDay();
    }

    /**
     * @return Builder<Order>
     */
    private function myOrders(Request $request, CarbonImmutable $date): Builder
    {
        return Order::where('shipper_id', $request->user()->id)
            ->whereDate('delivery_date', $date->toDateString())
            ->whereNotIn('status', [OrderStatus::Pending, OrderStatus::Cancelled]);
    }

    private function ensureMine(Request $request, Order $order): void
    {
        abort_unless($order->shipper_id === $request->user()->id, 404);
    }

    private function backToDay(Order $order): RedirectResponse
    {
        return redirect()->to(route('shipper.index', ['date' => $order->delivery_date->toDateString()]).'#order-'.$order->id);
    }
}
