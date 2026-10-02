<x-layouts.public title="Đã nhận đơn">
    <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-5">
        <h1 class="text-xl font-semibold text-emerald-900">Đã nhận đơn của bạn</h1>
        <p class="mt-1 text-sm text-emerald-900">Chúng tôi sẽ liên hệ qua số điện thoại bạn đã để lại để xác nhận.</p>

        <dl class="mt-4 space-y-2 text-sm">
            <div class="flex justify-between gap-4"><dt class="text-stone-600">Mã đơn</dt><dd class="font-semibold">{{ $order->code }}</dd></div>
            <div class="flex justify-between gap-4"><dt class="text-stone-600">Ngày giao</dt><dd>{{ $order->delivery_date->format('d/m/Y') }} · {{ $order->time_slot }}</dd></div>
            <div class="flex justify-between gap-4"><dt class="text-stone-600">Thanh toán</dt><dd>{{ $order->payment_method->label() }}</dd></div>
            <div class="flex justify-between gap-4 border-t border-emerald-200 pt-2 text-base"><dt class="font-semibold">Tổng tiền</dt><dd class="font-semibold">@vnd($order->total)</dd></div>
        </dl>

        @if ($order->payment_method === \App\Enums\PaymentMethod::BankTransfer)
            <p class="mt-3 text-sm text-emerald-900">Chúng tôi sẽ gửi thông tin chuyển khoản qua Zalo.</p>
        @endif
    </div>

    <div class="mt-5 flex flex-wrap gap-3">
        @if (Route::has('lookup.show'))
            <a href="{{ route('lookup.show') }}" class="inline-flex min-h-11 items-center rounded-lg border border-stone-300 bg-white px-4 font-medium hover:bg-stone-100">Tra cứu đơn</a>
        @endif
        <a href="{{ route('orders.create') }}" class="inline-flex min-h-11 items-center rounded-lg border border-stone-300 bg-white px-4 font-medium hover:bg-stone-100">Đặt thêm</a>
    </div>
</x-layouts.public>
