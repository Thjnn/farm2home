@php
    use App\Enums\HarvestPlanStatus;

    $isDraft = $plan->status === HarvestPlanStatus::Draft;
    $canReceive = in_array($plan->status, [HarvestPlanStatus::Confirmed, HarvestPlanStatus::Sent], true) && Route::has('admin.harvest-plan-items.receive');
    $totalSuggested = (float) $plan->items->sum('suggested_kg');
    $withoutSource = $plan->items->whereNull('farmer_id')->count();
    // Chênh lệch dòng HTX: khi nháp = chốt − gợi ý; đã chốt và có số nhận = nhận − chốt.
    $rowDiff = fn ($item) => ! $isDraft && $item->received_kg !== null
        ? (float) $item->received_kg - (float) $item->final_kg
        : (float) ($item->final_kg ?? $item->suggested_kg) - (float) $item->suggested_kg;
    $signedKg = fn (float $kg) => (abs($kg) < 0.05 ? '' : ($kg > 0 ? '+' : '−')).\App\Support\Format::kg(abs($kg));
    // Dư (vàng) / thiếu (đỏ).
    $diffTone = fn (float $kg) => abs($kg) < 0.05 ? 'text-stone-500' : ($kg > 0 ? 'text-amber-800' : 'text-rose-700');
