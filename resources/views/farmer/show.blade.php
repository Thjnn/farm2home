<x-layouts.farmer title="Báo sản lượng">
    @php
        $dayLabel = fn (string $date, string $format) => str(\Carbon\Carbon::parse($date)->locale('vi')->isoFormat($format))->ucfirst();
        // Ngày mở sẵn: ngày đầu tiên còn mở và chưa báo, nếu không có thì ngày đầu.
        $firstOpenDay = collect($dates)->first(fn ($date) => ! $locked[$date] && empty($reports[$date])) ?? $dates[0];
    @endphp

    <div class="mb-5">
        <p class="text-base text-stone-600">Xin chào</p>
        <h1 class="text-2xl font-bold tracking-tight">{{ $farmer->name }}</h1>
    </div>

    <div>
        <x-flash />
        @if ($errors->any() && ! $errors->has('action'))
            <div role="alert" class="mb-4 rounded-xl bg-rose-50 px-4 py-3 text-base text-rose-900">{{ $errors->first() }}</div>
        @endif
    </div>

    @if ($harvests->isNotEmpty())
        <section aria-labelledby="harvest-heading" class="mb-6">
            <h2 id="harvest-heading" class="mb-3 text-xl font-semibold">Danh sách thu hoạch</h2>
            <div class="space-y-3">
                @foreach ($harvests as $plan)
                    @php($pending = $plan->items->contains(fn ($item) => $item->farmer_confirmed_at === null))
                    <article class="overflow-hidden rounded-2xl border-2 {{ $pending ? 'border-emerald-600' : 'border-stone-200' }} bg-white">
                        <div class="flex items-center justify-between gap-3 px-4 pt-4">
                            <h3 class="font-semibold">{{ $dayLabel($plan->harvest_date->toDateString(), 'dddd, DD/MM') }}</h3>
                            @unless ($pending)
                                <span class="rounded-full bg-emerald-100 px-3 py-1 text-sm font-semibold text-emerald-900">✓ Đã xác nhận</span>
                            @endunless
                        </div>
                        <ul class="mt-2 divide-y divide-stone-100 px-4">
                            @foreach ($plan->items as $item)
                                <li class="flex items-center justify-between gap-3 py-2.5">
                                    <span class="flex items-center gap-3"><x-product-image :product="$item->product" class="size-10 rounded-lg" />{{ $item->product->name }}</span>
                                    <span class="text-xl font-bold tabular-nums">@kg($item->final_kg)</span>
                                </li>
                            @endforeach
                        </ul>
                        @if ($pending)
                            <form method="POST" action="{{ route('farmer.harvest.confirm', [$farmer->access_token, $plan]) }}" class="p-4 pt-2">
                                @csrf
                                <button class="min-h-14 w-full rounded-xl bg-emerald-700 px-4 text-lg font-semibold text-white hover:bg-emerald-800 active:scale-[0.98] motion-reduce:active:scale-100">Tôi sẽ thu hoạch</button>
                            </form>
                        @else
                            <div class="h-3"></div>
                        @endif
                    </article>
                @endforeach
            </div>
        </section>
    @endif

    @if ($placedOrders !== [])
        {{-- Bảng gọn: mỗi hàng một loại rau, mỗi cột một ngày (đơn thật, không phải dự báo). --}}
        <section aria-labelledby="placed-heading" class="mb-6 rounded-2xl border border-amber-300 bg-amber-50 p-4">
            <h2 id="placed-heading" class="text-xl font-semibold">Đơn khách đã đặt {{ config('farm2home.htx_orders_days_ahead') }} ngày tới</h2>
            <table class="mt-3 w-full text-base">
                <thead>
                    <tr class="text-left text-sm text-stone-600">
                        <th scope="col" class="pb-1 font-medium"><span class="sr-only">Loại rau</span></th>
                        @foreach (array_keys($placedOrders) as $date)
                            <th scope="col" class="pb-1 text-right font-medium">{{ $dayLabel($date, 'ddd') }}<br><span class="font-semibold text-stone-800">{{ \Carbon\Carbon::parse($date)->format('d/m') }}</span></th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-amber-200/70">
                    @foreach ($products as $product)
                        @continue(! collect($placedOrders)->contains(fn ($byProduct) => isset($byProduct[$product->id])))
                        <tr>
                            <th scope="row" class="py-2 pr-2 text-left font-normal">{{ $product->name }}</th>
                            @foreach ($placedOrders as $byProduct)
                                <td class="py-2 text-right font-semibold tabular-nums">{{ isset($byProduct[$product->id]) ? \App\Support\Format::kg($byProduct[$product->id]) : '—' }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <p class="mt-3 rounded-xl bg-white px-3 py-2 text-base text-amber-950">
                Đây là đơn thật khách cả vùng đã đặt, FARM2HOME chia cho nhiều HTX; khách còn đặt thêm được tới {{ config('farm2home.order.cutoff_hour') }}:00 hôm trước.
                Chỉ cắt rau theo <strong>số kg FARM2HOME gửi</strong> trong danh sách thu hoạch.
            </p>
        </section>
    @endif

    <section aria-labelledby="supply-heading" class="mb-6"
        x-data="{
            day: @js($firstOpenDay),
            dirty: false,
            copyFrom(previous) {
                const source = this.$root.querySelector(`[data-day='${previous}']`);
                this.$root.querySelectorAll(`[data-day='${this.day}'] input[data-product]`).forEach((input) => {
                    const from = source.querySelector(`[data-product='${input.dataset.product}']`);
                    const value = from ? (from.value ?? from.dataset.kg ?? '') : '';
                    if (value !== '') { input.value = value; this.dirty = true; }
                });
            },
        }"
        @beforeunload.window="if (dirty) { $event.preventDefault(); $event.returnValue = ''; }">
        <h2 id="supply-heading" class="text-xl font-semibold">Báo sản lượng</h2>
        <p class="mt-1 text-base text-stone-700">Chọn ngày, nhập số kg có thể thu hoạch. Để trống nếu không có.</p>

        <form method="POST" action="{{ route('farmer.supply.store', $farmer->access_token) }}" class="mt-4" @input="dirty = true" @submit="dirty = false">
            @csrf

            {{-- Chọn ngày: mỗi lần chỉ hiện 1 ngày; nút Lưu vẫn gửi tất cả các ngày. --}}
            <div class="-mx-4 mb-4 flex gap-2 overflow-x-auto px-4 pb-1 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden" role="tablist" aria-label="Ngày báo sản lượng">
                @foreach ($dates as $date)
                    @php($status = $locked[$date] ? 'Đã chốt' : (empty($reports[$date]) ? 'Chưa báo' : 'Đã báo'))
                    <button type="button" role="tab" @click="day = @js($date)" :aria-selected="day === @js($date)" aria-controls="day-{{ $date }}"
                        :class="day === @js($date) ? 'border-emerald-700 bg-emerald-700 text-white' : 'border-stone-300 bg-white text-stone-900'"
                        class="flex min-h-16 min-w-[5.5rem] shrink-0 flex-col items-center justify-center rounded-2xl border px-3 leading-tight transition-colors">
                        <span class="text-sm">{{ $dayLabel($date, 'dddd') }}</span>
                        <span class="text-lg font-bold">{{ \Carbon\Carbon::parse($date)->format('d/m') }}</span>
                        <span class="text-xs font-medium" :class="day === @js($date) ? 'text-emerald-50' : '{{ $status === 'Chưa báo' ? 'text-amber-700' : 'text-stone-500' }}'">{{ $status }}</span>
                    </button>
                @endforeach
            </div>

            @foreach ($dates as $index => $date)
                <div id="day-{{ $date }}" data-day="{{ $date }}" role="tabpanel" x-show="day === @js($date)" @if ($date !== $firstOpenDay) x-cloak @endif
                    class="rounded-2xl border border-stone-200 bg-white p-4">
                    <div class="mb-1 flex flex-wrap items-center justify-between gap-2">
                        <h3 class="font-semibold">{{ $dayLabel($date, 'dddd, DD/MM') }}</h3>
                        @if ($locked[$date])
                            <span class="rounded-full bg-stone-200 px-3 py-1 text-sm font-medium text-stone-700">Đã chốt, không sửa được</span>
                        @elseif ($index > 0)
                            <button type="button" @click="copyFrom(@js($dates[$index - 1]))"
                                class="inline-flex min-h-11 items-center rounded-lg px-2 text-base font-medium text-emerald-800 hover:bg-emerald-50">
                                Lấy số của {{ $dayLabel($dates[$index - 1], 'dddd') }}
                            </button>
                        @endif
                    </div>
                    <div class="divide-y divide-stone-100">
                        @foreach ($products as $product)
                            @php($reported = isset($reports[$date][$product->id]) ? (float) $reports[$date][$product->id] : null)
                            <div class="flex items-center justify-between gap-3 py-2.5">
                                <label for="supply-{{ $date }}-{{ $product->id }}" class="flex min-w-0 flex-1 items-center gap-2.5">
                                    <x-product-image :product="$product" class="size-10 rounded-lg" />
                                    <span class="truncate">{{ $product->name }}</span>
                                </label>
                                @if ($locked[$date])
                                    <span data-product="{{ $product->id }}" data-kg="{{ $reported ?? '' }}" class="text-lg font-semibold tabular-nums">{{ $reported === null ? '—' : \App\Support\Format::kg($reported) }}</span>
                                @else
                                    <div class="flex items-center gap-2">
                                        <input id="supply-{{ $date }}-{{ $product->id }}" data-product="{{ $product->id }}" type="number" inputmode="decimal" step="0.5" min="0" max="500"
                                            name="supply[{{ $date }}][{{ $product->id }}]" value="{{ old("supply.$date.$product->id", $reported ?? '') }}" placeholder="0"
                                            class="h-14 w-24 rounded-xl border border-stone-300 px-3 text-right text-xl font-semibold tabular-nums placeholder:font-normal placeholder:text-stone-300 focus:border-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-700/20">
                                        <span class="text-stone-600">kg</span>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach

            {{-- Chừa chỗ cho thanh Lưu dưới đáy. --}}
            <div class="h-24" aria-hidden="true"></div>
            <div class="fixed inset-x-0 bottom-0 z-30 border-t border-stone-200 bg-white/95 pb-[env(safe-area-inset-bottom)] shadow-[0_-4px_16px_rgb(0_0_0/0.06)] backdrop-blur">
                <div class="mx-auto flex max-w-xl items-center gap-3 px-4 py-3">
                    <p class="min-w-0 flex-1 text-base leading-tight" aria-live="polite">
                        <span x-show="dirty" x-cloak class="font-semibold text-amber-700">Có thay đổi chưa lưu</span>
                        <span x-show="!dirty" class="text-stone-600">Báo trước để kịp lên kế hoạch</span>
                    </p>
                    <button class="min-h-14 shrink-0 rounded-xl bg-emerald-700 px-6 text-lg font-semibold text-white hover:bg-emerald-800 active:scale-[0.98] motion-reduce:active:scale-100">Lưu sản lượng</button>
                </div>
            </div>
        </form>
    </section>


    <section aria-labelledby="rating-heading" class="mb-4 rounded-2xl border border-stone-200 bg-white p-4">
        <h2 id="rating-heading" class="text-xl font-semibold">Khách đánh giá rau của cô chú</h2>
        @if ($rating['count'] === 0)
            <p class="mt-1 text-base text-stone-700">Chưa có lượt đánh giá nào.</p>
        @else
            <p class="mt-2 text-4xl font-bold">{{ number_format($rating['rating'], 1, ',', '.') }}<span class="text-amber-500">★</span>
                <span class="text-base font-normal text-stone-600">({{ $rating['count'] }} lượt đánh giá)</span>
            </p>
            @if ($rating['freshness'] !== null)
                <p class="text-base text-stone-700">Độ tươi: <span class="font-semibold">{{ number_format($rating['freshness'], 1, ',', '.') }}★</span></p>
            @endif
            @if ($recentComments->isNotEmpty())
                <ul class="mt-3 space-y-2">
                    @foreach ($recentComments as $feedback)
                        <li class="rounded-xl bg-stone-50 px-3 py-2 text-base">
                            <p class="text-sm text-stone-600">{{ $feedback->batch->product->name }} · {{ $feedback->rating }}★ · {{ $feedback->created_at->format('d/m') }}</p>
                            <p>“{{ $feedback->comment }}”</p>
                        </li>
                    @endforeach
                </ul>
            @endif
        @endif
    </section>

    @if ($farmer->maskedBankAccount())
        <p class="text-base text-stone-700">Tài khoản nhận tiền: <span class="font-semibold">{{ $farmer->maskedBankAccount() }}</span>. Sai số thì cô chú báo FARM2HOME qua Zalo.</p>
    @endif

    {{-- Nút Lên đầu trang nằm trên thanh Lưu. --}}
    <style>:root { --back-to-top-offset: 6.5rem; }</style>
</x-layouts.farmer>
