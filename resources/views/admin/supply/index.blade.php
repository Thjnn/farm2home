<x-layouts.admin title="Sản lượng HTX báo">
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

    @php($parsed = session('parsed'))
    <section id="ghi-san-luong" class="mb-4 scroll-mt-6 rounded-xl border border-emerald-200 bg-emerald-50 p-4">
        <h2 class="mb-1 font-semibold">Dán tin nhắn Zalo của HTX</h2>
        <p class="mb-3 text-sm text-stone-700">Máy đọc tin (ví dụ "mai có 30 ký cải ngọt") thành các dòng sản lượng. Chưa lưu gì cho tới khi bạn xem lại và bấm Xác nhận.</p>
        <form method="POST" action="{{ route('admin.supply.read', ['date' => $date]) }}" class="space-y-3">
            @csrf
            <div class="flex flex-wrap gap-3">
                <label class="block text-sm">
                    <span class="mb-1 block text-stone-700">HTX gửi tin</span>
                    <select name="farmer_id" class="min-h-11 rounded-lg border border-stone-300 bg-white px-3">
                        @foreach ($farmers->where('status', \App\Enums\FarmerStatus::Active) as $farmer)
                            <option value="{{ $farmer->id }}" @selected(old('farmer_id', $parsed['farmer_id'] ?? null) == $farmer->id)>{{ $farmer->code }} · {{ $farmer->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block min-w-64 flex-1 text-sm">
                    <span class="mb-1 block text-stone-700">Nội dung tin nhắn</span>
                    <textarea name="message" rows="2" maxlength="1000" required class="block w-full rounded-lg border border-stone-300 px-3 py-2" placeholder="mai có 30 ký cải ngọt, 20 ký rau muống">{{ old('message', $parsed['message'] ?? '') }}</textarea>
                </label>
            </div>
            @error('message')
                <p class="text-sm text-rose-700">{{ $message }}</p>
            @enderror
            <button class="min-h-11 rounded-lg bg-emerald-700 px-5 font-semibold text-white hover:bg-emerald-800">Đọc tin</button>
        </form>

        @if ($parsed)
            <div class="mt-4 rounded-lg border border-stone-200 bg-white p-3">
                <p class="mb-2 text-sm text-stone-600">
                    {{ $parsed['method'] === 'ai' ? 'AI đã đọc tin.' : 'Không gọi được AI, máy đọc bằng cách dò từ khóa.' }}
                    Dòng có ⚠ cần bạn sửa hoặc hỏi lại HTX trước khi lưu.
                </p>
                @if ($parsed['items'] === [])
                    <p class="text-sm font-medium text-amber-800">⚠ Không đọc được dòng sản lượng nào. Hãy hỏi lại HTX hoặc nhập tay bên dưới.</p>
                @else
                    <form method="POST" action="{{ route('admin.supply.confirm-message') }}" class="space-y-2">
                        @csrf
                        <input type="hidden" name="farmer_id" value="{{ $parsed['farmer_id'] }}">
                        @foreach ($parsed['items'] as $i => $item)
                            <div @class(['flex flex-wrap items-end gap-2 rounded-lg p-2 text-sm', 'bg-amber-50' => $item['reason']])>
                                <input type="hidden" name="rows[{{ $i }}][keep]" value="0">
                                <label class="flex min-h-11 items-center gap-2">
                                    <input type="checkbox" name="rows[{{ $i }}][keep]" value="1" @checked($item['reason'] === null) class="size-5">
                                    <span>Lưu</span>
                                </label>
                                <label class="block">
                                    <span class="mb-1 block text-stone-700">Loại rau</span>
                                    <select name="rows[{{ $i }}][product_id]" class="h-11 rounded-lg border border-stone-300 bg-white px-2">
                                        <option value="">— chọn —</option>
                                        @foreach ($allProducts as $product)
                                            <option value="{{ $product->id }}" @selected($item['product_id'] === $product->id)>{{ $product->name }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label class="block">
                                    <span class="mb-1 block text-stone-700">Số kg</span>
                                    <input type="number" step="0.5" min="0.5" max="{{ \App\Services\SupplyMessageService::MAX_KG }}" name="rows[{{ $i }}][kg]" value="{{ $item['kg'] }}" class="h-11 w-24 rounded-lg border border-stone-300 px-2 text-right">
                                </label>
                                <label class="block">
                                    <span class="mb-1 block text-stone-700">Ngày có hàng</span>
                                    <input type="date" name="rows[{{ $i }}][date]" value="{{ $item['date'] }}" class="h-11 rounded-lg border border-stone-300 px-2">
                                </label>
                                @if ($item['reason'])
                                    <span class="min-h-11 py-3 font-medium text-amber-800">⚠ {{ $item['reason'] }}</span>
                                @endif
                            </div>
                        @endforeach
                        @error('rows.*')
                            <p class="text-sm text-rose-700">{{ $message }}</p>
                        @enderror
                        <button class="min-h-11 rounded-lg bg-emerald-700 px-5 font-semibold text-white hover:bg-emerald-800">Xác nhận lưu</button>
                    </form>
                @endif
            </div>
        @endif
    </section>

    <section class="mb-4 rounded-xl border border-stone-200 bg-white p-4">
        <h2 class="mb-2 text-sm font-semibold text-stone-700">Tổng nguồn cung ngày {{ \Carbon\Carbon::parse($date)->format('d/m/Y') }}</h2>
        @if ($totals->isEmpty())
            <p class="text-sm text-stone-500">Chưa có HTX nào báo sản lượng cho ngày này.</p>
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
                        @click="copyText($refs.link.textContent).then(() => { copied = true; setTimeout(() => copied = false, 1500) })">
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
                                class="block min-h-11 w-full rounded-lg border {{ isset($unusual["{$farmer->id}-{$product->id}"]) ? 'border-amber-500 bg-amber-50' : 'border-stone-300' }} px-3">
                            @isset($unusual["{$farmer->id}-{$product->id}"])
                                <span class="mt-1 block text-amber-800">⚠ Cao bất thường: thường khoảng @kg($unusual["{$farmer->id}-{$product->id}"]['usual_kg'])/ngày. Gọi hỏi lại HTX.</span>
                            @endisset
                        </label>
                    @empty
                        <p class="text-sm text-stone-500">HTX này chưa được gắn loại rau nào.</p>
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
