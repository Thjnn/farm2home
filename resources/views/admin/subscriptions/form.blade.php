@php($weekdays = [1 => 'Thứ Hai', 2 => 'Thứ Ba', 3 => 'Thứ Tư', 4 => 'Thứ Năm', 5 => 'Thứ Sáu', 6 => 'Thứ Bảy', 7 => 'Chủ nhật'])
<x-layouts.admin :title="$subscription->exists ? 'Sửa giỏ định kỳ' : 'Thêm giỏ định kỳ'">
    <form method="POST" action="{{ $subscription->exists ? route('admin.subscriptions.update', $subscription) : route('admin.subscriptions.store') }}" class="grid max-w-2xl gap-4 sm:grid-cols-2">
        @csrf
        @if ($subscription->exists)
            @method('PUT')
        @endif
        <div class="sm:col-span-2">
            <x-field name="phone" label="Số điện thoại khách (khách phải đã từng đặt hàng)" type="tel" inputmode="numeric" :value="$subscription->customer?->phone" required />
        </div>
        <div>
            <label for="basket_id" class="mb-1 block text-sm font-medium text-stone-700">Giỏ rau</label>
            <select id="basket_id" name="basket_id" class="block min-h-11 w-full rounded-lg border border-stone-300 bg-white px-3">
                @foreach ($baskets as $basket)
                    <option value="{{ $basket->id }}" @selected((int) old('basket_id', $subscription->basket_id) === $basket->id)>{{ $basket->name }} — @vnd($basket->price)</option>
                @endforeach
            </select>
            @error('basket_id')
                <p class="mt-1 text-sm text-rose-700">{{ $message }}</p>
            @enderror
        </div>
        <div>
            <label for="frequency" class="mb-1 block text-sm font-medium text-stone-700">Tần suất</label>
            <select id="frequency" name="frequency" class="block min-h-11 w-full rounded-lg border border-stone-300 bg-white px-3">
                @foreach ($frequencies as $frequency)
                    <option value="{{ $frequency->value }}" @selected(old('frequency', $subscription->frequency?->value) === $frequency->value)>{{ $frequency->label() }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="delivery_weekday" class="mb-1 block text-sm font-medium text-stone-700">Thứ giao</label>
            <select id="delivery_weekday" name="delivery_weekday" class="block min-h-11 w-full rounded-lg border border-stone-300 bg-white px-3">
                @foreach ($weekdays as $number => $label)
                    <option value="{{ $number }}" @selected((int) old('delivery_weekday', $subscription->delivery_weekday) === $number)>{{ $label }}</option>
                @endforeach
            </select>
            @error('delivery_weekday')
                <p class="mt-1 text-sm text-rose-700">{{ $message }}</p>
            @enderror
        </div>
        <div>
            <label for="time_slot" class="mb-1 block text-sm font-medium text-stone-700">Khung giờ giao</label>
            <select id="time_slot" name="time_slot" class="block min-h-11 w-full rounded-lg border border-stone-300 bg-white px-3">
                @foreach ($timeSlots as $slot)
                    <option value="{{ $slot }}" @selected(old('time_slot', $subscription->time_slot) === $slot)>{{ $slot }}</option>
                @endforeach
            </select>
        </div>
        <x-field name="start_date" label="Bắt đầu từ ngày" type="date" :value="$subscription->start_date?->toDateString()" required />
        <x-field name="end_date" label="Kết thúc ngày (không bắt buộc)" type="date" :value="$subscription->end_date?->toDateString()" />
        <div>
            <label for="status" class="mb-1 block text-sm font-medium text-stone-700">Trạng thái</label>
            <select id="status" name="status" class="block min-h-11 w-full rounded-lg border border-stone-300 bg-white px-3">
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}" @selected(old('status', $subscription->status?->value) === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex gap-3 sm:col-span-2">
            <button class="min-h-11 rounded-lg bg-emerald-700 px-6 font-semibold text-white hover:bg-emerald-800">Lưu</button>
            <a href="{{ route('admin.subscriptions.index') }}" class="inline-flex min-h-11 items-center rounded-lg border border-stone-300 bg-white px-4 hover:bg-stone-100">Hủy</a>
        </div>
    </form>
</x-layouts.admin>
