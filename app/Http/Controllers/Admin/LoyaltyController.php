<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PointReason;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\LoyaltyService;
use Illuminate\View\View;

class LoyaltyController extends Controller
{
    /**
     * Khách đã có giao dịch điểm, số dư cao nhất trước.
     */
    public function index(): View
    {
        $earnReasons = [PointReason::OrderDelivered, PointReason::PackagingReturned];

        return view('admin.loyalty.index', [
            'customers' => Customer::query()
                ->whereHas('pointTransactions')
                ->withSum('pointTransactions as balance', 'points')
                ->withSum(['pointTransactions as earned' => fn ($query) => $query->whereIn('reason', $earnReasons)], 'points')
                ->orderByDesc('balance')
                ->orderBy('name')
                ->paginate(30),
        ]);
    }

    public function show(Customer $customer, LoyaltyService $loyalty): View
    {
        return view('admin.loyalty.show', [
            'customer' => $customer,
            'points' => $loyalty->balance($customer),
            'transactions' => $customer->pointTransactions()
                ->with('order', 'creator')
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->paginate(50),
        ]);
    }
}