@endphp
<x-layouts.admin :title="'Kế hoạch thu hoạch '.$plan->delivery_date->format('d/m/Y')" back="admin.harvest-plans.index">
    <div class="mb-4 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-stone-700">
        <x-status-badge :status="$plan->status" />
        <span>Biên an toàn {{ (float) $plan->safety_margin_pct }}%</span>
        @if ($plan->creator)
            <span>Tạo bởi {{ $plan->creator->name }}</span>
        @endif
        @if ($plan->confirmer)
            <span>Chốt bởi {{ $plan->confirmer->name }} lúc {{ $plan->confirmed_at?->format('H:i d/m') }}</span>
        @endif
    </div>

    @if ($pendingOrders > 0)
        <div class="mb-4 rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-900">
            Còn {{ $pendingOrders }} đơn chờ xác nhận cho ngày này. Số gợi ý đã tính cả các đơn đó.
        </div>
    @endif

    @if ($staleWarnings !== [])
        <div class="mb-4 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900" role="status">
            <p class="font-semibold">Kiểm tra dữ liệu trước khi chốt</p>
            <ul class="mt-1 list-disc space-y-0.5 pl-5">
                @foreach ($staleWarnings as $warning)
                    <li>{{ $warning }}</li>
                @endforeach
            </ul>
            <p class="mt-1">Kiểm tra lại rồi bấm "Tính lại gợi ý" bên dưới nếu cần.</p>
        </div>
    @endif

    <div x-data="{
            changed: 0,
            totalFinal: 0,
            byProduct: {},
            {{-- Đếm dòng chốt khác gợi ý, tổng kg chốt (cả kế hoạch và từng rau), cập nhật khi gõ. --}}
            recount() {
                const inputs = [...this.$root.querySelectorAll('[data-final]')];
                this.changed = inputs.filter((input) => Number(input.value) !== Number(input.dataset.suggested)).length;
                this.totalFinal = inputs.reduce((sum, input) => sum + (Number(input.value) || 0), 0);
                this.byProduct = inputs.reduce((sums, input) => {
                    sums[input.dataset.product] = (sums[input.dataset.product] ?? 0) + (Number(input.value) || 0);
                    return sums;
                }, {});
            },
            kg(value) { return new Intl.NumberFormat('vi-VN', { maximumFractionDigits: 1 }).format(value) + ' kg'; },
            signed(value) { return (Math.abs(value) < 0.05 ? '' : (value > 0 ? '+' : '−')) + this.kg(Math.abs(value)); },
            tone(value) { return Math.abs(value) < 0.05 ? 'text-stone-500' : (value > 0 ? 'text-amber-800' : 'text-rose-700'); },
        }" x-init="recount()" @input="recount()">

        @if ($isDraft && $plan->items->isNotEmpty())
            {{-- Tóm tắt nhanh trước khi chốt. --}}
            <dl class="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div class="rounded-xl border border-stone-200 bg-white p-3">
                    <dt class="text-xs text-stone-600">Tổng gợi ý</dt>
                    <dd class="text-xl font-bold tabular-nums">@kg($totalSuggested)</dd>
                </div>
                <div class="rounded-xl border border-stone-200 bg-white p-3">
                    <dt class="text-xs text-stone-600">Tổng chốt</dt>
                    <dd class="text-xl font-bold tabular-nums" x-text="kg(totalFinal)">@kg($plan->items->sum(fn ($item) => (float) ($item->final_kg ?? $item->suggested_kg)))</dd>
                </div>
                <div class="rounded-xl border p-3" :class="changed ? 'border-amber-300 bg-amber-50' : 'border-stone-200 bg-white'">
                    <dt class="text-xs text-stone-600">Dòng khác gợi ý</dt>
                    <dd class="text-xl font-bold tabular-nums" x-text="changed">0</dd>
                </div>
                <div @class(['rounded-xl border p-3', 'border-rose-300 bg-rose-50' => $withoutSource > 0, 'border-stone-200 bg-white' => $withoutSource === 0])>
                    <dt class="text-xs text-stone-600">Chưa có nguồn</dt>
                    <dd @class(['text-xl font-bold tabular-nums', 'text-rose-700' => $withoutSource > 0])>{{ $withoutSource }}</dd>
                </div>
            </dl>
            <p class="mb-4 text-sm text-stone-600">
                Phần của mỗi HTX được chia theo tỉ lệ sản lượng họ đã báo, không ai bị giao quá số mình báo.
                Nhà được chia dưới {{ (float) config('farm2home.min_allocation_kg') }} kg thì nhường phần cho nhà khác (nếu các nhà còn lại đủ rau).
                Ô chốt <span class="rounded bg-amber-100 px-1 text-amber-900">tô vàng</span> là khác gợi ý: ghi lý do để sau còn đối chiếu.
            </p>
        @endif

        @if ($isDraft)
            {{-- Nút mặc định khi nhấn Enter: lưu, không phải thêm/xoá dòng. --}}
            <button form="save-all-items" class="sr-only" tabindex="-1" aria-hidden="true">Lưu tất cả</button>
        @endif
        <div class="overflow-x-auto rounded-xl border border-stone-200 bg-white">
            <table class="table-cards w-full {{ $isDraft ? 'min-w-[960px]' : 'min-w-[880px]' }} text-left text-sm">
                <thead class="border-b border-stone-200 bg-stone-50 text-stone-600">
                    <tr>
                        <th class="px-3 py-2 font-medium">Rau</th>
                        <th class="px-3 py-2 font-medium">HTX</th>
                        <th class="whitespace-nowrap px-3 py-2 text-right font-medium">HTX báo</th>
                        <th class="whitespace-nowrap px-3 py-2 text-right font-medium">Gợi ý</th>
                        <th class="px-3 py-2 font-medium">Chốt</th>
                        <th class="whitespace-nowrap px-3 py-2 text-right font-medium">Chênh lệch</th>
                        @unless ($isDraft)
                            <th class="px-3 py-2 font-medium">Thực nhận</th>
                        @endunless
                    </tr>
                </thead>
                @forelse ($plan->items->groupBy('product_id') as $productId => $rows)
                    @php
                        $product = $rows->first()->product;
                        // Số đặt / dự báo / tồn chỉ ghi ở dòng đầu của mỗi rau (HarvestPlanService), nên cộng cả nhóm.
                        $ordered = (float) $rows->sum('confirmed_order_kg');
                        $extra = (float) $rows->sum('forecast_extra_kg');
                        $stock = (float) $rows->sum('available_stock_kg');
                        $sumSuggested = (float) $rows->sum('suggested_kg');
                        $sumFinal = (float) $rows->sum(fn ($item) => (float) ($item->final_kg ?? $item->suggested_kg));
                        $sumReported = (float) $rows->sum(fn ($item) => $reported["{$item->farmer_id}-{$item->product_id}"] ?? 0);
                        $anyReceived = $rows->contains(fn ($item) => $item->received_kg !== null);
                        $sumDiff = (float) $rows->sum(fn ($item) => $rowDiff($item));
                        $imageUrl = $product->imageUrl();
                    @endphp
                    <tbody class="border-t-2 border-stone-200 first-of-type:border-t-0">
                        <tr class="bg-stone-50 align-top">
                            <td class="px-3 py-2" data-label="">
                                <div class="flex items-start gap-3 text-left">
                                    @if ($imageUrl)
                                        <img src="{{ $imageUrl }}" alt="" class="size-10 shrink-0 rounded-lg object-cover" loading="lazy">
                                    @else
                                        <span class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-emerald-100 font-semibold text-emerald-800" aria-hidden="true">{{ mb_substr($product->name, 0, 1) }}</span>
                                    @endif
                                    <div>
                                        <span class="font-semibold text-stone-900">{{ $product->name }}</span>
                                        <span class="block text-xs font-normal text-stone-600">
                                            Đặt @kg($ordered) · Dự báo +@kg($extra) · Tồn @kg($stock) ·
                                            <span class="font-semibold text-stone-800">Cần @kg(max(0, $ordered + $extra - $stock))</span>
                                        </span>
                                        @if ($isDraft)
                                            <button form="save-all-items" name="add_product_id" value="{{ $productId }}" class="mt-1 block min-h-11 text-left text-xs font-medium text-emerald-800 hover:underline md:min-h-8">+ Thêm HTX cho {{ $product->name }}</button>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="px-3 py-2 text-stone-600">{{ $rows->whereNotNull('farmer_id')->count() }} HTX</td>
                            <td class="whitespace-nowrap px-3 py-2 text-right tabular-nums">@kg($sumReported)</td>
                            <td class="whitespace-nowrap px-3 py-2 text-right font-semibold tabular-nums">@kg($sumSuggested)</td>
                            @if ($isDraft)
                                <td class="whitespace-nowrap px-3 py-2 font-semibold tabular-nums" x-text="kg(byProduct[{{ $productId }}] ?? 0)">@kg($sumFinal)</td>
                                <td class="whitespace-nowrap px-3 py-2 text-right font-semibold tabular-nums"
                                    x-text="signed((byProduct[{{ $productId }}] ?? 0) - {{ $sumSuggested }})" :class="tone((byProduct[{{ $productId }}] ?? 0) - {{ $sumSuggested }})">{{ $signedKg($sumDiff) }}</td>
                            @else
                                <td class="whitespace-nowrap px-3 py-2 font-semibold tabular-nums">@kg($sumFinal)</td>
                                <td class="whitespace-nowrap px-3 py-2 text-right font-semibold tabular-nums {{ $diffTone($sumDiff) }}">{{ $signedKg($sumDiff) }}</td>
                                <td class="whitespace-nowrap px-3 py-2 font-semibold tabular-nums">{{ $anyReceived ? \App\Support\Format::kg($rows->sum('received_kg')) : '—' }}</td>
                            @endif
                        </tr>
                        @foreach ($rows as $item)
                            @php($reportedKg = $reported["{$item->farmer_id}-{$item->product_id}"] ?? null)
                            <tr class="align-top" @if ($isDraft) x-data="{ final: {{ (float) old("items.{$item->id}.final_kg", $item->final_kg ?? $item->suggested_kg) }}, suggested: {{ (float) $item->suggested_kg }}, reason: @js((string) old("items.{$item->id}.adjust_reason", $item->adjust_reason)) }" @endif>
                                <td class="px-3 py-2 max-md:hidden" data-label=""></td>
                                <td class="px-3 py-2">
                                    @if ($item->farmer)
                                        {{ $item->farmer->name }}
                                        @isset($unusual["{$item->farmer_id}-{$item->product_id}"])
                                            @php($odd = $unusual["{$item->farmer_id}-{$item->product_id}"])
                                            <span class="block text-xs text-amber-800">⚠ Báo @kg($odd['kg']), thường khoảng @kg($odd['usual_kg'])/ngày. Hỏi lại trước khi chốt.</span>
                                        @endisset
                                    @else
                                        <span class="font-semibold text-rose-700">Chưa có nguồn</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-3 py-2 text-right tabular-nums">{{ $reportedKg !== null ? \App\Support\Format::kg($reportedKg) : '—' }}</td>
                                <td class="whitespace-nowrap px-3 py-2 text-right tabular-nums">@kg($item->suggested_kg)</td>
                                @if ($isDraft)
                                    <td class="px-3 py-2">
                                        <div class="flex w-full flex-wrap items-center gap-2">
                                            <input type="number" step="0.5" min="0" form="save-all-items" name="items[{{ $item->id }}][final_kg]" x-model.number="final" data-final data-product="{{ $item->product_id }}" data-suggested="{{ (float) $item->suggested_kg }}"
                                                value="{{ (float) old("items.{$item->id}.final_kg", $item->final_kg ?? $item->suggested_kg) }}" aria-label="Số kg chốt cho {{ $item->product->name }}"
                                                :class="final !== suggested ? 'border-amber-400 bg-amber-50 font-semibold text-amber-900' : 'border-stone-300'"
                                                class="h-11 w-24 rounded-lg border px-2 text-right tabular-nums">
                                            <input form="save-all-items" name="items[{{ $item->id }}][adjust_reason]" value="{{ old("items.{$item->id}.adjust_reason", $item->adjust_reason) }}" x-model="reason" placeholder="Lý do nếu khác gợi ý" aria-label="Lý do điều chỉnh"
                                                :class="final !== suggested && ! reason ? 'border-amber-400' : 'border-stone-300'"
                                                class="h-11 min-w-28 flex-1 rounded-lg border px-2">
                                            <select form="save-all-items" name="items[{{ $item->id }}][farmer_id]" aria-label="HTX" class="h-11 w-full rounded-lg border border-stone-300 bg-white px-2 md:w-44">
                                                @unless ($item->farmer_id)
                                                    <option value="">— chọn HTX —</option>
                                                @endunless
                                                {{-- Chỉ nhà đã đăng ký trồng loại rau này (giữ nhà đang được chia dù chưa đăng ký). --}}
                                                @foreach ($allFarmers->filter(fn ($farmer) => $farmer->id === $item->farmer_id || $farmer->products->contains('id', $item->product_id)) as $farmer)
                                                    <option value="{{ $farmer->id }}" @selected((int) old("items.{$item->id}.farmer_id", $item->farmer_id) === $farmer->id)>{{ $farmer->code }} · {{ $farmer->name }}</option>
                                                @endforeach
                                            </select>
                                            @if ((float) $item->suggested_kg === 0.0)
                                                <button form="save-all-items" name="remove_item_id" value="{{ $item->id }}" class="h-11 rounded-lg px-3 text-rose-700 hover:bg-rose-50">Xoá</button>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-2 text-right tabular-nums" x-text="signed(final - suggested)" :class="tone(final - suggested)">{{ $signedKg($rowDiff($item)) }}</td>
                                @else
                                    <td class="px-3 py-2">
                                        <span class="font-semibold tabular-nums">@kg($item->final_kg)</span>
                                        @if ($item->adjust_reason)
                                            <span class="block text-xs text-stone-600">{{ $item->adjust_reason }}</span>
                                        @endif
                                        <span class="block text-xs {{ $item->farmer_confirmed_at ? 'text-emerald-800' : 'text-stone-500' }}">
                                            {{ $item->farmer_confirmed_at ? 'HTX xác nhận '.$item->farmer_confirmed_at->format('H:i d/m') : 'Chưa xác nhận' }}
                                        </span>
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-2 text-right tabular-nums {{ $diffTone($rowDiff($item)) }}">
                                        {{ $signedKg($rowDiff($item)) }}
                                        <span class="block text-xs font-normal text-stone-500">{{ $item->received_kg !== null ? 'nhận − chốt' : 'chốt − gợi ý' }}</span>
                                    </td>
                                    <td class="px-3 py-2">
                                        <div class="w-full">
                                            @if ($item->batch)
                                                <span class="font-semibold">@kg($item->received_kg)</span>
                                                <a href="{{ route('admin.batches.show', $item->batch) }}" class="block text-xs text-emerald-800 underline">{{ $item->batch->code }}</a>
                                            @elseif ($item->received_kg !== null)
                                                @kg($item->received_kg)
                                            @endif
                                            @if ($canReceive && $item->farmer_id)
                                                <form method="POST" action="{{ route('admin.harvest-plan-items.receive', $item) }}" class="mt-1 flex flex-wrap items-center justify-end gap-2 md:justify-start">
                                                    @csrf
                                                    <input type="number" step="0.1" min="0" name="received_kg" value="{{ (float) ($item->received_kg ?? $item->final_kg) }}" aria-label="Số kg thực nhận {{ $item->product->name }}"
                                                        class="h-11 w-20 rounded-lg border border-stone-300 px-2 text-right">
                                                    <input type="text" name="grower_name" value="{{ $item->batch?->grower_name }}" maxlength="150" placeholder="Hộ trồng (nếu HTX khai)" aria-label="Hộ trồng {{ $item->product->name }} (nếu HTX khai)"
                                                        class="h-11 w-44 rounded-lg border border-stone-300 px-2">
                                                    <button class="h-11 rounded-lg border border-stone-300 px-3 hover:bg-stone-100">{{ $item->batch ? 'Sửa' : 'Nhận' }}</button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                @empty
                    <tbody><tr><td colspan="7" class="px-3 py-6 text-center text-stone-500">Không có nhu cầu nào cho ngày này.</td></tr></tbody>
                @endforelse
            </table>
        </div>
        @if ($errors->has('items.*'))
            <p class="mt-2 text-sm text-rose-700">{{ $errors->first('items.*') }}</p>
        @endif
        @error('received_kg')
            <p class="mt-2 text-sm text-rose-700">{{ $message }}</p>
        @enderror

        {{-- Nút Lưu / Tính lại / Chốt luôn ở đáy màn hình, không phải cuộn xuống cuối bảng. --}}
        @if ($isDraft || in_array($plan->status, [HarvestPlanStatus::Confirmed, HarvestPlanStatus::Sent], true))
        <x-action-bar>
            @if ($isDraft)
                <p class="mr-auto hidden text-sm text-stone-600 sm:block" aria-live="polite">
                    Chốt <strong class="tabular-nums text-stone-900" x-text="kg(totalFinal)"></strong>
                    <span x-show="changed" x-cloak>· <span class="font-semibold text-amber-800" x-text="changed + ' dòng khác gợi ý'"></span></span>
                </p>
                @if ($plan->items->isNotEmpty())
                    <form id="save-all-items" method="POST" action="{{ route('admin.harvest-plans.items.update', $plan) }}">
                        @csrf
                        @method('PATCH')
                        <button class="min-h-11 rounded-lg border border-emerald-700 bg-white px-4 font-semibold text-emerald-800 hover:bg-emerald-50">Lưu tất cả</button>
                    </form>
                @endif
                <form method="POST" action="{{ route('admin.harvest-plans.generate') }}">
                    @csrf
                    <input type="hidden" name="delivery_date" value="{{ $plan->delivery_date->toDateString() }}">
                    <button class="min-h-11 rounded-lg border border-stone-300 bg-white px-4 hover:bg-stone-100">Tính lại gợi ý</button>
                </form>
                <form method="POST" action="{{ route('admin.harvest-plans.confirm', $plan) }}">
                    @csrf
                    <button class="min-h-11 rounded-lg bg-emerald-700 px-4 font-semibold text-white hover:bg-emerald-800">Chốt kế hoạch</button>
                </form>
            @endif
            @if ($plan->status === HarvestPlanStatus::Confirmed)
                <form method="POST" action="{{ route('admin.harvest-plans.sent', $plan) }}">
                    @csrf
                    <button class="min-h-11 rounded-lg bg-emerald-700 px-4 font-semibold text-white hover:bg-emerald-800">Đánh dấu đã gửi</button>
                </form>
            @endif
            @if (in_array($plan->status, [HarvestPlanStatus::Confirmed, HarvestPlanStatus::Sent], true))
                <form method="POST" action="{{ route('admin.harvest-plans.complete', $plan) }}">
                    @csrf
                    <button class="min-h-11 rounded-lg border border-stone-300 bg-white px-4 hover:bg-stone-100">Hoàn tất kế hoạch</button>
                </form>
            @endif
        </x-action-bar>
        @endif
    </div>

    @if ($messages->isNotEmpty())
        <section class="mt-6">
            <h2 class="mb-2 font-semibold">Tin nhắn cho HTX</h2>
            <div class="grid gap-3 md:grid-cols-2">
                @foreach ($farmers as $farmer)
                    <article class="rounded-xl border border-stone-200 bg-white p-4" x-data="{ copied: false }">
                        <h3 class="font-medium">{{ $farmer->code }} · {{ $farmer->name }}</h3>
                        <textarea readonly rows="6" x-ref="message" aria-label="Tin nhắn cho {{ $farmer->name }}"
                            class="mt-2 block w-full rounded-lg border border-stone-300 bg-stone-50 p-2 text-sm">{{ $messages[$farmer->id] }}</textarea>
                        <div class="mt-2 flex flex-wrap gap-2">
                            <button type="button" class="min-h-11 rounded-lg border border-stone-300 px-3 hover:bg-stone-100"
                                @click="copyText($refs.message.value).then(() => { copied = true; setTimeout(() => copied = false, 1500) })">
                                <span x-show="!copied">Sao chép</span><span x-show="copied" x-cloak>Đã chép</span>
                            </button>
                            <a href="{{ $farmer->zaloLink() }}" target="_blank" rel="noopener" class="inline-flex min-h-11 items-center rounded-lg border border-stone-300 px-3 hover:bg-stone-100">Mở Zalo</a>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>
    @endif
</x-layouts.admin>
