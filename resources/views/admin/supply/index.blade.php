<x-layouts.admin title="Sản lượng nông hộ báo">
    <form method="GET" action="{{ route('admin.supply.index') }}" class="mb-4 flex flex-wrap items-end gap-3">
        <div>
            <label for="date" class="mb-1 block text-sm font-medium text-stone-700">Ngày thu hoạch</label>
            <input id="date" type="date" name="date" value="{{ $date }}" class="min-h-11 rounded-lg border border-stone-300 bg-white px-3">
        </div>
        <button class="min-h-11 rounded-lg border border-stone-300 bg-white px-4 hover:bg-stone-100">Xem</button>
    </form>

    @error('supply')
        <div role="alert" class="mb-4 rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-900">{{ $message }}</div>
    @enderror

    @if ($locked)
        <div class="mb-4 rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-900">Kế hoạch ngày này đã chốt, sản lượng chỉ để xem.</div>
    @endif

    <section class="mb-4 rounded-xl border border-stone-200 bg-white p-4">
        <h2 class="mb-2 text-sm font-semibold text-stone-700">Tổng nguồn cung ngày {{ \Carbon\Carbon::parse($date)->format('d/m/Y') }}</h2>
        @if ($totals->isEmpty())
            <p class="text-sm text-stone-500">Chưa có nông hộ nào báo sản lượng cho ngày này.</p>
        @else
            <ul class="flex flex-wrap gap-x-5 gap-y-1 text-sm">
                @foreach ($totals as $productId => $kg)
                    <li>{{ $productNames[$productId] ?? '—' }}: <span class="font-semibold">@kg($kg)</span></li>
                @endforeach
            </ul>
        @endif
    </section>

    <form method="POST" action="{{ route('admin.supply.store') }}" class="space-y-4">
        @csrf
        <input type="hidden" name="date" value="{{ $date }}">

        @foreach ($farmers as $farmer)
            <fieldset class="rounded-xl border border-stone-200 bg-white p-4" @disabled($locked)>
                <legend class="px-1 font-semibold">{{ $farmer->code }} · {{ $farmer->name }}</legend>
                <div class="mb-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm" x-data="{ copied: false }">
                    <span class="text-stone-600">Link báo sản lượng:</span>
                    <code class="break-all rounded bg-stone-100 px-1.5 py-0.5" x-ref="link">{{ route('farmer.show', $farmer->access_token) }}</code>
                    <button type="button" class="rounded-md border border-stone-300 px-2 py-1 hover:bg-stone-100"
                        @click="navigator.clipboard.writeText($refs.link.textContent); copied = true; setTimeout(() => copied = false, 1500)">
                        <span x-show="!copied">Sao chép</span><span x-show="copied" x-cloak>Đã chép</span>
                    </button>
                    <a href="{{ $farmer->zaloLink() }}" target="_blank" rel="noopener" class="text-emerald-800 underline">Mở Zalo</a>
                </div>
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    @forelse ($farmer->products as $product)
                        <label class="block text-sm">
                            <span class="mb-1 block text-stone-700">{{ $product->name }} (kg)</span>
                            <input type="number" step="0.5" min="0" max="500" name="supply[{{ $farmer->id }}][{{ $product->id }}]"
                                value="{{ isset($reports[$farmer->id][$product->id]) ? (float) $reports[$farmer->id][$product->id] : '' }}"
                                class="block min-h-11 w-full rounded-lg border border-stone-300 px-3">
                        </label>
                    @empty
                        <p class="text-sm text-stone-500">Nông hộ này chưa được gắn loại rau nào.</p>
                    @endforelse
                </div>
            </fieldset>
        @endforeach

        @unless ($locked)
            <div class="flex flex-wrap items-end gap-3">
                <div>
                    <label for="source" class="mb-1 block text-sm font-medium text-stone-700">Nguồn thông tin</label>
                    <select id="source" name="source" class="min-h-11 rounded-lg border border-stone-300 bg-white px-3">
                        <option value="zalo">Zalo</option>
                        <option value="operator">Operator nhập</option>
                    </select>
                    @error('source')
                        <p class="mt-1 text-sm text-rose-700">{{ $message }}</p>
                    @enderror
                </div>
                <button class="min-h-11 rounded-lg bg-emerald-700 px-6 font-semibold text-white hover:bg-emerald-800">Lưu</button>
            </div>
        @endunless
    </form>
</x-layouts.admin>
