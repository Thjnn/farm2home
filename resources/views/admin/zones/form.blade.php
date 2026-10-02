<x-layouts.admin :title="$zone->exists ? 'Sửa cụm giao' : 'Thêm cụm giao'">
    <form method="POST" action="{{ $zone->exists ? route('admin.zones.update', $zone) : route('admin.zones.store') }}" class="grid max-w-2xl gap-4 sm:grid-cols-2">
        @csrf
        @if ($zone->exists)
            @method('PUT')
        @endif
        <x-field name="name" label="Tên cụm giao" :value="$zone->name" required />
        <x-field name="description" label="Phường/xã thuộc cụm" :value="$zone->description" />
        <x-field name="shipping_fee" label="Phí giao (đ)" type="number" min="0" :value="$zone->shipping_fee" required />
        <x-field name="free_ship_from" label="Miễn phí giao khi đơn từ (đ, để trống = không miễn)" type="number" min="0" :value="$zone->free_ship_from" />
        <div class="sm:col-span-2">
            <x-field name="pickup_point" label="Điểm nhận tập trung (điền thì cụm này là nhận tại điểm, không phí giao)" :value="$zone->pickup_point" />
        </div>
        <label class="flex min-h-11 items-center gap-2 text-sm sm:col-span-2">
            <input type="checkbox" name="is_active" value="1" class="size-5 rounded border-stone-300" @checked(old('is_active', $zone->is_active))> Đang dùng
        </label>
        <div class="flex gap-3 sm:col-span-2">
            <button class="min-h-11 rounded-lg bg-emerald-700 px-6 font-semibold text-white hover:bg-emerald-800">Lưu</button>
            <a href="{{ route('admin.zones.index') }}" class="inline-flex min-h-11 items-center rounded-lg border border-stone-300 bg-white px-4 hover:bg-stone-100">Hủy</a>
        </div>
    </form>
</x-layouts.admin>
