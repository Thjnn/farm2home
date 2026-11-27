<?php

namespace App\Http\Controllers\Public;

use App\Enums\OrderChannel;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Enums\ProductCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderRequest;
use App\Models\Basket;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\Product;
use App\Services\CatalogService;
use App\Services\OrderService;
use App\Support\Format;
use App\Support\OrderWindow;
use App\Support\Reward;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class OrderController extends Controller
{
    /** Số giỏ / món mỗi trang ở bước 1. */
    public const LIST_PER_PAGE = 6;

    /**
     * Bước 1 chọn giỏ / chọn lẻ (danh sách phân trang, trang đầu nhúng sẵn), bước 2 giao hàng & thanh toán.
     */
    public function create(Request $request, CatalogService $catalog): View
    {
        $now = CarbonImmutable::now();
        // Link "Đặt giỏ này" ở trang chủ: /dat-hang?goi=<id> chọn sẵn giỏ đó.
        $preselected = Basket::with('items.product')->where('is_active', true)->find($request->integer('goi'));
        // Thẻ rau trong chatbot: /dat-hang?rau=<id> thêm sẵn 1 gói rau đó vào giỏ chọn lẻ.
        $product = Product::where('is_active', true)->find($request->integer('rau'));

        return view('public.orders.create', [
            'baskets' => $catalog->baskets(1, self::LIST_PER_PAGE),
            'products' => $catalog->products(1, self::LIST_PER_PAGE),
            'categories' => ProductCategory::cases(),
            'preselectedBasket' => $preselected === null ? null : [
                'id' => $preselected->id,
                'name' => $preselected->name,
                'price' => $preselected->price,
                'summary' => $preselected->items->map(fn ($item) => $item->quantity.' × '.$item->product->name)->join(', '),
            ],
            'preselectedProduct' => $product === null ? null : [
                'id' => $product->id,
                'name' => $product->name,
                'price' => $product->price,
                'unit' => Format::kg($product->pack_weight_kg),
                'imageUrl' => $product->imageUrl(),
            ],
            'zones' => DeliveryZone::where('is_active', true)->orderBy('id')->get(),
            'dates' => collect(CarbonPeriod::create(
                OrderWindow::earliest($now, config('farm2home.order.cutoff_hour')),
                OrderWindow::latest($now, config('farm2home.order.max_days_ahead')),
            )),
            'timeSlots' => config('farm2home.order.time_slots'),
            'orderTypes' => OrderType::cases(),
            'paymentMethods' => PaymentMethod::cases(),
            'rewards' => Reward::all(),
        ]);
    }

    public function store(StoreOrderRequest $request, OrderService $orders): RedirectResponse
    {
        $data = $request->safe()->except('submission_token');
        $token = $request->validated('submission_token');

        $place = fn () => $orders->place($data, OrderChannel::Website)->code;

        // Bấm "Gửi đơn" nhiều lần cùng một form chỉ tạo một đơn: lần sau chờ lần đầu xong rồi trả lại đúng đơn đó.
        $code = $token === null
            ? $place()
            : Cache::lock('order-submit:'.$token, 15)->block(15, fn () => Cache::get('order-submit-code:'.$token)
                ?? tap($place(), fn (string $code) => Cache::put('order-submit-code:'.$token, $code, now()->addHour())));

        $request->session()->put('last_order_code', $code);

        return redirect()->route('orders.thanks');
    }

    public function thanks(Request $request): View|RedirectResponse
    {
        $order = Order::where('code', $request->session()->get('last_order_code'))->first();

        if ($order === null) {
            return redirect()->route('home');
        }

        return view('public.orders.thanks', ['order' => $order, 'requestedReward' => Reward::find($order->requested_reward)]);
    }
}
