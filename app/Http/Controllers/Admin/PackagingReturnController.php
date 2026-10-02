<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Order;
use App\Models\PackagingReturn;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PackagingReturnController extends Controller
{
    public function index(): View
    {
        return view('admin.packaging-returns.index', [
            'returns' => PackagingReturn::with('customer', 'order')->orderByDesc('return_date')->orderByDesc('id')->paginate(30),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'return_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.today()->toDateString()],
            'phone' => ['nullable', 'regex:/^0\d{9}$/'],
            'order_code' => ['nullable', 'string', 'exists:orders,code'],
            'quantity_returned' => ['required', 'integer', 'min:1', 'max:500'],
            'quantity_reused' => ['required', 'integer', 'min:0', 'lte:quantity_returned'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $order = filled($data['order_code'] ?? null) ? Order::where('code', $data['order_code'])->first() : null;
        $customer = filled($data['phone'] ?? null) ? Customer::where('phone', $data['phone'])->first() : null;

        PackagingReturn::create([
            'return_date' => $data['return_date'],
            'customer_id' => $customer?->id ?? $order?->customer_id,
            'order_id' => $order?->id,
            'quantity_returned' => $data['quantity_returned'],
            'quantity_reused' => $data['quantity_reused'],
            'note' => $data['note'] ?? null,
        ]);

        return back()->with('status', 'Đã ghi nhận bao bì thu hồi.');
    }
}
