<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DeliveryZone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DeliveryZoneController extends Controller
{
    public function index(): View
    {
        return view('admin.zones.index', ['zones' => DeliveryZone::orderBy('id')->get()]);
    }

    public function create(): View
    {
        return view('admin.zones.form', ['zone' => new DeliveryZone(['is_active' => true, 'shipping_fee' => 0])]);
    }

    public function store(Request $request): RedirectResponse
    {
        DeliveryZone::create($this->validated($request));

        return redirect()->route('admin.zones.index')->with('status', 'Đã thêm cụm giao.');
    }

    public function edit(DeliveryZone $zone): View
    {
        return view('admin.zones.form', ['zone' => $zone]);
    }

    public function update(Request $request, DeliveryZone $zone): RedirectResponse
    {
        $zone->update($this->validated($request));

        return redirect()->route('admin.zones.index')->with('status', 'Đã cập nhật cụm giao.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
            'shipping_fee' => ['required', 'integer', 'min:0'],
            'free_ship_from' => ['nullable', 'integer', 'min:0'],
            'pickup_point' => ['nullable', 'string', 'max:255'],
        ]);

        return [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'shipping_fee' => $data['shipping_fee'],
            'free_ship_from' => $data['free_ship_from'] ?? null,
            'pickup_point' => $data['pickup_point'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ];
    }
}
