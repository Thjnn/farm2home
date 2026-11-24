<x-layouts.public title="Đặt hàng">
    @php
        // Lỗi ở phần giao hàng (bước 2) thì mở thẳng bước 2; lỗi ở phần chọn rau thì ở bước 1.
        $startAtDelivery = $errors->any() && ! $errors->hasAny(['order_type', 'basket_id', 'items', 'items.*']);
        $zoneMap = $zones->mapWithKeys(fn ($zone) => [$zone->id => [
            'fee' => $zone->shipping_fee,
            'freeFrom' => $zone->free_ship_from,
            'pickup' => $zone->isPickup(),
        ]]);
        $chip = 'relative flex min-h-11 cursor-pointer items-center justify-center rounded-xl border border-stone-300 bg-white px-3 text-center has-checked:border-emerald-700 has-checked:bg-emerald-50 has-checked:font-semibold has-checked:text-emerald-900 has-focus-visible:ring-2 has-focus-visible:ring-emerald-700/40';
        $label = 'mb-2 block font-medium text-stone-800';
    @endphp

    <div x-data="orderCart({
            zones: @js($zoneMap),
            zoneId: @js(old('delivery_zone_id', $zones->first()?->id)),
            preselectedBasket: @js($preselectedBasket),
            startAtDelivery: @js($startAtDelivery),
        })">
        <div x-ref="top" class="mb-5 scroll-mt-4">
            <h1 class="text-2xl font-bold tracking-tight">Đặt hàng</h1>
            <p class="mt-1 text-stone-600">Không cần tài khoản, chỉ cần số điện thoại để xác nhận và giao đơn.</p>

            <ol class="mt-4 grid grid-cols-2 gap-2" aria-label="Các bước đặt hàng">
                @foreach ([1 => 'Chọn rau', 2 => 'Giao hàng & thanh toán'] as $number => $name)
                    <li :aria-current="step === {{ $number }} ? 'step' : null">
                        <div class="h-1.5 rounded-full transition-colors duration-300 motion-reduce:transition-none" :class="step >= {{ $number }} ? 'bg-emerald-700' : 'bg-stone-200'"></div>
                        <p class="mt-1.5 text-sm" :class="step === {{ $number }} ? 'font-semibold text-emerald-900' : 'text-stone-500'">
                            <span aria-hidden="true">{{ $number === 1 ? '①' : '②' }}</span> {{ $name }}
                        </p>
                    </li>
                @endforeach
            </ol>
        </div>

        @if ($errors->any())
            <div role="alert" class="mb-4 rounded-xl bg-rose-50 px-4 py-3 text-rose-900">
                Đơn chưa gửi được. Vui lòng xem lại các mục được đánh dấu bên dưới.
            </div>
        @endif

        <form method="POST" action="{{ route('orders.store') }}" x-on:pageshow.window="$event.persisted && location.reload()"
            @submit="if (chosenCount === 0) { $event.preventDefault(); goTo(1); next(); return; } $el.querySelector('[data-submit]').disabled = true">
            @csrf
            <input type="hidden" name="submission_token" value="{{ old('submission_token', (string) Str::uuid()) }}">
            <input type="hidden" name="order_type" :value="type">
            <template x-if="type === 'basket' && basket">
                <input type="hidden" name="basket_id" :value="basket.id">
            </template>
            <template x-if="type === 'single'">
                <div>
                    <template x-for="(line, index) in chosenLines" :key="line.id">
                        <div>
                            <input type="hidden" :name="`items[${index}][product_id]`" :value="line.id">
                            <input type="hidden" :name="`items[${index}][quantity]`" :value="line.quantity">
                        </div>
                    </template>
                </div>
            </template>

            {{-- ① Chọn rau --}}
            <div x-show="step === 1" @if ($startAtDelivery) x-cloak @endif>
                <div class="mb-4 grid grid-cols-2 rounded-xl bg-stone-200/70 p-1" role="group" aria-label="Loại đơn">
                    <button type="button" @click="type = 'basket'" :aria-pressed="type === 'basket'"
                        :class="type === 'basket' ? 'bg-white text-emerald-900 shadow-sm' : 'text-stone-600'"
                        class="min-h-11 rounded-lg font-semibold transition-colors">Giỏ rau</button>
                    <button type="button" @click="type = 'single'" :aria-pressed="type === 'single'"
                        :class="type === 'single' ? 'bg-white text-emerald-900 shadow-sm' : 'text-stone-600'"
                        class="min-h-11 rounded-lg font-semibold transition-colors">Chọn lẻ</button>
                </div>

                @foreach (['order_type', 'basket_id', 'items'] as $field)
                    @error($field)
                        <p class="mb-3 text-rose-700" role="alert">{{ $message }}</p>
                    @enderror
                @endforeach
                @if ($errors->has('items.*'))
                    <p class="mb-3 text-rose-700" role="alert">{{ $errors->first('items.*') }}</p>
                @endif

                {{-- Đã chọn: ghim ở đầu, không đổi theo trang / tìm kiếm / danh mục. --}}
                <section x-show="type === 'basket' && basket" x-cloak aria-label="Giỏ đã chọn" class="mb-4 rounded-xl border border-emerald-700 bg-emerald-50 p-4">
                    <p class="text-sm font-semibold uppercase tracking-wide text-emerald-800">Đã chọn</p>
                    <div class="mt-1 flex items-baseline justify-between gap-3">
                        <p class="font-semibold" x-text="basket?.name"></p>
                        <p class="shrink-0 font-semibold text-emerald-800" x-text="money(basket?.price ?? 0)"></p>
                    </div>
                    <p class="mt-1 text-stone-700" x-text="basket?.summary"></p>
                </section>

                <section x-show="type === 'single' && chosenLines.length" x-cloak aria-labelledby="chosen-heading" class="mb-4 rounded-xl border border-emerald-700 bg-emerald-50 p-3">
                    <h2 id="chosen-heading" class="px-1 font-semibold text-emerald-900">Đã chọn (<span x-text="chosenLines.length"></span>)</h2>
                    <ul class="mt-2 divide-y divide-emerald-100">
                        <template x-for="line in chosenLines" :key="line.id">
                            <li class="flex items-center gap-3 py-2">
                                <div class="min-w-0 flex-1 px-1">
                                    <p class="truncate font-medium" x-text="line.name"></p>
                                    <p class="text-sm text-stone-600" x-text="money(line.price * line.quantity)"></p>
                                </div>
                                <x-qty-stepper item="line" />
                            </li>
                        </template>
                    </ul>
                </section>

                {{-- Giỏ rau --}}
                <section x-show="type === 'basket'" aria-labelledby="basket-list-heading"
                    x-data="pagedList({ endpoint: @js(route('api.baskets')), key: 'trang_gio', initial: @js($baskets) })">
                    <h2 id="basket-list-heading" x-ref="heading" class="mb-3 scroll-mt-4 text-lg font-semibold">Chọn giỏ</h2>
                    <div class="grid gap-3 sm:grid-cols-2" :aria-busy="loading">
                        @foreach ($baskets['items'] as $skeleton)
                            <div x-show="loading" aria-hidden="true" class="h-32 animate-pulse rounded-xl border border-stone-200 bg-white"></div>
                        @endforeach
                        <template x-for="option in items" :key="option.id">
                            <button type="button" x-show="!loading" @click="chooseBasket(option)" :aria-pressed="basket?.id === option.id"
                                :class="basket?.id === option.id ? 'border-emerald-700 bg-emerald-50 ring-1 ring-emerald-700' : 'border-stone-200 bg-white hover:border-stone-300'"
                                class="flex min-h-32 w-full flex-col rounded-xl border p-4 text-left transition-colors">
                                <span class="flex w-full items-baseline justify-between gap-3">
                                    <span class="font-semibold" x-text="option.name"></span>
                                    <span class="shrink-0 font-semibold text-emerald-800" x-text="money(option.price)"></span>
                                </span>
                                <span x-show="option.description" class="mt-1 text-stone-600" x-text="option.description"></span>
                                <span class="mt-1 text-stone-700" x-text="option.items.map((line) => line.quantity + ' × ' + line.name).join(', ')"></span>
                                <span class="mt-auto pt-2 text-sm font-semibold" :class="basket?.id === option.id ? 'text-emerald-800' : 'text-stone-500'"
                                    x-text="basket?.id === option.id ? '✓ Đã chọn giỏ này' : 'Bấm để chọn'"></span>
                            </button>
                        </template>
                    </div>
                    <x-pagination label="Trang giỏ rau" :total-pages="$baskets['totalPages']" />
                </section>

                {{-- Chọn lẻ --}}
                <section x-show="type === 'single'" x-cloak aria-labelledby="product-list-heading"
                    x-data="pagedList({
                        endpoint: @js(route('api.products')),
                        key: 'trang',
                        initial: @js($products),
                        filters: { q: '', category: '' },
                        urlKeys: { q: 'q', category: 'danh_muc' },
                    })">
                    <h2 id="product-list-heading" x-ref="heading" class="mb-3 scroll-mt-4 text-lg font-semibold">Chọn rau <span class="font-normal text-stone-500">(số gói)</span></h2>
                    <div class="relative mb-3">
                        <label for="order-product-search" class="sr-only">Tìm rau theo tên</label>
                        <svg class="pointer-events-none absolute left-3 top-1/2 size-5 -translate-y-1/2 text-stone-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 1 0 0 11 5.5 5.5 0 0 0 0-11ZM2 9a7 7 0 1 1 12.45 4.39l3.08 3.08a.75.75 0 1 1-1.06 1.06l-3.08-3.08A7 7 0 0 1 2 9Z" clip-rule="evenodd"/>
                        </svg>
                        <input id="order-product-search" type="search" placeholder="Tìm rau, ví dụ: cải" autocomplete="off" enterkeyhint="search"
                            x-model="filters.q" @input.debounce.300ms="refilter()" @keydown.enter.prevent="refilter()"
                            class="block min-h-12 w-full rounded-xl border border-stone-300 bg-white pl-10 pr-3 text-base placeholder:text-stone-400 focus:border-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-700/20">
                    </div>
                    <div class="-mx-4 mb-3 flex gap-2 overflow-x-auto px-4 pb-1 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden" role="group" aria-label="Danh mục">
                        @foreach (['' => 'Tất cả'] + collect($categories)->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all() as $value => $name)
                            <button type="button" @click="filters.category = @js($value); refilter()" :aria-pressed="filters.category === @js($value)"
                                :class="filters.category === @js($value) ? 'border-emerald-700 bg-emerald-700 text-white' : 'border-stone-300 bg-white text-stone-800 hover:bg-stone-100'"
                                class="min-h-11 shrink-0 whitespace-nowrap rounded-full border px-4 font-medium transition-colors">{{ $name }}</button>
                        @endforeach
                    </div>

                    <ul class="space-y-2" :aria-busy="loading">
                        @foreach ($products['items'] as $skeleton)
                            <li x-show="loading" aria-hidden="true" class="h-[68px] animate-pulse rounded-xl border border-stone-200 bg-white"></li>
                        @endforeach
                        <template x-for="product in items" :key="product.id">
                            <li x-show="!loading" class="flex items-center gap-3 rounded-xl border bg-white p-2.5 transition-colors"
                                :class="quantity(product.id) ? 'border-emerald-700' : 'border-stone-200'">
                                <div class="size-12 shrink-0 overflow-hidden rounded-lg bg-emerald-50">
                                    <img x-show="product.imageUrl" :src="product.imageUrl" alt="" loading="lazy" width="48" height="48" class="size-full object-cover">
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate font-semibold" x-text="product.name"></p>
                                    <p class="text-stone-600"><span x-text="money(product.price)"></span> / <span x-text="product.unit"></span></p>
                                </div>
                                <x-qty-stepper item="product" />
                            </li>
                        </template>
                    </ul>
                    <p x-show="!loading && total === 0" x-cloak class="rounded-xl border border-dashed border-stone-300 bg-white px-4 py-8 text-center text-stone-600">
                        Chưa có món nào khớp. Thử từ khoá khác nhé.
                    </p>
                    <x-pagination label="Trang danh sách rau" :total-pages="$products['totalPages']" />
                </section>
            </div>

            {{-- ② Giao hàng & thanh toán --}}
            <div x-show="step === 2" @unless ($startAtDelivery) x-cloak @endunless class="space-y-6">
                <button type="button" @click="goTo(1)" class="-ml-2 inline-flex min-h-11 items-center rounded-lg px-2 font-medium text-emerald-800 hover:bg-emerald-50">
                    <span aria-hidden="true">‹&nbsp;</span>Sửa món đã chọn (<span x-text="countLabel"></span>)
                </button>

                <fieldset class="min-w-0">
                    <legend class="{{ $label }}">Ngày giao</legend>
                    <div class="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                        @foreach ($dates as $date)
                            <label class="{{ $chip }} min-w-20 shrink-0 flex-col py-1.5 leading-tight">
                                <input type="radio" name="delivery_date" value="{{ $date->format('Y-m-d') }}" class="sr-only"
                                    @checked(old('delivery_date', $dates->first()->format('Y-m-d')) === $date->format('Y-m-d'))>
                                <span class="text-sm">{{ str($date->locale('vi')->isoFormat('dddd'))->ucfirst() }}</span>
                                <span class="font-semibold">{{ $date->format('d/m') }}</span>
                            </label>
                        @endforeach
                    </div>
                    @error('delivery_date')
                        <p class="mt-1 text-rose-700">{{ $message }}</p>
                    @enderror
                </fieldset>

                <fieldset class="min-w-0">
                    <legend class="{{ $label }}">Khung giờ giao</legend>
                    <div class="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                        @foreach ($timeSlots as $slot)
                            <label class="{{ $chip }} shrink-0 whitespace-nowrap px-4">
                                <input type="radio" name="time_slot" value="{{ $slot }}" class="sr-only" @checked(old('time_slot', $timeSlots[array_key_last($timeSlots)]) === $slot)>
                                {{ $slot }}
                            </label>
                        @endforeach
                    </div>
                    @error('time_slot')
                        <p class="mt-1 text-rose-700">{{ $message }}</p>
                    @enderror
                </fieldset>

                <fieldset class="min-w-0">
                    <legend class="{{ $label }}">Khu vực giao</legend>
                    <div class="grid gap-2 sm:grid-cols-2">
                        @foreach ($zones as $zone)
                            <label class="flex min-h-14 cursor-pointer items-start gap-3 rounded-xl border border-stone-300 bg-white p-3 has-checked:border-emerald-700 has-checked:bg-emerald-50 has-checked:ring-1 has-checked:ring-emerald-700">
                                <input type="radio" name="delivery_zone_id" value="{{ $zone->id }}" x-model="zoneId" class="mt-1 size-5 shrink-0 accent-emerald-700">
                                <span class="min-w-0">
                                    <span class="block font-medium">{{ $zone->name }}</span>
                                    <span class="block text-stone-600">
                                        @if ($zone->isPickup())
                                            Nhận tại: {{ $zone->pickup_point }} · miễn phí
                                        @else
                                            Phí giao @vnd($zone->shipping_fee)@if ($zone->free_ship_from) · miễn phí từ @vnd($zone->free_ship_from)@endif
                                        @endif
                                    </span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                    @error('delivery_zone_id')
                        <p class="mt-1 text-rose-700">{{ $message }}</p>
                    @enderror
                </fieldset>

                <div class="space-y-4">
                    <x-field name="name" label="Họ tên" autocomplete="name" required />
                    <x-field name="phone" label="Số điện thoại" type="tel" inputmode="tel" autocomplete="tel" placeholder="0xxxxxxxxx" pattern="0[0-9]{9}" required />
                    <div x-show="!zone.pickup">
                        <x-field name="address" label="Địa chỉ giao" autocomplete="street-address" />
                    </div>
                </div>

                <fieldset class="min-w-0" x-data="{ payment: @js(old('payment_method', 'cod')) }">
                    <legend class="{{ $label }}">Thanh toán</legend>
                    <div class="grid grid-cols-2 gap-2">
                        @foreach ($paymentMethods as $method)
                            <label class="{{ $chip }}">
                                <input type="radio" name="payment_method" value="{{ $method->value }}" x-model="payment" class="sr-only" @checked(old('payment_method', 'cod') === $method->value)>
                                {{ $method->label() }}
                            </label>
                        @endforeach
                    </div>
                    <p x-show="payment === 'bank_transfer'" x-cloak class="mt-2 rounded-lg bg-emerald-50 px-3 py-2 text-emerald-900">Sau khi gửi đơn, bạn sẽ thấy mã QR với số tiền và nội dung điền sẵn.</p>
                    @error('payment_method')
                        <p class="mt-1 text-rose-700">{{ $message }}</p>
                    @enderror
                </fieldset>

                <div>
                    <label for="note" class="{{ $label }}">Ghi chú <span class="font-normal text-stone-500">(không bắt buộc)</span></label>
                    <textarea id="note" name="note" rows="2" class="block w-full rounded-xl border border-stone-300 bg-white px-3 py-2 text-base">{{ old('note') }}</textarea>
                    @error('note')
                        <p class="mt-1 text-rose-700">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="requested_reward" class="{{ $label }}">Đổi điểm lấy quà <span class="font-normal text-stone-500">(không bắt buộc)</span></label>
                    <select id="requested_reward" name="requested_reward" class="block min-h-12 w-full rounded-xl border border-stone-300 bg-white px-3 text-base">
                        <option value="">Không đổi lần này</option>
                        @foreach ($rewards as $reward)
                            <option value="{{ $reward->key }}" data-points="{{ $reward->points }}" data-label="{{ $reward->label }} - {{ $reward->points }} điểm" @selected(old('requested_reward') === $reward->key)>{{ $reward->label }} - {{ $reward->points }} điểm</option>
                        @endforeach
                    </select>
                    <p id="reward-points" class="mt-1 text-stone-600" aria-live="polite">Nhập số điện thoại để xem bạn có bao nhiêu điểm.</p>
                    @error('requested_reward')
                        <p class="mt-1 text-rose-700">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="flex min-h-11 items-start gap-3">
                        <input type="checkbox" name="data_consent" value="1" class="mt-0.5 size-5 shrink-0 rounded border-stone-300 accent-emerald-700" @checked(old('data_consent'))>
                        <span>Tôi đồng ý để FARM2HOME dùng thông tin này để xử lý và giao đơn.</span>
                    </label>
                    @error('data_consent')
                        <p class="mt-1 text-rose-700">{{ $message }}</p>
                    @enderror
                </div>

                <div class="rounded-xl border border-stone-200 bg-white p-4" aria-live="polite">
                    <dl class="space-y-1">
                        <div class="flex justify-between"><dt>Tiền hàng</dt><dd x-text="money(subtotal)"></dd></div>
                        <div class="flex justify-between"><dt>Phí giao</dt><dd x-text="shipping === 0 ? 'Miễn phí' : money(shipping)"></dd></div>
                        <div class="flex justify-between border-t border-stone-200 pt-2 text-lg font-semibold"><dt>Tạm tính</dt><dd x-text="money(total)"></dd></div>
                    </dl>
                    <p class="mt-2 text-sm text-stone-500">Tổng tiền chính xác được xác nhận sau khi gửi đơn.</p>
                </div>
            </div>

            {{-- Chừa chỗ cho thanh dưới đáy. --}}
            <div class="h-28" aria-hidden="true"></div>

            {{-- Thanh dính đáy: số món · tạm tính · Tiếp tục / Gửi đơn --}}
            <div class="fixed inset-x-0 bottom-0 z-30 border-t border-stone-200 bg-white/95 pb-[env(safe-area-inset-bottom)] shadow-[0_-4px_16px_rgb(0_0_0/0.06)] backdrop-blur">
                <div class="mx-auto max-w-xl px-4 py-3">
                    <p x-show="hint" x-cloak x-text="hint" role="alert" class="mb-2 text-rose-700"></p>
                    <div class="flex items-center gap-3">
                        <div class="min-w-0 flex-1" aria-live="polite">
                            <p class="truncate text-sm text-stone-600" x-text="countLabel"></p>
                            <p class="text-lg font-bold leading-tight tabular-nums">
                                <span x-text="money(step === 1 ? subtotal : total)"></span>
                                <span class="text-sm font-normal text-stone-500" x-text="step === 1 ? 'tạm tính' : (shipping ? 'gồm phí giao' : '')"></span>
                            </p>
                        </div>
                        <button type="button" x-show="step === 1" @click="next()"
                            class="min-h-12 shrink-0 rounded-xl bg-emerald-700 px-6 text-base font-semibold text-white hover:bg-emerald-800 active:scale-[0.98] motion-reduce:active:scale-100">Tiếp tục <span aria-hidden="true">›</span></button>
                        <button type="submit" data-submit x-show="step === 2" x-cloak
                            class="min-h-12 shrink-0 rounded-xl bg-emerald-700 px-6 text-base font-semibold text-white hover:bg-emerald-800 active:scale-[0.98] disabled:cursor-wait disabled:opacity-60 motion-reduce:active:scale-100">Gửi đơn</button>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <script>
        // Nhập đủ số điện thoại thì hỏi số điểm, làm mờ (khóa) quà chưa đủ điểm. Server vẫn kiểm tra lại khi gửi đơn.
        (() => {
            const phone = document.getElementById('phone');
            const select = document.getElementById('requested_reward');
            const status = document.getElementById('reward-points');
            let asked = null;

            const apply = (points) => {
                for (const option of select.querySelectorAll('option[data-points]')) {
                    const missing = Number(option.dataset.points) - points;
                    option.disabled = missing > 0;
                    option.textContent = option.dataset.label + (missing > 0 ? ` (còn thiếu ${missing} điểm)` : '');
                }
                if (select.selectedOptions[0]?.disabled) {
                    select.value = '';
                }
                status.textContent = `Bạn đang có ${points} điểm.` + (points === 0 ? ' Mỗi đơn giao xong sẽ được cộng điểm.' : '');
            };

            const check = async () => {
                const value = phone.value.trim();
                if (!/^0\d{9}$/.test(value) || value === asked) {
                    return;
                }
                asked = value;
                status.textContent = 'Đang xem điểm của bạn…';
                try {
                    const response = await fetch('{{ route('lookup.points') }}', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                        body: JSON.stringify({ phone: value }),
                    });
                    if (!response.ok) {
                        throw new Error(response.status);
                    }
                    apply((await response.json()).points);
                } catch {
                    asked = null;
                    status.textContent = 'Chưa xem được điểm. Chúng tôi sẽ kiểm tra điểm khi xác nhận đơn.';
                }
            };

            phone.addEventListener('input', check);
            check();
        })();
    </script>
    {{-- Nút Lên đầu trang nằm trên nút Hỏi đáp, cả hai trên thanh dưới đáy. --}}
    <style>:root { --back-to-top-offset: 11rem; }</style>
    @include('partials.chatbot', ['chatbotPosition' => 'bottom-28'])
</x-layouts.public>
