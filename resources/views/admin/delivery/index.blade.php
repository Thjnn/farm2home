@php($dateString = $date->toDateString())
@php($unassigned = $orders->whereNull('shipper_id')->where('status', '!=', \App\Enums\OrderStatus::Delivered)->count())
<x-layouts.admin title="Phân giao">
    <div class="mb-4 flex flex-wrap items-end gap-3">
        <form method="GET" action="{{ route('admin.delivery.index') }}" class="flex flex-wrap items-end gap-3">
            <div>
                <label for="date" class="mb-1 block text-sm font-medium text-stone-700">Ngày giao</label>
                <input id="date" type="date" name="date" value="{{ $dateString }}" class="min-h-11 rounded-lg border border-stone-300 bg-white px-3">
            </div>
            <button class="min-h-11 rounded-lg border border-stone-300 bg-white px-4 hover:bg-stone-100">Xem</button>
        </form>
        <form method="POST" action="{{ route('admin.delivery.rebalance') }}" class="ml-auto">
            @csrf
            <input type="hidden" name="date" value="{{ $dateString }}">
            <button class="min-h-11 rounded-lg border border-emerald-700 bg-white px-4 font-semibold text-emerald-800 hover:bg-emerald-50">Chia lại</button>
        </form>
    </div>
    <p class="mb-4 text-sm text-stone-600">Đơn tự chia khi chuyển sang Đang đóng gói. "Chia lại" và đánh dấu nghỉ chỉ dời đơn còn trong kho; đơn đang giao giữ người đang cầm hàng.</p>

    @if ($shippers->isEmpty())
        <p class="mb-4 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">Chưa có tài khoản người giao. Admin tạo ở trang Tài khoản, vai trò "Giao hàng".</p>
    @endif

    @if ($unassigned > 0)
        <p class="mb-4 rounded-lg border border-red-300 bg-red-50 px-4 py-3 font-semibold text-red-800">Chưa có người giao: {{ $unassigned }} đơn</p>
    @endif

    <section class="mb-6 grid gap-3 sm:grid-cols-2">
        @foreach ($shippers as $shipper)
            @php($mine = $orders->where('shipper_id', $shipper->id))
            @php($isOff = $shipper->off_date?->isSameDay($date))
            <div @class(['rounded-xl border bg-white p-4', 'border-stone-200' => ! $isOff, 'border-stone-300 bg-stone-100' => $isOff])>
                <div class="flex items-center gap-2">
                    <span class="font-semibold">{{ $shipper->name }}</span>
                    @if ($shipper->phone)
                        <span class="text-sm text-stone-500">{{ $shipper->phone }}</span>
                    @endif
                    @if ($isOff)
                        <span class="rounded-full bg-stone-200 px-2 py-0.5 text-xs font-semibold text-stone-700">Nghỉ ngày này</span>
                    @endif
                </div>
                <p class="mt-1 text-sm tabular-nums text-stone-700">{{ $mine->count() }} đơn · đã giao {{ $mine->where('status', \App\Enums\OrderStatus::Delivered)->count() }}</p>
                <p class="text-sm text-stone-600">{{ $mine->pluck('zone.name')->unique()->implode(', ') ?: 'Chưa có cụm' }}</p>
                <form method="POST" action="{{ route('admin.delivery.day-off', $shipper) }}" class="mt-2">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="date" value="{{ $dateString }}">
                    <input type="hidden" name="off" value="{{ $isOff ? 0 : 1 }}">
                    <button class="min-h-11 rounded-lg border border-stone-300 bg-white px-3 text-sm hover:bg-stone-100">{{ $isOff ? 'Đi làm lại' : 'Nghỉ ngày này' }}</button>
                </form>
            </div>
        @endforeach
    </section>

    @if ($orders->isEmpty())
        <p class="rounded-xl border border-stone-200 bg-white px-4 py-8 text-center text-stone-500">Chưa có đơn giao tận nhà cho ngày này.</p>
    @endif

    @foreach ($zones as $zoneId => $zoneOrders)
        @php($zone = $zoneOrders->first()->zone)
        <section class="mb-4 rounded-xl border border-stone-200 bg-white p-4">
            <div class="mb-2 flex flex-wrap items-center gap-3">
                <h2 class="font-semibold">{{ $zone->name }} <span class="font-normal text-stone-500">({{ $zoneOrders->count() }} đơn)</span></h2>
                <form method="POST" action="{{ route('admin.delivery.zone', $zone) }}" class="ml-auto flex items-center gap-2">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="date" value="{{ $dateString }}">
                    <label for="zone-{{ $zone->id }}" class="text-sm text-stone-600">Cả cụm:</label>
                    <select id="zone-{{ $zone->id }}" name="shipper_id" onchange="this.form.submit()" class="min-h-11 rounded-lg border border-stone-300 bg-white px-2 text-sm">
                        <option value="">— chọn —</option>
                        @foreach ($shippers as $shipper)
                            <option value="{{ $shipper->id }}">{{ $shipper->name }}</option>
                        @endforeach
                    </select>
                </form>
            </div>

            <ul class="divide-y divide-stone-100">
                @foreach ($zoneOrders as $order)
                    <li @class(['flex flex-wrap items-center gap-x-3 gap-y-1 py-2 text-sm', 'bg-orange-50 -mx-2 px-2 rounded' => $order->delivery_issue && $order->status !== \App\Enums\OrderStatus::Delivered])>
                        <a href="{{ route('admin.orders.show', $order) }}" class="font-medium text-emerald-800 hover:underline">{{ $order->code }}</a>
                        <span>{{ $order->customer->name }}</span>
                        <span class="text-stone-500">{{ $order->time_slot }} · {{ $order->status->label() }}</span>
                        @if ($order->delivery_issue && $order->status !== \App\Enums\OrderStatus::Delivered)
                            <span class="font-semibold text-orange-800">Không giao được: {{ $order->delivery_issue }}</span>
                        @endif
                        <form method="POST" action="{{ route('admin.delivery.order', $order) }}" class="ml-auto">
                            @csrf
                            @method('PATCH')
                            <select name="shipper_id" onchange="this.form.submit()" aria-label="Người giao {{ $order->code }}" @class(['min-h-11 rounded-lg border bg-white px-2 text-sm', 'border-red-400' => ! $order->shipper_id, 'border-stone-300' => $order->shipper_id])>
                                <option value="">Chưa có người giao</option>
                                @foreach ($shippers as $shipper)
                                    <option value="{{ $shipper->id }}" @selected($order->shipper_id === $shipper->id)>{{ $shipper->name }}</option>
                                @endforeach
                            </select>
                        </form>
                    </li>
                @endforeach
            </ul>
        </section>
    @endforeach
</x-layouts.admin>
