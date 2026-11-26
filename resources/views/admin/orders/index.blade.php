@php
    $day = \Carbon\Carbon::parse($date);
    $chip = fn (bool $active) => $active
        ? 'border-emerald-700 bg-emerald-700 text-white'
        : 'border-stone-300 bg-white text-stone-700 hover:bg-stone-100';
@endphp
<x-layouts.admin title="Đơn hàng">
    <x-slot:actions>
        <a href="{{ route('admin.orders.create') }}" class="inline-flex min-h-11 items-center rounded-lg bg-emerald-700 px-4 font-semibold text-white hover:bg-emerald-800">Tạo đơn</a>
    </x-slot:actions>

    {{-- Ngày giao: đổi ngày là lọc luôn; ‹ › nhảy ngày trước / sau, giữ trạng thái đang lọc. --}}
    <form method="GET" action="{{ route('admin.orders.index') }}" class="mb-3 flex flex-wrap items-end gap-2">
        <div>
            <label for="delivery_date" class="mb-1 block text-sm font-medium text-stone-700">Ngày giao</label>
            <div class="flex items-center gap-1">
                <a href="{{ route('admin.orders.index', array_filter(['delivery_date' => $day->copy()->subDay()->toDateString(), 'status' => $status])) }}"
                    class="inline-flex size-11 items-center justify-center rounded-lg border border-stone-300 bg-white hover:bg-stone-100" aria-label="Ngày trước">‹</a>
                <input id="delivery_date" type="date" name="delivery_date" value="{{ $date }}" onchange="this.form.requestSubmit()" class="min-h-11 rounded-lg border border-stone-300 bg-white px-3">
                <a href="{{ route('admin.orders.index', array_filter(['delivery_date' => $day->copy()->addDay()->toDateString(), 'status' => $status])) }}"
                    class="inline-flex size-11 items-center justify-center rounded-lg border border-stone-300 bg-white hover:bg-stone-100" aria-label="Ngày sau">›</a>
            </div>
        </div>
        @if ($status)
            <input type="hidden" name="status" value="{{ $status }}">
        @endif
        @unless ($day->isToday())
            <a href="{{ route('admin.orders.index', array_filter(['status' => $status])) }}" class="inline-flex min-h-11 items-center rounded-lg px-3 font-medium text-emerald-800 hover:bg-emerald-50">Về hôm nay</a>
        @endunless
        <noscript><button class="min-h-11 rounded-lg border border-stone-300 bg-white px-4">Xem</button></noscript>
    </form>

    {{-- Tìm đơn theo SĐT / mã đơn / tên khách, mọi ngày giao. --}}
    <form method="GET" action="{{ route('admin.orders.index') }}" role="search" class="mb-4 flex gap-2">
        <label for="order-search" class="sr-only">Tìm đơn</label>
        <input id="order-search" type="search" name="q" value="{{ $search }}" maxlength="50" placeholder="SĐT, mã đơn hoặc tên khách"
            class="min-h-11 w-full max-w-md rounded-lg border border-stone-300 bg-white px-3">
        <button class="min-h-11 shrink-0 rounded-lg border border-emerald-700 px-4 font-medium text-emerald-800 hover:bg-emerald-50">Tìm</button>
    </form>

    @if ($search)
        <p class="mb-4 text-sm text-stone-700">
            Kết quả cho "<span class="font-semibold">{{ $search }}</span>" ({{ $orders->count() }} đơn, mới nhất trước) ·
            <a href="{{ route('admin.orders.index', ['delivery_date' => $date]) }}" class="font-medium text-emerald-800 underline">Xoá tìm</a>
        </p>
    @else
        {{-- Lọc theo trạng thái, kèm số đơn trong ngày. --}}
        <nav aria-label="Lọc theo trạng thái" class="-mx-4 mb-4 flex gap-2 overflow-x-auto px-4 pb-1 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
            <a href="{{ route('admin.orders.index', ['delivery_date' => $date]) }}" @if (! $status) aria-current="page" @endif
                class="inline-flex min-h-11 shrink-0 items-center gap-1.5 whitespace-nowrap rounded-full border px-4 text-sm font-medium {{ $chip(! $status) }}">
                Tất cả <span class="tabular-nums opacity-80">{{ $statusCounts->sum() }}</span>
            </a>
            @foreach ($statuses as $option)
                @php($count = $statusCounts[$option->value] ?? 0)
                <a href="{{ route('admin.orders.index', ['delivery_date' => $date, 'status' => $option->value]) }}" @if ($status === $option->value) aria-current="page" @endif
                    @class(['inline-flex min-h-11 shrink-0 items-center gap-1.5 whitespace-nowrap rounded-full border px-4 text-sm font-medium', $chip($status === $option->value), 'opacity-60' => $count === 0 && $status !== $option->value])>
                    {{ $option->label() }} <span class="tabular-nums opacity-80">{{ $count }}</span>
                </a>
            @endforeach
        </nav>

        @if ($demand->isNotEmpty())
            <section class="mb-4 rounded-xl border border-stone-200 bg-white p-4">
                <h2 class="mb-2 text-sm font-semibold text-stone-700">Nhu cầu trong ngày (đơn chưa hủy)</h2>
                <ul class="flex flex-wrap gap-x-5 gap-y-1 text-sm">
                    @foreach ($demand as $row)
                        <li>{{ $row->name }}: <span class="font-semibold">@kg($row->kg)</span></li>
                    @endforeach
                </ul>
            </section>
        @endif

    @endif

    <div x-data="{ selected: 0, count() { this.selected = this.$root.querySelectorAll('[data-bulk-item]:checked').length } }" @change="count()">
        <div class="overflow-x-auto rounded-xl border border-stone-200 bg-white">
            <table class="table-cards w-full min-w-[640px] text-left text-sm">
                <thead class="border-b border-stone-200 bg-stone-50 text-stone-600">
                    <tr>
                        <th class="w-10 px-3 py-2"><input type="checkbox" data-bulk-all class="size-5 accent-emerald-700" aria-label="Chọn tất cả đơn"
                            @change="$root.querySelectorAll('[data-bulk-item]').forEach((item) => item.checked = $event.target.checked)"></th>
                        <th class="px-3 py-2 font-medium">Mã đơn</th>
                        @if ($search)
                            <th class="px-3 py-2 font-medium">Ngày giao</th>
                        @endif
                        <th class="px-3 py-2 font-medium">Khung giờ</th>
                        <th class="px-3 py-2 font-medium">Khách</th>
                        <th class="px-3 py-2 font-medium">Khu vực</th>
                        <th class="px-3 py-2 font-medium">Loại</th>
                        <th class="px-3 py-2 text-right font-medium">Tổng</th>
                        <th class="px-3 py-2 font-medium">Trạng thái</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100">
                    @forelse ($orders as $order)
                        <tr class="hover:bg-stone-50 has-checked:bg-emerald-50/60">
                            <td class="px-3 py-2"><input type="checkbox" name="order_ids[]" value="{{ $order->id }}" form="bulk-orders" data-bulk-item class="size-5 accent-emerald-700" aria-label="Chọn đơn {{ $order->code }}"></td>
                            <td class="px-3 py-2"><a href="{{ route('admin.orders.show', $order) }}" class="font-semibold text-emerald-800 underline">{{ $order->code }}</a></td>
                            @if ($search)
                                <td class="px-3 py-2 tabular-nums">{{ $order->delivery_date->format('d/m/Y') }}</td>
                            @endif
                            <td class="px-3 py-2">{{ $order->time_slot }}</td>
                            <td class="px-3 py-2">{{ $order->customer->name }}</td>
                            <td class="px-3 py-2">{{ $order->zone?->name ?? '—' }}</td>
                            <td class="px-3 py-2">{{ $order->order_type->label() }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">@vnd($order->total)</td>
                            <td class="px-3 py-2"><x-status-badge :status="$order->status" /></td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="px-3 py-10 text-center text-stone-500">
                            @if ($search)
                                Không tìm thấy đơn nào khớp "{{ $search }}".
                            @else
                                Chưa có đơn nào{{ $status ? ' ở trạng thái này' : '' }} cho ngày {{ $day->format('d/m') }}.
                            @endif
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Thao tác hàng loạt: chỉ hiện khi đã chọn đơn, dính đáy màn hình. --}}
        @if ($orders->isNotEmpty())
            <form id="bulk-orders" method="POST" action="{{ route('admin.orders.bulk-transition') }}" x-show="selected > 0" x-cloak
                x-transition:enter="transition duration-150 motion-reduce:transition-none" x-transition:enter-start="translate-y-2 opacity-0"
                class="sticky bottom-[max(0.75rem,env(safe-area-inset-bottom))] z-20 mt-4 rounded-xl border border-stone-200 bg-white/95 px-4 py-3 text-sm shadow-[0_4px_20px_rgb(0_0_0/0.08)] backdrop-blur">
                @csrf
                @method('PATCH')
                <div class="flex flex-wrap items-center gap-2">
                    <span class="mr-1 text-stone-700">Đã chọn <span data-bulk-count class="font-semibold" x-text="selected">0</span> đơn → chuyển sang</span>
                    @foreach ($bulkStatuses as $option)
                        <button name="status" value="{{ $option->value }}" class="min-h-11 rounded-lg border border-emerald-700 px-3 font-medium text-emerald-800 hover:bg-emerald-50">{{ $option->label() }}</button>
                    @endforeach
                </div>
                <p class="mt-1 text-xs text-stone-500">Đơn không ở bước liền trước sẽ được bỏ qua. Hủy đơn thì mở từng đơn để nhập lý do.</p>
            </form>
        @endif
    </div>
</x-layouts.admin>
