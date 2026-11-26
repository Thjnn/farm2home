<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\Order;
use App\Services\PackingService;
use App\Services\QrCodeService;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PackingController extends Controller
{
    public function index(Request $request, PackingService $packing): View
    {
        $date = CarbonImmutable::parse($request->validate(['date' => ['nullable', 'date_format:Y-m-d']])['date'] ?? today());
        $orders = $packing->orders($date);

        return view('admin.packing.index', [
            'date' => $date,
            'pickList' => $packing->pickList($date),
            'groups' => $orders->groupBy(fn (Order $order) => $order->zone->name.' · '.$order->time_slot),
            'bags' => $orders->mapWithKeys(fn (Order $order) => [$order->id => $packing->bagsFor($order)]),
        ]);
    }

    /**
     * Phiếu dán túi: cả ngày (date) hoặc một đơn (order). Không in SĐT / địa chỉ khách.
     */
    public function slips(Request $request, PackingService $packing, QrCodeService $qr): View
    {
        $data = $request->validate([
            'date' => ['required_without:order', 'date_format:Y-m-d'],
            'order' => ['required_without:date', 'integer'],
        ]);

        $orders = isset($data['order'])
            ? collect([Order::with('customer', 'zone')->findOrFail($data['order'])])
            : $packing->orders(CarbonImmutable::parse($data['date']));

        $qrByBatch = [];
        $slips = $orders->flatMap(function (Order $order) use ($packing, $qr, &$qrByBatch) {
            $bags = $packing->bagsFor($order)->reject(fn (array $bag) => $bag['short'])->values();

            return $bags->map(function (array $bag, int $index) use ($order, $bags, $qr, &$qrByBatch) {
                $qrByBatch[$bag['batch']->id] ??= $qr->svg($bag['batch']->publicUrl(), 140);

                return $bag + ['order' => $order, 'number' => $index + 1, 'total' => $bags->count(), 'qr' => $qrByBatch[$bag['batch']->id]];
            });
        });

        return view('admin.packing.slips', ['slips' => $slips]);
    }

    public function toggleBag(Request $request, Order $order, Batch $batch, PackingService $packing): RedirectResponse
    {
        $data = $request->validate(['packed' => ['required', 'boolean']]);

        try {
            $packing->markBag($order, $batch, (bool) $data['packed']);
        } catch (DomainException $e) {
            return $this->backToOrder($order)->withErrors(['action' => $e->getMessage()]);
        }

        return $this->backToOrder($order);
    }

    public function packOrder(Order $order, PackingService $packing): RedirectResponse
    {
        try {
            $packing->markOrder($order);
        } catch (DomainException $e) {
            return $this->backToOrder($order)->withErrors(['action' => $e->getMessage()]);
        }

        return $this->backToOrder($order)->with('status', "Đã đóng đủ túi đơn {$order->code}.");
    }

    /**
     * Quay lại đúng ngày giao và vị trí đơn trên màn hình đóng gói.
     */
    private function backToOrder(Order $order): RedirectResponse
    {
        return redirect()->to(route('admin.packing.index', ['date' => $order->delivery_date->toDateString()]).'#order-'.$order->id);
    }
}
