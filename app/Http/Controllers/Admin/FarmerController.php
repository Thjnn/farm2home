<?php

namespace App\Http\Controllers\Admin;

use App\Enums\FarmerStatus;
use App\Http\Controllers\Controller;
use App\Models\Farmer;
use App\Models\Product;
use App\Support\PhotoStore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FarmerController extends Controller
{
    public function index(): View
    {
        return view('admin.farmers.index', ['farmers' => Farmer::with('products')->orderBy('code')->get()]);
    }

    public function create(): View
    {
        return $this->form(new Farmer(['status' => FarmerStatus::Active]));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->save($request, new Farmer);

        return redirect()->route('admin.farmers.index')->with('status', 'Đã thêm nông hộ.');
    }

    public function edit(Farmer $farmer): View
    {
        return $this->form($farmer->load('products'));
    }

    public function update(Request $request, Farmer $farmer): RedirectResponse
    {
        $this->save($request, $farmer);

        return redirect()->route('admin.farmers.index')->with('status', 'Đã cập nhật nông hộ.');
    }

    public function regenerateToken(Farmer $farmer): RedirectResponse
    {
        $farmer->update(['access_token' => $this->newToken()]);

        return back()->with('status', 'Đã cấp link mới. Link cũ không còn dùng được; hãy gửi link mới cho nông hộ.');
    }

    private function form(Farmer $farmer): View
    {
        return view('admin.farmers.form', [
            'farmer' => $farmer,
            'products' => Product::orderBy('id')->get(),
            'weeklyKg' => $farmer->exists ? $farmer->products->mapWithKeys(fn ($product) => [$product->id => $product->pivot->typical_weekly_kg]) : collect(),
            'statuses' => FarmerStatus::cases(),
        ]);
    }

    /**
     * Save the farmer, an optional certificate file, and the products the farmer grows.
     */
    private function save(Request $request, Farmer $farmer): void
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:20', Rule::unique('farmers', 'code')->ignore($farmer->id)],
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['required', 'regex:/^0\d{9}$/'],
            'zalo_phone' => ['nullable', 'regex:/^0\d{9}$/'],
            'address' => ['nullable', 'string', 'max:255'],
            'growing_area' => ['nullable', 'string', 'max:255'],
            'area_m2' => ['nullable', 'integer', 'min:0'],
            'farming_method' => ['nullable', 'string', 'max:150'],
            'bank_name' => ['nullable', 'string', 'max:100'],
            'bank_account_number' => ['nullable', 'regex:/^\d{6,20}$/'],
            'bank_account_holder' => ['nullable', 'string', 'max:150'],
            'farm_photos' => ['nullable', 'array', 'max:4'],
            'farm_photos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'remove_farm_photos' => ['nullable', 'boolean'],
            'certificate_name' => ['nullable', 'string', 'max:150'],
            'certificate' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:4096'],
            'certificate_expires_at' => ['nullable', 'date'],
            'status' => ['required', Rule::enum(FarmerStatus::class)],
            'note' => ['nullable', 'string', 'max:1000'],
            'products' => ['nullable', 'array'],
            'products.*' => ['nullable', 'numeric', 'min:0', 'max:10000'],
        ]);

        DB::transaction(function () use ($request, $farmer, $data) {
            $farmer->fill(Arr::except($data, ['certificate', 'products', 'farm_photos', 'remove_farm_photos']));
            $farmer->zalo_phone = $farmer->zalo_phone ?: $data['phone'];
            $farmer->access_token = $farmer->access_token ?: $this->newToken();

            if ($request->hasFile('certificate')) {
                $farmer->certificate_file = $request->file('certificate')->store('certificates', 'public');
            }

            // Chọn ảnh mới thì thay cả bộ ảnh vườn cũ.
            if ($request->hasFile('farm_photos') || $request->boolean('remove_farm_photos')) {
                array_map(PhotoStore::delete(...), $farmer->farm_photos ?? []);
                $farmer->farm_photos = array_map(
                    fn ($photo) => PhotoStore::store($photo, 'farms'),
                    $request->file('farm_photos', []),
                ) ?: null;
            }

            $farmer->save();

            $farmer->products()->sync(
                collect($data['products'] ?? [])
                    ->filter(fn ($kg) => $kg !== null && $kg !== '')
                    ->map(fn ($kg) => ['typical_weekly_kg' => (float) $kg])
                    ->all()
            );
        });
    }

    private function newToken(): string
    {
        return bin2hex(random_bytes(20));
    }
}
