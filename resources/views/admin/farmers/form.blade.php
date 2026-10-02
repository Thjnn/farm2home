<x-layouts.admin :title="$farmer->exists ? 'Sửa nông hộ' : 'Thêm nông hộ'">
    <form method="POST" enctype="multipart/form-data" action="{{ $farmer->exists ? route('admin.farmers.update', $farmer) : route('admin.farmers.store') }}" class="max-w-3xl space-y-4">
        @csrf
        @if ($farmer->exists)
            @method('PUT')
        @endif
        <div class="grid gap-4 sm:grid-cols-2">
            <x-field name="code" label="Mã (vd NH01, dùng trong mã lô)" :value="$farmer->code" maxlength="20" required />
            <x-field name="name" label="Họ tên" :value="$farmer->name" required />
            <x-field name="phone" label="Số điện thoại" type="tel" inputmode="numeric" :value="$farmer->phone" required />
            <x-field name="zalo_phone" label="Số Zalo (để trống nếu trùng số điện thoại)" type="tel" inputmode="numeric" :value="$farmer->zalo_phone" />
            <x-field name="address" label="Địa chỉ (không hiện công khai)" :value="$farmer->address" />
            <x-field name="growing_area" label="Khu vực trồng (hiện trên trang QR)" :value="$farmer->growing_area" />
            <x-field name="area_m2" label="Diện tích (m²)" type="number" min="0" :value="$farmer->area_m2" />
            <x-field name="farming_method" label="Cách canh tác (theo nông hộ khai)" :value="$farmer->farming_method" />
            <div>
                <label for="status" class="mb-1 block text-sm font-medium text-stone-700">Trạng thái</label>
                <select id="status" name="status" class="block min-h-11 w-full rounded-lg border border-stone-300 bg-white px-3">
                    @foreach ($statuses as $status)
                        <option value="{{ $status->value }}" @selected(old('status', $farmer->status?->value) === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
                @error('status')
                    <p class="mt-1 text-sm text-rose-700">{{ $message }}</p>
                @enderror
            </div>
            <x-field name="note" label="Ghi chú nội bộ" :value="$farmer->note" />
        </div>

        <fieldset class="rounded-xl border border-stone-200 bg-white p-4">
            <legend class="px-1 text-sm font-medium text-stone-700">Chứng nhận</legend>
            <p class="mb-3 text-sm text-stone-600">Chỉ điền khi có giấy tờ thật. Trang QR chỉ hiện chứng nhận khi có file và còn hạn.</p>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-field name="certificate_name" label="Tên chứng nhận" :value="$farmer->certificate_name" />
                <x-field name="certificate_expires_at" label="Hết hạn ngày" type="date" :value="$farmer->certificate_expires_at?->toDateString()" />
                <div class="sm:col-span-2">
                    <label for="certificate" class="mb-1 block text-sm font-medium text-stone-700">File chứng nhận (PDF/JPG/PNG, tối đa 4 MB)</label>
                    <input id="certificate" type="file" name="certificate" accept=".pdf,.jpg,.jpeg,.png" class="block w-full text-sm">
                    @if ($farmer->certificate_file)
                        <p class="mt-1 text-xs text-stone-600">Đã có file: {{ $farmer->certificate_file }}</p>
                    @endif
                    @error('certificate')
                        <p class="mt-1 text-sm text-rose-700">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </fieldset>

        <fieldset class="rounded-xl border border-stone-200 bg-white p-4">
            <legend class="px-1 text-sm font-medium text-stone-700">Loại rau trồng (kg/tuần ước tính; để trống = không trồng)</legend>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($products as $product)
                    <label class="block text-sm">
                        <span class="mb-1 block text-stone-700">{{ $product->name }}</span>
                        <input type="number" step="0.5" min="0" name="products[{{ $product->id }}]"
                            value="{{ old("products.$product->id", isset($weeklyKg[$product->id]) ? (float) $weeklyKg[$product->id] : '') }}"
                            class="block min-h-11 w-full rounded-lg border border-stone-300 px-3">
                    </label>
                @endforeach
            </div>
        </fieldset>

        <div class="flex gap-3">
            <button class="min-h-11 rounded-lg bg-emerald-700 px-6 font-semibold text-white hover:bg-emerald-800">Lưu</button>
            <a href="{{ route('admin.farmers.index') }}" class="inline-flex min-h-11 items-center rounded-lg border border-stone-300 bg-white px-4 hover:bg-stone-100">Hủy</a>
        </div>
    </form>
</x-layouts.admin>
