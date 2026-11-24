<x-layouts.public title="Rau theo nhu cầu" wide>
    <section class="mb-8 max-w-2xl">
        <h1 class="text-2xl font-bold leading-tight tracking-tight text-stone-900 sm:text-3xl">Điều phối nông sản theo nhu cầu, giảm dư thừa.</h1>
        <p class="mt-3 text-stone-700">
            Bạn đặt rau trước, hợp tác xã rau ở Gò Công và vùng lân cận thu hoạch vừa đủ theo đơn.
            Rau được sơ chế, đóng gói và giao trong ngày, mỗi gói có mã QR để xem thông tin lô hàng.
        </p>
        <div class="mt-5 flex flex-wrap gap-3">
            <a href="{{ route('orders.create') }}" class="inline-flex min-h-12 items-center rounded-lg bg-emerald-700 px-6 font-semibold text-white hover:bg-emerald-800">Đặt hàng</a>
            @if (Route::has('lookup.show'))
                <a href="{{ route('lookup.show') }}" class="inline-flex min-h-12 items-center rounded-lg border border-stone-300 bg-white px-6 font-medium hover:bg-stone-100">Tra cứu đơn</a>
            @endif
        </div>
    </section>

    @if ($products['total'] > 0)
        <section aria-labelledby="products-heading" class="mb-10"
            x-data="pagedList({ endpoint: @js(route('api.products')), key: 'trang', initial: @js($products) })">
            <div class="mb-3 flex items-center justify-between gap-3">
                <h2 id="products-heading" x-ref="heading" class="scroll-mt-4 text-lg font-semibold">Rau đang có</h2>
                @if (Route::has('products.index'))
                    <a href="{{ route('products.index') }}" class="inline-flex min-h-11 items-center px-1 font-medium text-emerald-800 hover:underline">Xem tất cả <span aria-hidden="true">&nbsp;→</span></a>
                @endif
            </div>
            <x-product-grid :skeletons="count($products['items'])" />
            <x-pagination label="Trang rau đang có" :total-pages="$products['totalPages']" />
        </section>
    @endif

    <section aria-labelledby="baskets-heading" class="mb-10"
        x-data="pagedList({ endpoint: @js(route('api.baskets')), key: 'trang_gio', initial: @js($baskets) })">
        <h2 id="baskets-heading" x-ref="heading" class="mb-3 scroll-mt-4 text-lg font-semibold">Giỏ rau</h2>
        @if ($baskets['total'] === 0)
            <p class="text-stone-600">Hiện chưa có giỏ rau nào đang mở bán.</p>
        @endif
        <div class="grid gap-3 sm:grid-cols-2" :aria-busy="loading">
            @foreach ($baskets['items'] as $basket)
                <div x-show="loading" aria-hidden="true" class="h-52 animate-pulse rounded-xl border border-stone-200 bg-white"></div>
            @endforeach
            <template x-for="basket in items" :key="basket.id">
                <article x-show="!loading" data-card class="flex min-h-52 flex-col rounded-xl border border-stone-200 bg-white p-4">
                    <div class="flex items-baseline justify-between gap-3">
                        <h3 class="font-semibold" x-text="basket.name"></h3>
                        <p class="shrink-0 font-semibold text-emerald-800" x-text="money(basket.price)"></p>
                    </div>
                    <p x-show="basket.description" class="mt-1 text-stone-600" x-text="basket.description"></p>
                    <ul class="mt-2 flex flex-wrap gap-x-3 gap-y-1.5 text-stone-700">
                        <template x-for="(line, index) in basket.items" :key="index">
                            <li class="flex items-center gap-1.5">
                                <img x-show="line.imageUrl" :src="line.imageUrl" alt="" loading="lazy" width="24" height="24" class="size-6 rounded-full bg-stone-100 object-cover">
                                <span x-text="line.quantity + ' × ' + line.name"></span>
                            </li>
                        </template>
                    </ul>
                    <div class="mt-auto pt-3">
                        <a :href="@js(route('orders.create')) + '?goi=' + basket.id"
                            class="inline-flex min-h-11 items-center justify-center rounded-lg border border-emerald-700 px-4 font-semibold text-emerald-800 hover:bg-emerald-50">Đặt giỏ này</a>
                    </div>
                </article>
            </template>
        </div>
        <x-pagination label="Trang giỏ rau" :total-pages="$baskets['totalPages']" />
    </section>

    <section class="max-w-2xl rounded-xl bg-emerald-50 p-4 text-emerald-950">
        <h2 class="font-semibold">Cách hoạt động</h2>
        <ol class="mt-2 list-decimal space-y-1 pl-5">
            <li>Bạn đặt giỏ rau hoặc chọn lẻ trước ngày giao.</li>
            <li>Chúng tôi tổng hợp đơn và báo hợp tác xã số lượng cần thu hoạch.</li>
            <li>Rau được giao theo khung giờ bạn chọn, kèm mã QR của lô hàng.</li>
        </ol>
    </section>
</x-layouts.public>
