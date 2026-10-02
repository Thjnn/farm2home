<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderLookupController extends Controller
{
    public function show(): View
    {
        return view('public.orders.lookup', ['orders' => null, 'phone' => null]);
    }

    /**
     * Quyết định D11: chỉ trả mã, ngày, trạng thái, tổng tiền của 5 đơn gần nhất.
     */
    public function search(Request $request): View
    {
        $data = $request->validate(['phone' => ['required', 'regex:/^0\d{9}$/']]);

        $orders = Order::query()
            ->whereHas('customer', fn ($query) => $query->where('phone', $data['phone']))
            ->orderByDesc('delivery_date')
            ->orderByDesc('id')
            ->limit(5)
            ->get(['id', 'code', 'delivery_date', 'time_slot', 'status', 'total']);

        return view('public.orders.lookup', ['orders' => $orders, 'phone' => $data['phone']]);
    }
}
