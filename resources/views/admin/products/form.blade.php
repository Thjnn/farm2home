<x-layouts.admin :title="$product->exists ? 'Sửa sản phẩm' : 'Thêm sản phẩm'">
    <form method="POST" action="{{ $product->exists ? route('admin.products.update', $product) : route('admin.products.store') }}" class="grid max-w-2xl gap-4 sm:grid-cols-2">
        @csrf
        @if ($product->exists)
            @method('PUT')
        @endif
        <x-field name="code" label="Mã (dùng trong mã lô, vd RM)" :value="$product->code" maxlength="10" required />
        <x-field name="name" label="Tên" :value="$product->name" required />
        <x-field name="pack_weight_kg" label="Khối lượng 1 gói (kg)" type="number" step="0.05" min="0.05" :value="(float) $product->pack_weight_kg" required />
        <x-field name="price" label="Giá bán / gói (đ)" type="number" min="0" :value="$product->price" required />
        <x-field name="cost_price_per_kg" label="Giá thu mua / kg (đ)" type="number" min="0" :value="$product->cost_price_per_kg" required />
        <x-field name="shelf_life_days" label="Hạn dùng (ngày)" type="number" min="1" max="30" :value="$product->shelf_life_days" required />
        <div class="sm:col-span-2">
            <label for="storage_guide" class="mb-1 block text-sm font-medium text-stone-700">Hướng dẫn bảo quản (hiện trên trang QR)</label>
            <textarea id="storage_guide" name="storage_guide" rows="2" class="block w-full rounded-lg border border-stone-300 bg-white px-3 py-2">{{ old('storage_guide', $product->storage_guide) }}</textarea>
            @error('storage_guide')
                <p class="mt-1 text-sm text-rose-700">{{ $message }}</p>
            @enderror
        </div>
        <label class="flex min-h-11 items-center gap-2 text-sm sm:col-span-2">
            <input type="checkbox" name="is_active" value="1" class="size-5 rounded border-stone-300" @checked(old('is_active', $product->is_active))> Đang bán
        </label>
        <div class="flex gap-3 sm:col-span-2">
            <button class="min-h-11 rounded-lg bg-emerald-700 px-6 font-semibold text-white hover:bg-emerald-800">Lưu</button>
            <a href="{{ route('admin.products.index') }}" class="inline-flex min-h-11 items-center rounded-lg border border-stone-300 bg-white px-4 hover:bg-stone-100">Hủy</a>
        </div>
    </form>
</x-layouts.admin>
