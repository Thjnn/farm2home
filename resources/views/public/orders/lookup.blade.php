<x-layouts.public title="Tra cứu đơn">
    <h1 class="mb-4 text-xl font-semibold">Tra cứu đơn</h1>

    <form method="POST" action="{{ route('lookup.search') }}" class="space-y-4 rounded-xl border border-stone-200 bg-white p-4">
        @csrf
        <x-field name="phone" label="Số điện thoại đã đặt hàng" type="tel" inputmode="numeric" autocomplete="tel" :value="$phone" required />
        <button class="min-h-12 w-full rounded-lg bg-emerald-700 px-4 font-semibold text-white hover:bg-emerald-800">Tra cứu</button>
    </form>

    @if ($points !== null)
        <section class="mt-6 rounded-xl border border-emerald-200 bg-emerald-50 p-4" aria-live="polite">
            <h2 class="text-base font-semibold text-emerald-900">Bạn đang có {{ $points }} điểm</h2>
            <p class="mt-1 text-sm text-emerald-900">Mỗi đơn từ @vnd(config('farm2home.loyalty.min_order_subtotal')) giao xong được +{{ config('farm2home.loyalty.order_points') }} điểm. Trả lại bao bì được +{{ config('farm2home.loyalty.packaging_points') }} điểm.</p>
            <ul class="mt-3 space-y-1 text-sm">
                @foreach ($rewards as $reward)
                    <li class="flex items-center justify-between gap-3">
                        <span>{{ $reward->label }}</span>
                        <span @class(['shrink-0 font-medium', 'text-emerald-800' => $points >= $reward->points, 'text-stone-500' => $points < $reward->points])>{{ $reward->points }} điểm{{ $points >= $reward->points ? ' · đủ điểm' : '' }}</span>
                    </li>
                @endforeach
            </ul>
            <p class="mt-3 text-xs text-stone-600">Muốn đổi quà, hãy chọn quà khi đặt đơn kế tiếp. Chúng tôi xác nhận cùng đơn hàng.</p>
        </section>
    @endif

    @if ($orders !== null)
        <section class="mt-6" aria-live="polite">
            @if ($orders->isEmpty())
                <p class="rounded-lg bg-stone-100 px-4 py-3 text-stone-700">Không tìm thấy đơn nào với số điện thoại này.</p>
            @else
                <h2 class="mb-2 text-sm font-medium text-stone-600">{{ $orders->count() }} đơn gần nhất</h2>
                <ul class="space-y-2">
                    @foreach ($orders as $order)
                        <li class="rounded-xl border border-stone-200 bg-white p-4">
                            <div class="flex items-center justify-between gap-3">
                                <span class="font-semibold">{{ $order->code }}</span>
                                <x-status-badge :status="$order->status" />
                            </div>
                            <div class="mt-1 flex items-center justify-between gap-3 text-sm text-stone-600">
                                <span>{{ $order->delivery_date->format('d/m/Y') }} · {{ $order->time_slot }}</span>
                                <span class="font-medium text-stone-900">@vnd($order->total)</span>
                            </div>
                            @if ($order->feedbackBatches()->isNotEmpty())
                                <div class="mt-3 border-t border-stone-100 pt-3">
                                    <p class="text-sm text-stone-600">Xem nguồn gốc và đánh giá rau trong đơn:</p>
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        @foreach ($order->feedbackBatches() as $batch)
                                            <a href="{{ $batch->publicUrl() }}" class="inline-flex min-h-11 items-center rounded-lg border border-emerald-300 px-3 text-sm font-medium text-emerald-800 hover:bg-emerald-50">Đánh giá {{ $batch->product->name }}</a>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    @endif
</x-layouts.public>
