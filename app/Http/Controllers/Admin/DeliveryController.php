<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DeliveryMethod;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\User;
use App\Services\ShipperAssignmentService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Trang "Phân giao": ai giao cụm nào trong ngày, đánh dấu nghỉ, chia lại, đổi tay.
 */
class DeliveryController extends Controller
{
    public function index(Request $request): View
    {
        $date = $this->date($request);
        $orders = Order::with(['customer', 'zone', 'shipper'])
            ->whereDate('delivery_date', $date->toDateString())
            ->where('delivery_method', DeliveryMethod::Home)
            ->whereNotIn('status', [OrderStatus::Pending, OrderStatus::Cancelled])
            ->get()
            ->sortBy(fn (Order $order) => [$order->zone->name, $order->time_slot, $order->id])
            ->values();

        return view('admin.delivery.index', [
            'date' => $date,
            'shippers' => User::shippers()->get(),
            'orders' => $orders,
            'zones' => $orders->groupBy('delivery_zone_id'),
        ]);
    }

    public function rebalance(Request $request, ShipperAssignmentService $shippers): RedirectResponse
    {
        $date = $this->date($request);
        $shippers->rebalance($date);

        return $this->back($date, 'Đã chia lại các đơn còn trong kho.');
    }

    public function dayOff(Request $request, User $user, ShipperAssignmentService $shippers): RedirectResponse
    {
        abort_unless($user->isShipper(), 404);
        $date = $this->date($request);
        $off = $request->boolean('off');
        $shippers->setDayOff($user, $date, $off);

        return $this->back($date, $off ? "{$user->name} nghỉ ngày {$date->format('d/m')}, đơn trong kho đã chuyển cho người còn lại." : "{$user->name} đi làm lại.");
    }

    public function zone(Request $request, DeliveryZone $zone, ShipperAssignmentService $shippers): RedirectResponse
    {
        $date = $this->date($request);
        $shippers->assignZone($date, $zone->id, $this->shipperId($request));

        return $this->back($date, "Đã đổi người giao cho {$zone->name}.");
    }

    public function order(Request $request, Order $order): RedirectResponse
    {
        $order->update(['shipper_id' => $this->shipperId($request)]);

        return $this->back(CarbonImmutable::parse($order->delivery_date), "Đã đổi người giao đơn {$order->code}.");
    }

    private function shipperId(Request $request): ?int
    {
        $data = $request->validate([
            'shipper_id' => ['nullable', Rule::exists('users', 'id')->where('role', UserRole::Shipper->value)],
        ]);

        return filled($data['shipper_id'] ?? null) ? (int) $data['shipper_id'] : null;
    }

    private function date(Request $request): CarbonImmutable
    {
        $request->validate(['date' => ['nullable', 'date']]);

        return CarbonImmutable::parse($request->input('date', today()->toDateString()))->startOfDay();
    }

    private function back(CarbonImmutable $date, string $message): RedirectResponse
    {
        return redirect()->route('admin.delivery.index', ['date' => $date->toDateString()])->with('status', $message);
    }
}
