<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\CancelOrderRequest;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CustomerOrderController extends Controller
{
    /**
     * Trang đơn của khách qua link bí mật: xem đơn và tự hủy khi còn được hủy.
     */
    public function show(Order $order, string $key, OrderService $orders): View
    {
        $this->ensureKey($order, $key);

        $order->load('items.product', 'items.basket');

        return view('public.orders.show', [
            'order' => $order,
            'blockReason' => $orders->customerCancelBlockReason($order),
            'deadline' => $orders->customerCancelDeadline($order),
            'operatorPhone' => User::operatorPhone(),
        ]);
    }

    public function cancel(CancelOrderRequest $request, Order $order, string $key, OrderService $orders): RedirectResponse
    {
        $this->ensureKey($order, $key);

        try {
            $orders->cancelByCustomer($order, $request->validated('reason'));
        } catch (DomainException $e) {
            return redirect($order->customerUrl())->withErrors(['action' => $e->getMessage()]);
        }

        return redirect($order->customerUrl())->with('status', 'Đã hủy đơn.');
    }

    private function ensureKey(Order $order, string $key): void
    {
        abort_unless(hash_equals((string) $order->access_key, $key), 404);
    }
}
