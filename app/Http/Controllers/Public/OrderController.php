<?php

namespace App\Http\Controllers\Public;

use App\Enums\OrderChannel;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderRequest;
use App\Models\Basket;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\Product;
use App\Services\OrderService;
use App\Support\OrderWindow;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function create(): View
    {
        $now = CarbonImmutable::now();

        return view('public.orders.create', [
            'baskets' => Basket::with('items.product')->where('is_active', true)->orderBy('price')->get(),
            'products' => Product::where('is_active', true)->orderBy('id')->get(),
            'zones' => DeliveryZone::where('is_active', true)->orderBy('id')->get(),
            'dates' => collect(CarbonPeriod::create(
                OrderWindow::earliest($now, config('farm2home.order.cutoff_hour')),
                OrderWindow::latest($now, config('farm2home.order.max_days_ahead')),
            )),
            'timeSlots' => config('farm2home.order.time_slots'),
            'orderTypes' => OrderType::cases(),
            'paymentMethods' => PaymentMethod::cases(),
        ]);
    }

    public function store(StoreOrderRequest $request, OrderService $orders): RedirectResponse
    {
        $order = $orders->place($request->validated(), OrderChannel::Website);

        $request->session()->put('last_order_code', $order->code);

        return redirect()->route('orders.thanks');
    }

    public function thanks(Request $request): View|RedirectResponse
    {
        $order = Order::where('code', $request->session()->get('last_order_code'))->first();

        if ($order === null) {
            return redirect()->route('home');
        }

        return view('public.orders.thanks', ['order' => $order]);
    }
}
