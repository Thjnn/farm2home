<x-layouts.admin title="Tạo đơn hộ khách">
    @include('partials.order-form-script')

    <form method="POST" action="{{ route('admin.orders.store') }}" x-data="orderForm(...window.orderFormData)" class="max-w-2xl space-y-6">
        @csrf

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="channel" class="mb-1 block text-sm font-medium text-stone-700">Kênh nhận đơn</label>
                <select id="channel" name="channel" class="block min-h-11 w-full rounded-lg border border-stone-300 bg-white px-3">
                    @foreach ($channels as $channel)
                        <option value="{{ $channel->value }}" @selected(old('channel', 'zalo') === $channel->value)>{{ $channel->label() }}</option>
                    @endforeach
                </select>
                @error('channel')
                    <p class="mt-1 text-sm text-rose-700">{{ $message }}</p>
                @enderror
            </div>
            <x-field name="delivery_date" label="Ngày giao" type="date" :value="today()->addDay()->toDateString()" min="{{ today()->toDateString() }}" required />
        </div>

        @include('partials.order-lines', ['maxQuantity' => 50])

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="time_slot" class="mb-1 block text-sm font-medium text-stone-700">Khung giờ giao</label>
                <select id="time_slot" name="time_slot" class="block min-h-11 w-full rounded-lg border border-stone-300 bg-white px-3">
                    @foreach ($timeSlots as $slot)
                        <option value="{{ $slot }}" @selected(old('time_slot', '17:00-19:00') === $slot)>{{ $slot }}</option>
                    @endforeach
                </select>
                @error('time_slot')
                    <p class="mt-1 text-sm text-rose-700">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label for="delivery_zone_id" class="mb-1 block text-sm font-medium text-stone-700">Khu vực giao</label>
                <select id="delivery_zone_id" name="delivery_zone_id" x-model="zoneId" class="block min-h-11 w-full rounded-lg border border-stone-300 bg-white px-3">
                    @foreach ($zones as $zone)
                        <option value="{{ $zone->id }}">{{ $zone->name }}</option>
                    @endforeach
                </select>
                @error('delivery_zone_id')
                    <p class="mt-1 text-sm text-rose-700">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <x-field name="name" label="Họ tên khách" required />
            <x-field name="phone" label="Số điện thoại" type="tel" inputmode="numeric" required />
        </div>
        <div x-show="!zone.pickup">
            <x-field name="address" label="Địa chỉ giao" />
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="payment_method" class="mb-1 block text-sm font-medium text-stone-700">Thanh toán</label>
                <select id="payment_method" name="payment_method" class="block min-h-11 w-full rounded-lg border border-stone-300 bg-white px-3">
                    @foreach ($paymentMethods as $method)
                        <option value="{{ $method->value }}" @selected(old('payment_method', 'cod') === $method->value)>{{ $method->label() }}</option>
                    @endforeach
                </select>
            </div>
            <x-field name="note" label="Ghi chú" />
        </div>

        <label class="flex items-start gap-3 text-sm">
            <input type="hidden" name="data_consent" value="0">
            <input type="checkbox" name="data_consent" value="1" class="mt-0.5 size-5 shrink-0 rounded border-stone-300" @checked(old('data_consent'))>
            <span>Khách đã đồng ý cho dùng thông tin để xử lý và giao đơn.</span>
        </label>

        <div class="rounded-xl border border-stone-200 bg-white p-4 text-sm" aria-live="polite">
            Tạm tính: <span class="font-semibold" x-text="money(total)"></span>
            (tiền hàng <span x-text="money(subtotal)"></span>, phí giao <span x-text="money(shipping)"></span>)
        </div>

        <button class="min-h-12 rounded-lg bg-emerald-700 px-6 font-semibold text-white hover:bg-emerald-800">Tạo đơn</button>
    </form>
</x-layouts.admin>
