<x-layouts.farmer title="Báo sản lượng">
    <h1 class="text-xl font-semibold">Chào {{ $farmer->name }}</h1>

    <div class="mt-3">
        <x-flash />
        @if ($errors->any() && ! $errors->has('action'))
            <div role="alert" class="mb-4 rounded-lg bg-rose-50 px-4 py-3 text-base text-rose-900">{{ $errors->first() }}</div>
        @endif
    </div>

    @if ($harvests->isNotEmpty())
        <section aria-labelledby="harvest-heading" class="mb-6">
            <h2 id="harvest-heading" class="text-lg font-semibold">Danh sách thu hoạch</h2>
            <div class="mt-3 space-y-3">
                @foreach ($harvests as $plan)
                    <article class="rounded-xl border-2 border-emerald-600 bg-white p-4">
                        <h3 class="font-semibold">{{ str($plan->harvest_date->locale('vi')->isoFormat('dddd, DD/MM'))->ucfirst() }}</h3>
                        <ul class="mt-2 space-y-1">
                            @foreach ($plan->items as $item)
                                <li class="flex justify-between gap-3"><span>{{ $item->product->name }}</span><span class="font-semibold">@kg($item->final_kg)</span></li>
                            @endforeach
                        </ul>
                        @if ($plan->items->contains(fn ($item) => $item->farmer_confirmed_at === null))
                            <form method="POST" action="{{ route('farmer.harvest.confirm', [$farmer->access_token, $plan]) }}" class="mt-3">
                                @csrf
                                <button class="min-h-14 w-full rounded-lg bg-emerald-700 px-4 text-lg font-semibold text-white hover:bg-emerald-800">Tôi sẽ thu hoạch</button>
                            </form>
                        @else
                            <p class="mt-3 rounded-lg bg-emerald-50 px-3 py-2 text-center font-medium text-emerald-900">Đã xác nhận</p>
                        @endif
                    </article>
                @endforeach
            </div>
        </section>
    @endif

    @if ($outlooks->isNotEmpty())
        <section aria-labelledby="outlook-heading" class="mb-6 rounded-xl border border-amber-300 bg-amber-50 p-4">
            <h2 id="outlook-heading" class="text-lg font-semibold">Tuần này khách dự kiến cần</h2>
            <ul class="mt-2 space-y-2 text-base">
                @foreach ($products as $product)
                    @continue(! $outlooks->has($product->id))
                    @php($outlook = $outlooks[$product->id])
                    <li>
                        <div class="flex justify-between gap-3"><span>{{ $product->name }}</span><span class="font-semibold">khoảng @kg(round($outlook['total'] * 2) / 2)</span></div>
                        <p class="text-sm text-stone-600">
                            @if ($outlook['peak'])
                                Đông nhất {{ $outlook['peak']['date']->locale('vi')->isoFormat('dddd DD/MM') }}: khoảng @kg(round($outlook['peak']['kg'] * 2) / 2)
                            @else
                                Các ngày khá đều, khoảng @kg(round($outlook['average'] * 2) / 2) mỗi ngày
                            @endif
                        </p>
                    </li>
                @endforeach
            </ul>
            <p class="mt-3 rounded-lg bg-white px-3 py-2 text-base font-medium text-amber-900">
                Đây là tổng số khách cả vùng dự kiến đặt, chia cho nhiều nông hộ. Cô chú dùng để chăm rau và báo sản lượng.
                Chỉ cắt rau theo <strong>số kg FARM2HOME gửi</strong> trong danh sách thu hoạch.
            </p>
        </section>
    @endif

    <section aria-labelledby="supply-heading">
        <h2 id="supply-heading" class="text-lg font-semibold">Báo sản lượng</h2>
        <p class="mt-1 text-base text-stone-700">Nhập số kg có thể thu hoạch mỗi ngày. Để trống nếu không có.</p>

        <form method="POST" action="{{ route('farmer.supply.store', $farmer->access_token) }}" class="mt-4 space-y-4">
            @csrf
            @foreach ($dates as $date)
                <fieldset class="rounded-xl border border-stone-200 bg-white p-4">
                    <legend class="px-1 font-semibold">
                        {{ str(\Carbon\Carbon::parse($date)->locale('vi')->isoFormat('dddd, DD/MM'))->ucfirst() }}
                        @if ($locked[$date])
                            <span class="ml-2 rounded-full bg-stone-200 px-2.5 py-0.5 text-sm font-medium text-stone-700">Đã chốt</span>
                        @endif
                    </legend>
                    <div class="space-y-3">
                        @foreach ($products as $product)
                            @php($reported = isset($reports[$date][$product->id]) ? (float) $reports[$date][$product->id] : null)
                            <div class="flex items-center justify-between gap-3">
                                <label for="supply-{{ $date }}-{{ $product->id }}" class="flex min-w-0 flex-1 items-center gap-3"><x-product-image :product="$product" class="size-12 rounded-lg" />{{ $product->name }}</label>
                                @if ($locked[$date])
                                    <span class="font-semibold">{{ $reported === null ? '—' : \App\Support\Format::kg($reported) }}</span>
                                @else
                                    <div class="flex items-center gap-2">
                                        <input id="supply-{{ $date }}-{{ $product->id }}" type="number" inputmode="decimal" step="0.5" min="0" max="500"
                                            name="supply[{{ $date }}][{{ $product->id }}]" value="{{ old("supply.$date.$product->id", $reported ?? '') }}"
                                            class="h-12 w-24 rounded-lg border border-stone-300 px-3 text-right text-lg">
                                        <span class="text-stone-600">kg</span>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </fieldset>
            @endforeach

            <div class="sticky bottom-0 -mx-4 border-t border-stone-200 bg-stone-50 px-4 py-3">
                <button class="min-h-14 w-full rounded-lg bg-emerald-700 px-4 text-lg font-semibold text-white hover:bg-emerald-800">Lưu sản lượng</button>
            </div>
        </form>
    </section>
</x-layouts.farmer>
