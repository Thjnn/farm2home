<x-layouts.public title="Tra cứu đơn">
    <h1 class="mb-4 text-xl font-semibold">Tra cứu đơn</h1>

    <form method="POST" action="{{ route('lookup.search') }}" class="space-y-4 rounded-xl border border-stone-200 bg-white p-4">
        @csrf
        <x-field name="phone" label="Số điện thoại đã đặt hàng" type="tel" inputmode="numeric" autocomplete="tel" :value="$phone" required />
        <button class="min-h-12 w-full rounded-lg bg-emerald-700 px-4 font-semibold text-white hover:bg-emerald-800">Tra cứu</button>
    </form>

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
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    @endif
</x-layouts.public>
