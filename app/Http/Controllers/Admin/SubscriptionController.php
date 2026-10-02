<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SubscriptionFrequency;
use App\Enums\SubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Models\Basket;
use App\Models\Customer;
use App\Models\Subscription;
use App\Services\SubscriptionService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SubscriptionController extends Controller
{
    public function index(): View
    {
        return view('admin.subscriptions.index', [
            'subscriptions' => Subscription::with('customer', 'basket')->orderBy('status')->orderBy('delivery_weekday')->orderBy('id')->paginate(30),
            'defaultDate' => today()->addDay()->toDateString(),
        ]);
    }

    public function create(): View
    {
        return $this->form(new Subscription([
            'frequency' => SubscriptionFrequency::Weekly,
            'status' => SubscriptionStatus::Active,
            'delivery_weekday' => 6,
            'time_slot' => '17:00-19:00',
            'start_date' => today()->toDateString(),
        ]));
    }

    public function store(Request $request): RedirectResponse
    {
        Subscription::create($this->validated($request));

        return redirect()->route('admin.subscriptions.index')->with('status', 'Đã thêm giỏ định kỳ.');
    }

    public function edit(Subscription $subscription): View
    {
        return $this->form($subscription->load('customer'));
    }

    public function update(Request $request, Subscription $subscription): RedirectResponse
    {
        $subscription->update($this->validated($request));

        return redirect()->route('admin.subscriptions.index')->with('status', 'Đã cập nhật giỏ định kỳ.');
    }

    public function generate(Request $request, SubscriptionService $subscriptions): RedirectResponse
    {
        $data = $request->validate(['date' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.today()->toDateString()]]);
        $count = $subscriptions->generateOrders(CarbonImmutable::parse($data['date']));

        return back()->with('status', "Đã tạo {$count} đơn giỏ định kỳ.");
    }

    private function form(Subscription $subscription): View
    {
        return view('admin.subscriptions.form', [
            'subscription' => $subscription,
            'baskets' => Basket::where('is_active', true)->orderBy('price')->get(),
            'frequencies' => SubscriptionFrequency::cases(),
            'statuses' => SubscriptionStatus::cases(),
            'timeSlots' => config('farm2home.order.time_slots'),
        ]);
    }

    /**
     * Khách phải đã tồn tại (đã từng đặt hàng); tra theo số điện thoại.
     *
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'phone' => ['required', 'regex:/^0\d{9}$/', 'exists:customers,phone'],
            'basket_id' => ['required', Rule::exists('baskets', 'id')->where('is_active', true)],
            'frequency' => ['required', Rule::enum(SubscriptionFrequency::class)],
            'delivery_weekday' => ['required', 'integer', 'between:1,7'],
            'time_slot' => ['required', Rule::in(config('farm2home.order.time_slots'))],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'status' => ['required', Rule::enum(SubscriptionStatus::class)],
        ]);

        $data['customer_id'] = Customer::where('phone', $data['phone'])->value('id');
        $data['end_date'] ??= null;
        unset($data['phone']);

        return $data;
    }
}
