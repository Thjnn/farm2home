@php
    use App\Enums\HarvestPlanStatus;

    $isDraft = $plan->status === HarvestPlanStatus::Draft;
    $canReceive = in_array($plan->status, [HarvestPlanStatus::Confirmed, HarvestPlanStatus::Sent], true) && Route::has('admin.harvest-plan-items.receive');
@endphp
<x-layouts.admin :title="'Kế hoạch thu hoạch '.$plan->delivery_date->format('d/m/Y')">
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

    @if ($isDraft)
        <p class="mb-4 text-sm text-stone-600">
            Phần của mỗi nông hộ được chia theo tỉ lệ sản lượng họ đã báo, không ai bị giao quá số mình báo.
            Nhà được chia dưới {{ (float) config('farm2home.min_allocation_kg') }} kg thì nhường phần cho nhà khác (nếu các nhà còn lại đủ rau).
        </p>
    @endif

    @if ($pendingOrders > 0)
        <div class="mb-4 rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-900">
            Còn {{ $pendingOrders }} đơn chờ xác nhận cho ngày này. Số gợi ý đã tính cả các đơn đó.
        </div>
    @endif

    <div class="overflow-x-auto rounded-xl border border-stone-200 bg-white">
        <table class="w-full min-w-[900px] text-left text-sm">
            <thead class="border-b border-stone-200 bg-stone-50 text-stone-600">
                <tr>
                    <th class="px-3 py-2 font-medium">Loại rau</th>
                    <th class="px-3 py-2 font-medium">Nông hộ</th>
                    <th class="px-3 py-2 text-right font-medium">Đã đặt</th>
                    <th class="px-3 py-2 text-right font-medium">Dự báo thêm</th>
                    <th class="px-3 py-2 text-right font-medium">Tồn</th>
                    <th class="px-3 py-2 text-right font-medium">Gợi ý</th>
                    <th class="px-3 py-2 font-medium">Chốt</th>
                    <th class="px-3 py-2 font-medium">{{ $isDraft ? '' : 'Thực nhận' }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @forelse ($plan->items as $item)
                    <tr class="align-top">
                        <td class="px-3 py-2 font-medium">{{ $item->product->name }}</td>
                        <td class="px-3 py-2">
                            @if ($item->farmer)
                                {{ $item->farmer->name }}
                            @else
                                <span class="font-semibold text-rose-700">Chưa có nguồn</span>
                            @endif
                        </td>
                        <td class="px-3 py-2 text-right">@kg($item->confirmed_order_kg)</td>
                        <td class="px-3 py-2 text-right">@kg($item->forecast_extra_kg)</td>
                        <td class="px-3 py-2 text-right">@kg($item->available_stock_kg)</td>
                        <td class="px-3 py-2 text-right font-semibold">@kg($item->suggested_kg)</td>
                        @if ($isDraft)
                            <td class="px-3 py-2" colspan="2">
                                <form method="POST" action="{{ route('admin.harvest-plan-items.update', $item) }}" class="flex flex-wrap items-center gap-2">
                                    @csrf
                                    @method('PATCH')
                                    <input type="number" step="0.5" min="0" name="final_kg" value="{{ (float) ($item->final_kg ?? $item->suggested_kg) }}" aria-label="Số kg chốt cho {{ $item->product->name }}"
                                        class="h-11 w-20 rounded-lg border border-stone-300 px-2 text-right">
                                    <input name="adjust_reason" value="{{ $item->adjust_reason }}" placeholder="Lý do nếu khác gợi ý" aria-label="Lý do điều chỉnh"
                                        class="h-11 min-w-40 flex-1 rounded-lg border border-stone-300 px-2">
                                    <select name="farmer_id" aria-label="Nông hộ" class="h-11 rounded-lg border border-stone-300 bg-white px-2">
                                        @unless ($item->farmer_id)
                                            <option value="">— chọn nông hộ —</option>
                                        @endunless
                                        @foreach ($allFarmers as $farmer)
                                            <option value="{{ $farmer->id }}" @selected($item->farmer_id === $farmer->id)>{{ $farmer->code }} · {{ $farmer->name }}</option>
                                        @endforeach
                                    </select>
                                    <button class="h-11 rounded-lg border border-stone-300 px-3 hover:bg-stone-100">Lưu</button>
                                </form>
                            </td>
                        @else
                            <td class="px-3 py-2">
                                <span class="font-semibold">@kg($item->final_kg)</span>
                                @if ($item->adjust_reason)
                                    <span class="block text-xs text-stone-600">{{ $item->adjust_reason }}</span>
                                @endif
                                <span class="block text-xs {{ $item->farmer_confirmed_at ? 'text-emerald-800' : 'text-stone-500' }}">
                                    {{ $item->farmer_confirmed_at ? 'Nông hộ xác nhận '.$item->farmer_confirmed_at->format('H:i d/m') : 'Chưa xác nhận' }}
                                </span>
                            </td>
                            <td class="px-3 py-2">
                                @if ($item->batch)
                                    <span class="font-semibold">@kg($item->received_kg)</span>
                                    <a href="{{ route('admin.batches.show', $item->batch) }}" class="block text-xs text-emerald-800 underline">{{ $item->batch->code }}</a>
                                @elseif ($item->received_kg !== null)
                                    @kg($item->received_kg)
                                @endif
                                @if ($canReceive && $item->farmer_id)
                                    <form method="POST" action="{{ route('admin.harvest-plan-items.receive', $item) }}" class="mt-1 flex items-center gap-2">
                                        @csrf
                                        <input type="number" step="0.1" min="0" name="received_kg" value="{{ (float) ($item->received_kg ?? $item->final_kg) }}" aria-label="Số kg thực nhận {{ $item->product->name }}"
                                            class="h-11 w-20 rounded-lg border border-stone-300 px-2 text-right">
                                        <button class="h-11 rounded-lg border border-stone-300 px-3 hover:bg-stone-100">{{ $item->batch ? 'Sửa' : 'Nhận' }}</button>
                                    </form>
                                @endif
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-3 py-6 text-center text-stone-500">Không có nhu cầu nào cho ngày này.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @error('final_kg')
        <p class="mt-2 text-sm text-rose-700">{{ $message }}</p>
    @enderror
    @error('received_kg')
        <p class="mt-2 text-sm text-rose-700">{{ $message }}</p>
    @enderror

    <div class="mt-4 flex flex-wrap gap-3">
        @if ($isDraft)
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
    </div>

    @if ($messages->isNotEmpty())
        <section class="mt-6">
            <h2 class="mb-2 font-semibold">Tin nhắn cho nông hộ</h2>
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
