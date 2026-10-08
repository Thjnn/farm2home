<x-layouts.public title="Rau theo nhu cầu">
    <section class="mb-8">
        <h1 class="text-2xl font-bold leading-tight tracking-tight text-stone-900">Điều phối nông sản theo nhu cầu, giảm dư thừa.</h1>
        <p class="mt-3 text-stone-700">
            Bạn đặt rau trước, nông hộ ở Thân Cửu Nghĩa và Gò Công thu hoạch vừa đủ theo đơn.
            Rau được sơ chế, đóng gói và giao trong ngày, mỗi gói có mã QR để xem thông tin lô hàng.
        </p>
        <div class="mt-5 flex flex-wrap gap-3">
            <a href="{{ route('orders.create') }}" class="inline-flex min-h-12 items-center rounded-lg bg-emerald-700 px-6 font-semibold text-white hover:bg-emerald-800">Đặt hàng</a>
            @if (Route::has('lookup.show'))
                <a href="{{ route('lookup.show') }}" class="inline-flex min-h-12 items-center rounded-lg border border-stone-300 bg-white px-6 font-medium hover:bg-stone-100">Tra cứu đơn</a>
            @endif
        </div>
    </section>

    @if ($products->isNotEmpty())
        <section aria-labelledby="products-heading" class="mb-8">
            <h2 id="products-heading" class="mb-3 text-lg font-semibold">Rau đang có</h2>
            <ul class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                @foreach ($products as $product)
                    <li class="overflow-hidden rounded-xl border border-stone-200 bg-white">
                        <x-product-image :product="$product" class="aspect-[4/3] w-full" />
                        <div class="p-3">
                            <p class="font-semibold">{{ $product->name }}</p>
                            <p class="text-sm text-stone-600">@vnd($product->price) / @kg($product->pack_weight_kg)</p>
                        </div>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    <section aria-labelledby="baskets-heading">
        <h2 id="baskets-heading" class="mb-3 text-lg font-semibold">Giỏ rau</h2>
        <div class="space-y-3">
            @forelse ($baskets as $basket)
                <article class="rounded-xl border border-stone-200 bg-white p-4">
                    <div class="flex items-baseline justify-between gap-3">
                        <h3 class="font-semibold">{{ $basket->name }}</h3>
                        <p class="font-semibold text-emerald-800">@vnd($basket->price)</p>
                    </div>
                    @if ($basket->description)
                        <p class="mt-1 text-sm text-stone-600">{{ $basket->description }}</p>
                    @endif
                    <ul class="mt-2 flex flex-wrap gap-x-3 gap-y-1.5 text-sm text-stone-700">
                        @foreach ($basket->items as $item)
                            <li class="flex items-center gap-1.5"><x-product-image :product="$item->product" class="size-6 rounded-full" />{{ $item->quantity }} × {{ $item->product->name }}</li>
                        @endforeach
                    </ul>
                </article>
            @empty
                <p class="text-stone-600">Hiện chưa có giỏ rau nào đang mở bán.</p>
            @endforelse
        </div>
    </section>

    <section class="mt-8 rounded-xl bg-emerald-50 p-4 text-sm text-emerald-950">
        <h2 class="font-semibold">Cách hoạt động</h2>
        <ol class="mt-2 list-decimal space-y-1 pl-5">
            <li>Bạn đặt giỏ rau hoặc chọn lẻ trước ngày giao.</li>
            <li>Chúng tôi tổng hợp đơn và báo nông hộ số lượng cần thu hoạch.</li>
            <li>Rau được giao theo khung giờ bạn chọn, kèm mã QR của lô hàng.</li>
        </ol>
    </section>
</x-layouts.public>
