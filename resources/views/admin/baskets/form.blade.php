<x-layouts.admin :title="$basket->exists ? 'Sửa giỏ rau' : 'Thêm giỏ rau'">
    <form method="POST" action="{{ $basket->exists ? route('admin.baskets.update', $basket) : route('admin.baskets.store') }}" class="max-w-2xl space-y-4">
        @csrf
        @if ($basket->exists)
            @method('PUT')
        @endif
        <div class="grid gap-4 sm:grid-cols-2">
            <x-field name="name" label="Tên giỏ" :value="$basket->name" required />
            <x-field name="price" label="Giá / giỏ (đ)" type="number" min="0" :value="$basket->price" required />
            <x-field name="household_min" label="Số người tối thiểu" type="number" min="1" max="20" :value="$basket->household_min" required />
            <x-field name="household_max" label="Số người tối đa" type="number" min="1" max="20" :value="$basket->household_max" required />
        </div>
        <div>
            <label for="description" class="mb-1 block text-sm font-medium text-stone-700">Mô tả</label>
            <textarea id="description" name="description" rows="2" class="block w-full rounded-lg border border-stone-300 bg-white px-3 py-2">{{ old('description', $basket->description) }}</textarea>
        </div>

        <fieldset class="rounded-xl border border-stone-200 bg-white p-4">
            <legend class="px-1 text-sm font-medium text-stone-700">Thành phần (số gói; 0 = không có trong giỏ)</legend>
            @error('items')
                <p class="mb-2 text-sm text-rose-700" role="alert">{{ $message }}</p>
            @enderror
            <div class="space-y-2">
                @foreach ($products as $index => $product)
                    @php($line = $lines[$product->id] ?? null)
                    <div class="flex flex-wrap items-center gap-3 text-sm">
                        <input type="hidden" name="items[{{ $index }}][product_id]" value="{{ $product->id }}">
                        <label for="item-{{ $product->id }}" class="min-w-32 flex-1">{{ $product->name }} <span class="text-stone-500">(@kg($product->pack_weight_kg)/gói)</span></label>
                        <input id="item-{{ $product->id }}" type="number" min="0" max="20" name="items[{{ $index }}][quantity]"
                            value="{{ old("items.$index.quantity", $line?->quantity ?? 0) }}" class="h-11 w-20 rounded-lg border border-stone-300 px-2 text-right">
                        <label class="flex items-center gap-2">
                            <input type="hidden" name="items[{{ $index }}][allow_substitute]" value="0">
                            <input type="checkbox" name="items[{{ $index }}][allow_substitute]" value="1" class="size-5 rounded border-stone-300" @checked(old("items.$index.allow_substitute", $line?->allow_substitute ?? true))>
                            Cho đổi rau tương đương
                        </label>
                    </div>
                @endforeach
            </div>
        </fieldset>

        <label class="flex min-h-11 items-center gap-2 text-sm">
            <input type="checkbox" name="is_active" value="1" class="size-5 rounded border-stone-300" @checked(old('is_active', $basket->is_active))> Đang bán
        </label>
        <div class="flex gap-3">
            <button class="min-h-11 rounded-lg bg-emerald-700 px-6 font-semibold text-white hover:bg-emerald-800">Lưu</button>
            <a href="{{ route('admin.baskets.index') }}" class="inline-flex min-h-11 items-center rounded-lg border border-stone-300 bg-white px-4 hover:bg-stone-100">Hủy</a>
        </div>
    </form>
</x-layouts.admin>
