<x-layouts.public title="Đơn {{ $order->code }}">
    <x-flash />

    <section class="rounded-xl border border-stone-200 bg-white p-5">
        <div class="flex items-center justify-between gap-3">
            <h1 class="text-xl font-semibold">Đơn {{ $order->code }}</h1>
            <x-status-badge :status="$order->status" />
        </div>
        <p class="mt-1 text-sm text-stone-600">Giao {{ $order->delivery_date->format('d/m/Y') }} · {{ $order->time_slot }}</p>

        @if ($basket = $order->items->firstWhere('basket_id', '!=', null)?->basket)
            <p class="mt-4 font-medium">{{ $basket->name }}</p>
        @endif
        <ul class="mt-2 space-y-1 text-sm">
            @foreach ($order->items as $item)
                <li class="flex justify-between gap-4">
                    <span>{{ $item->product->name }} × {{ $item->quantity }}@if ($item->is_gift) <span class="ml-1 rounded bg-amber-100 px-1.5 py-0.5 text-xs font-medium text-amber-900">Quà đổi điểm</span>@endif</span>
                    @if ($item->line_total > 0)
                        <span>@vnd($item->line_total)</span>
                    @endif
                </li>
            @endforeach
        </ul>

        <dl class="mt-4 space-y-1 border-t border-stone-100 pt-3 text-sm">
            <div class="flex justify-between gap-4"><dt class="text-stone-600">Tiền hàng</dt><dd>@vnd($order->subtotal)</dd></div>
            <div class="flex justify-between gap-4"><dt class="text-stone-600">Phí giao</dt><dd>@vnd($order->shipping_fee)</dd></div>
            @if ($order->discount > 0)
                <div class="flex justify-between gap-4"><dt class="text-stone-600">Giảm</dt><dd>−@vnd($order->discount)</dd></div>
            @endif
            <div class="flex justify-between gap-4 text-base"><dt class="font-semibold">Tổng tiền</dt><dd class="font-semibold">@vnd($order->total)</dd></div>
            <div class="flex justify-between gap-4"><dt class="text-stone-600">Thanh toán</dt><dd>{{ $order->payment_method->label() }} · {{ $order->payment_status->label() }}</dd></div>
        </dl>
    </section>

    @if ($order->status === \App\Enums\OrderStatus::Cancelled)
        <section class="mt-4 rounded-xl border border-stone-200 bg-stone-50 p-5 text-sm">
            <h2 class="font-semibold">Đơn đã hủy</h2>
            @if ($order->cancel_reason)
                <p class="mt-1 text-stone-700">Lý do: {{ $order->cancel_reason }}</p>
            @endif
        </section>
    @elseif ($blockReason)
        <section class="mt-4 rounded-xl border border-stone-200 bg-stone-50 p-5 text-sm">
            <p class="text-stone-700">{{ $blockReason }}</p>
            @if ($operatorPhone)
                <a href="https://zalo.me/{{ $operatorPhone }}" class="mt-3 inline-flex min-h-11 items-center rounded-lg border border-stone-300 bg-white px-4 font-medium hover:bg-stone-100">Nhắn Zalo FARM2HOME</a>
            @endif
        </section>
    @else
        <section class="mt-4 rounded-xl border border-rose-200 bg-white p-5"
            x-data="{ reason: @js(old('reason', '')), confirming: false }">
            <h2 class="font-semibold">Hủy đơn (trước {{ $deadline->format('G') }}:00 ngày {{ $deadline->format('d/m') }})</h2>
            <form method="POST" action="{{ route('orders.public.cancel', [$order->code, $order->access_key]) }}" class="mt-3 space-y-3">
                @csrf
                <div class="flex flex-wrap gap-2">
                    @foreach (['Đổi lịch', 'Đặt nhầm', 'Không cần nữa'] as $quick)
                        <button type="button" @click="reason = @js($quick)"
                            class="inline-flex min-h-11 items-center rounded-lg border border-stone-300 px-3 text-sm hover:bg-stone-100"
                            :class="reason === @js($quick) && 'border-rose-400 bg-rose-50'">{{ $quick }}</button>
                    @endforeach
                    <button type="button" @click="reason = ''; $nextTick(() => $refs.reason.focus())"
                        class="inline-flex min-h-11 items-center rounded-lg border border-stone-300 px-3 text-sm hover:bg-stone-100">Khác</button>
                </div>
                <div>
                    <label for="reason" class="block text-sm text-stone-700">Lý do hủy</label>
                    <textarea id="reason" name="reason" rows="2" maxlength="200" x-model="reason" x-ref="reason"
                        class="mt-1 block w-full rounded-lg border border-stone-300 px-3 py-2">{{ old('reason') }}</textarea>
                    @error('reason')
                        <p class="mt-1 text-sm text-rose-700">{{ $message }}</p>
                    @enderror
                </div>
                <button type="button" x-show="!confirming" @click="confirming = true"
                    class="inline-flex min-h-11 items-center rounded-lg bg-rose-600 px-4 font-medium text-white hover:bg-rose-700">Hủy đơn này</button>
                <div x-show="confirming" x-cloak class="flex flex-wrap items-center gap-3">
                    <span class="text-sm font-medium">Chắc chắn hủy?</span>
                    <button type="submit" class="inline-flex min-h-11 items-center rounded-lg bg-rose-600 px-4 font-medium text-white hover:bg-rose-700">Có, hủy</button>
                    <button type="button" @click="confirming = false" class="inline-flex min-h-11 items-center rounded-lg border border-stone-300 bg-white px-4 font-medium hover:bg-stone-100">Không</button>
                </div>
            </form>
        </section>
    @endif
</x-layouts.public>
