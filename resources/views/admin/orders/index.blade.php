<x-layouts.admin title="Đơn hàng">
    <div class="mb-4 flex flex-wrap items-end gap-3">
        <form method="GET" action="{{ route('admin.orders.index') }}" class="flex flex-wrap items-end gap-3">
            <div>
                <label for="delivery_date" class="mb-1 block text-sm font-medium text-stone-700">Ngày giao</label>
                <input id="delivery_date" type="date" name="delivery_date" value="{{ $date }}" class="min-h-11 rounded-lg border border-stone-300 bg-white px-3">
            </div>
            <div>
                <label for="status" class="mb-1 block text-sm font-medium text-stone-700">Trạng thái</label>
                <select id="status" name="status" class="min-h-11 rounded-lg border border-stone-300 bg-white px-3">
                    <option value="">Tất cả</option>
                    @foreach ($statuses as $option)
                        <option value="{{ $option->value }}" @selected($status === $option->value)>{{ $option->label() }}</option>
                    @endforeach
                </select>
            </div>
            <button class="min-h-11 rounded-lg border border-stone-300 bg-white px-4 hover:bg-stone-100">Lọc</button>
        </form>
        <a href="{{ route('admin.orders.create') }}" class="ml-auto inline-flex min-h-11 items-center rounded-lg bg-emerald-700 px-4 font-semibold text-white hover:bg-emerald-800">Tạo đơn</a>
    </div>

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

    @if ($orders->isNotEmpty())
        <form id="bulk-orders" method="POST" action="{{ route('admin.orders.bulk-transition') }}" class="mb-3 flex flex-wrap items-center gap-2 rounded-xl border border-stone-200 bg-white p-3 text-sm">
            @csrf
            @method('PATCH')
            <span class="mr-1 text-stone-700">Đã chọn <span data-bulk-count class="font-semibold">0</span> đơn → chuyển sang</span>
            @foreach ($bulkStatuses as $option)
                <button name="status" value="{{ $option->value }}" class="min-h-11 rounded-lg border border-emerald-700 px-3 font-medium text-emerald-800 hover:bg-emerald-50">{{ $option->label() }}</button>
            @endforeach
            <span class="w-full text-xs text-stone-500">Đơn không ở bước liền trước sẽ được bỏ qua. Hủy đơn thì mở từng đơn để nhập lý do.</span>
        </form>
    @endif

    <div class="overflow-x-auto rounded-xl border border-stone-200 bg-white">
        <table class="w-full min-w-[640px] text-left text-sm">
            <thead class="border-b border-stone-200 bg-stone-50 text-stone-600">
                <tr>
                    <th class="w-10 px-3 py-2"><input type="checkbox" data-bulk-all class="size-5" aria-label="Chọn tất cả đơn"></th>
                    <th class="px-3 py-2 font-medium">Mã đơn</th>
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
                    <tr>
                        <td class="px-3 py-2"><input type="checkbox" name="order_ids[]" value="{{ $order->id }}" form="bulk-orders" data-bulk-item class="size-5" aria-label="Chọn đơn {{ $order->code }}"></td>
                        <td class="px-3 py-2"><a href="{{ route('admin.orders.show', $order) }}" class="font-semibold text-emerald-800 underline">{{ $order->code }}</a></td>
                        <td class="px-3 py-2">{{ $order->time_slot }}</td>
                        <td class="px-3 py-2">{{ $order->customer->name }}</td>
                        <td class="px-3 py-2">{{ $order->zone?->name ?? '—' }}</td>
                        <td class="px-3 py-2">{{ $order->order_type->label() }}</td>
                        <td class="px-3 py-2 text-right">@vnd($order->total)</td>
                        <td class="px-3 py-2"><x-status-badge :status="$order->status" /></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-3 py-6 text-center text-stone-500">Chưa có đơn nào cho ngày này.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <script>
        // Chọn tất cả và đếm số đơn đã chọn cho thanh thao tác hàng loạt.
        (() => {
            const all = document.querySelector('[data-bulk-all]');
            const items = [...document.querySelectorAll('[data-bulk-item]')];
            const count = document.querySelector('[data-bulk-count]');
            const refresh = () => {
                if (count) {
                    count.textContent = items.filter((item) => item.checked).length;
                }
            };

            all?.addEventListener('change', () => {
                items.forEach((item) => { item.checked = all.checked; });
                refresh();
            });
            items.forEach((item) => item.addEventListener('change', refresh));
            refresh();
        })();
    </script>
</x-layouts.admin>
