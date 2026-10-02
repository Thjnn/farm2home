<x-layouts.farmer title="Báo sản lượng">
    <h1 class="text-xl font-semibold">Chào {{ $farmer->name }}</h1>

    <div class="mt-3">
        <x-flash />
        @if ($errors->any() && ! $errors->has('action'))
            <div role="alert" class="mb-4 rounded-lg bg-rose-50 px-4 py-3 text-base text-rose-900">{{ $errors->first() }}</div>
        @endif
    </div>

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
                                <label for="supply-{{ $date }}-{{ $product->id }}" class="min-w-0 flex-1">{{ $product->name }}</label>
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
