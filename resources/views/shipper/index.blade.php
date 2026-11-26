@php($dateString = $date->toDateString())
@php($open = $orders->where('status', '!=', \App\Enums\OrderStatus::Delivered))
<x-layouts.shipper>
    <div class="mb-3 flex items-center gap-2">
        <a href="{{ route('shipper.index', ['date' => $date->subDay()->toDateString()]) }}" class="grid min-h-11 min-w-11 place-items-center rounded-lg border border-stone-300 bg-white" aria-label="Ngày trước">←</a>
        <h1 class="flex-1 text-center text-lg font-bold">{{ $date->isToday() ? 'Hôm nay' : $date->translatedFormat('l') }} {{ $date->format('d/m') }}</h1>
        <a href="{{ route('shipper.index', ['date' => $date->addDay()->toDateString()]) }}" class="grid min-h-11 min-w-11 place-items-center rounded-lg border border-stone-300 bg-white" aria-label="Ngày sau">→</a>
        <a href="{{ route('shipper.index', ['date' => $dateString]) }}" class="inline-flex min-h-11 items-center rounded-lg border border-stone-300 bg-white px-3 text-sm">Tải lại</a>
    </div>

    <section class="mb-4 grid grid-cols-3 gap-2 text-center">
        <div class="rounded-xl bg-white p-3 shadow-sm"><p class="text-2xl font-bold tabular-nums">{{ $orders->count() }}</p><p class="text-xs text-stone-600">đơn</p></div>
        <div class="rounded-xl bg-white p-3 shadow-sm"><p class="text-2xl font-bold tabular-nums text-emerald-700">{{ $orders->count() - $open->count() }}</p><p class="text-xs text-stone-600">đã giao</p></div>
        <div class="rounded-xl bg-white p-3 shadow-sm"><p class="text-lg font-bold tabular-nums">@vnd($open->sum(fn ($order) => \App\Support\AmountToCollect::for($order)))</p><p class="text-xs text-stone-600">còn phải thu</p></div>
    </section>

    @if ($readyCount > 0)
        <form method="POST" action="{{ route('shipper.pickup') }}" class="mb-4">
            @csrf
            <input type="hidden" name="date" value="{{ $dateString }}">
            <button class="min-h-12 w-full rounded-xl bg-emerald-700 px-4 text-lg font-semibold text-white hover:bg-emerald-800">Nhận hàng đi giao ({{ $readyCount }} đơn)</button>
        </form>
    @endif

    @if ($orders->isEmpty())
        <p class="rounded-xl border border-stone-200 bg-white px-4 py-8 text-center text-stone-500">Chưa có đơn nào giao cho bạn ngày này.</p>
    @endif

    @foreach ($groups as $label => $groupOrders)
        <h2 class="mb-2 mt-5 text-sm font-semibold uppercase tracking-wide text-stone-600">{{ $label }} <span class="font-normal normal-case">({{ $groupOrders->count() }} đơn)</span></h2>

        <div class="space-y-3">
            @foreach ($groupOrders as $order)
                @php($amount = \App\Support\AmountToCollect::for($order))
                @php($done = $order->status === \App\Enums\OrderStatus::Delivered)
                <article id="order-{{ $order->id }}" @class(['scroll-mt-16 rounded-xl border bg-white p-3', 'opacity-60' => $done, 'border-orange-400' => $order->delivery_issue && ! $done, 'border-stone-200' => ! $order->delivery_issue || $done])>
                    <header class="flex flex-wrap items-baseline gap-x-2">
                        <span class="text-lg font-semibold">{{ $order->customer->name }}</span>
                        <span class="font-mono text-xs text-stone-500">{{ $order->code }}</span>
                        <span class="ml-auto text-sm text-stone-600">{{ $bags[$order->id] }} túi</span>
                    </header>
                    <p class="mt-1 text-stone-800">{{ $order->delivery_address }}</p>
                    @if ($order->note)
                        <p class="mt-1 text-sm text-stone-600">Ghi chú: {{ $order->note }}</p>
                    @endif
                    <p @class(['mt-2 font-semibold', 'text-amber-800' => $amount > 0, 'text-emerald-800' => $amount === 0])>
                        @if ($amount > 0)
                            Thu tiền: @vnd($amount)
                        @elseif ($order->payment_method === \App\Enums\PaymentMethod::BankTransfer)
                            Đã chuyển khoản
                        @else
                            Đã thu tiền
                        @endif
                    </p>

                    @if ($order->delivery_issue && ! $done)
                        <p class="mt-2 rounded-lg bg-orange-50 px-3 py-2 text-sm text-orange-900">Đã báo không giao được {{ $order->delivery_issue_at->format('H:i') }}: {{ $order->delivery_issue }}</p>
                    @endif

                    @if ($done)
                        <p class="mt-2 font-semibold text-emerald-800">✓ Đã giao {{ $order->delivered_at?->format('H:i') }}</p>
                    @else
                        <div class="mt-3 grid grid-cols-2 gap-2">
                            <a href="tel:{{ $order->customer->phone }}" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-stone-300 bg-white font-medium">Gọi</a>
                            <a href="https://www.google.com/maps/search/?api=1&amp;query={{ urlencode($order->delivery_address.', '.$order->zone->name) }}" target="_blank" rel="noopener" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-stone-300 bg-white font-medium">Bản đồ</a>
                        </div>

                        @if ($order->status === \App\Enums\OrderStatus::Delivering)
                            <form method="POST" action="{{ route('shipper.delivered', $order) }}" class="mt-2">
                                @csrf
                                @method('PATCH')
                                <button class="min-h-12 w-full rounded-lg bg-emerald-700 text-lg font-semibold text-white hover:bg-emerald-800">✓ Đã giao</button>
                            </form>
                            <details class="mt-2">
                                <summary class="flex min-h-11 cursor-pointer items-center justify-center rounded-lg border border-orange-300 text-orange-900">Không giao được</summary>
                                <form method="POST" action="{{ route('shipper.issue', $order) }}" class="mt-2 space-y-2">
                                    @csrf
                                    @method('PATCH')
                                    @foreach ($reasons as $reason)
                                        <label class="flex min-h-11 items-center gap-3 rounded-lg bg-stone-50 px-3">
                                            <input type="radio" name="reason" value="{{ $reason }}" required class="size-5">
                                            {{ $reason }}
                                        </label>
                                    @endforeach
                                    <input type="text" name="note" maxlength="120" placeholder="Ghi chú (không bắt buộc)" class="min-h-11 w-full rounded-lg border border-stone-300 px-3">
                                    <button class="min-h-11 w-full rounded-lg bg-orange-600 font-semibold text-white hover:bg-orange-700">Gửi báo</button>
                                </form>
                            </details>
                        @else
                            <p class="mt-2 text-center text-sm text-stone-500">{{ $order->status === \App\Enums\OrderStatus::Packing ? 'Đang đóng gói' : 'Chờ đóng gói' }}</p>
                        @endif
                    @endif
                </article>
            @endforeach
        </div>
    @endforeach
</x-layouts.shipper>
