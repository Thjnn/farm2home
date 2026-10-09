<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Order;
use App\Services\LoyaltyService;
use App\Support\Reward;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderLookupController extends Controller
{
    public function show(): View
    {
        return view('public.orders.lookup', ['orders' => null, 'phone' => null, 'points' => null, 'rewards' => []]);
    }

    /**
     * Quyết định D11: chỉ trả mã, ngày, trạng thái, tổng tiền của 5 đơn gần nhất.
     */
    public function search(Request $request, LoyaltyService $loyalty): View
    {
        $data = $request->validate(['phone' => ['required', 'regex:/^0\d{9}$/']]);

        $orders = Order::query()
            ->whereHas('customer', fn ($query) => $query->where('phone', $data['phone']))
            ->orderByDesc('delivery_date')
            ->orderByDesc('id')
            ->limit(5)
            ->get(['id', 'code', 'delivery_date', 'time_slot', 'status', 'total'])
            ->load('items.batches.product');

        $customer = Customer::where('phone', $data['phone'])->first();

        return view('public.orders.lookup', [
            'orders' => $orders,
            'phone' => $data['phone'],
            // Quyết định D25: chỉ hiện số dư, không hiện lịch sử điểm.
            'points' => $customer === null ? null : $loyalty->balance($customer),
            'rewards' => Reward::all(),
        ]);
    }
}
