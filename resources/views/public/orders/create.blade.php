<x-layouts.public title="Đặt hàng">
    <h1 class="mb-1 text-xl font-semibold">Đặt hàng</h1>
    <p class="mb-4 text-sm text-stone-600">Không cần tài khoản. Chúng tôi dùng số điện thoại để xác nhận và giao đơn.</p>

    @include('partials.order-form-script')

    @if ($errors->any())
        <div role="alert" class="mb-4 rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-900">
            Đơn chưa gửi được. Vui lòng xem lại các mục được đánh dấu bên dưới.
        </div>
    @endif

    <form method="POST" action="{{ route('orders.store') }}" x-data="orderForm(...window.orderFormData)" class="space-y-6">
        @csrf

        @include('partials.order-lines', ['maxQuantity' => 20])

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="delivery_date" class="mb-1 block text-sm font-medium text-stone-700">Ngày giao</label>
                <select id="delivery_date" name="delivery_date" class="block min-h-11 w-full rounded-lg border border-stone-300 bg-white px-3 text-base">
                    @foreach ($dates as $date)
                        <option value="{{ $date->format('Y-m-d') }}" @selected(old('delivery_date') === $date->format('Y-m-d'))>
                            {{ str($date->locale('vi')->isoFormat('dddd, DD/MM'))->ucfirst() }}
                        </option>
                    @endforeach
                </select>
                @error('delivery_date')
                    <p class="mt-1 text-sm text-rose-700">{{ $message }}</p>
                @enderror
            </div>

            <fieldset>
                <legend class="mb-1 text-sm font-medium text-stone-700">Khung giờ giao</legend>
                <div class="grid grid-cols-3 gap-2">
                    @foreach ($timeSlots as $slot)
                        <label class="flex min-h-11 cursor-pointer items-center justify-center rounded-lg border border-stone-300 bg-white px-1 text-center text-sm has-checked:border-emerald-700 has-checked:bg-emerald-50 has-checked:font-semibold">
                            <input type="radio" name="time_slot" value="{{ $slot }}" class="sr-only" @checked(old('time_slot', $timeSlots[array_key_last($timeSlots)]) === $slot)>
                            {{ $slot }}
                        </label>
                    @endforeach
                </div>
                @error('time_slot')
                    <p class="mt-1 text-sm text-rose-700">{{ $message }}</p>
                @enderror
            </fieldset>
        </div>

        <div>
            <label for="delivery_zone_id" class="mb-1 block text-sm font-medium text-stone-700">Khu vực giao</label>
            <select id="delivery_zone_id" name="delivery_zone_id" x-model="zoneId" class="block min-h-11 w-full rounded-lg border border-stone-300 bg-white px-3 text-base">
                @foreach ($zones as $zone)
                    <option value="{{ $zone->id }}">
                        {{ $zone->name }} —
                        @if ($zone->isPickup())
                            Nhận tại: {{ $zone->pickup_point }}
                        @else
                            Phí giao @vnd($zone->shipping_fee)
                        @endif
                    </option>
                @endforeach
            </select>
            @error('delivery_zone_id')
                <p class="mt-1 text-sm text-rose-700">{{ $message }}</p>
            @enderror
        </div>

        <div class="space-y-4">
            <x-field name="name" label="Họ tên" autocomplete="name" required />
            <x-field name="phone" label="Số điện thoại" type="tel" inputmode="numeric" autocomplete="tel" placeholder="0xxxxxxxxx" required />
            <div x-show="!zone.pickup">
                <x-field name="address" label="Địa chỉ giao" autocomplete="street-address" />
            </div>
        </div>

        <fieldset>
            <legend class="mb-1 text-sm font-medium text-stone-700">Thanh toán</legend>
            <div class="grid grid-cols-2 gap-2">
                @foreach ($paymentMethods as $method)
                    <label class="flex min-h-11 cursor-pointer items-center justify-center rounded-lg border border-stone-300 bg-white px-2 text-center text-sm has-checked:border-emerald-700 has-checked:bg-emerald-50 has-checked:font-semibold">
                        <input type="radio" name="payment_method" value="{{ $method->value }}" class="sr-only" @checked(old('payment_method', 'cod') === $method->value)>
                        {{ $method->label() }}
                    </label>
                @endforeach
            </div>
            @error('payment_method')
                <p class="mt-1 text-sm text-rose-700">{{ $message }}</p>
            @enderror
        </fieldset>

        <div>
            <label for="note" class="mb-1 block text-sm font-medium text-stone-700">Ghi chú (không bắt buộc)</label>
            <textarea id="note" name="note" rows="2" class="block w-full rounded-lg border border-stone-300 bg-white px-3 py-2 text-base">{{ old('note') }}</textarea>
            @error('note')
                <p class="mt-1 text-sm text-rose-700">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="flex items-start gap-3 text-sm">
                <input type="checkbox" name="data_consent" value="1" class="mt-0.5 size-5 shrink-0 rounded border-stone-300" @checked(old('data_consent'))>
                <span>Tôi đồng ý để FARM2HOME dùng thông tin này để xử lý và giao đơn.</span>
            </label>
            @error('data_consent')
                <p class="mt-1 text-sm text-rose-700">{{ $message }}</p>
            @enderror
        </div>

        <div class="rounded-xl border border-stone-200 bg-white p-4" aria-live="polite">
            <dl class="space-y-1 text-sm">
                <div class="flex justify-between"><dt>Tiền hàng</dt><dd x-text="money(subtotal)"></dd></div>
                <div class="flex justify-between"><dt>Phí giao</dt><dd x-text="money(shipping)"></dd></div>
                <div class="flex justify-between border-t border-stone-200 pt-2 text-base font-semibold"><dt>Tạm tính</dt><dd x-text="money(total)"></dd></div>
            </dl>
            <p class="mt-2 text-xs text-stone-500">Tổng tiền chính xác được xác nhận sau khi gửi đơn.</p>
        </div>

        <button class="min-h-12 w-full rounded-lg bg-emerald-700 px-4 text-base font-semibold text-white hover:bg-emerald-800">Gửi đơn</button>
    </form>
</x-layouts.public>
