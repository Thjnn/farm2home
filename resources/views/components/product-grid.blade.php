@props(['skeletons' => 6])
{{--
    Lưới thẻ rau, vẽ bằng Alpine từ `items` của pagedList(...).
    Khung chờ hiện sẵn trước khi Alpine chạy (giữ chỗ, không nhảy layout) và khi đang tải trang khác.
--}}
<ul {{ $attributes->class('grid grid-cols-2 gap-3 md:grid-cols-3') }} :aria-busy="loading">
    @for ($i = 0; $i < $skeletons; $i++)
        <li x-show="loading" aria-hidden="true" class="overflow-hidden rounded-xl border border-stone-200 bg-white">
            <div class="aspect-[4/3] w-full animate-pulse bg-stone-100"></div>
            <div class="space-y-2 p-3"><div class="h-5 w-3/4 animate-pulse rounded bg-stone-100"></div><div class="h-5 w-1/2 animate-pulse rounded bg-stone-100"></div></div>
        </li>
    @endfor
    <template x-for="product in items" :key="product.id">
        <li x-show="!loading" data-card class="group overflow-hidden rounded-xl border border-stone-200 bg-white transition hover:-translate-y-0.5 hover:shadow-sm motion-reduce:transition-none motion-reduce:hover:translate-y-0">
            <div class="aspect-[4/3] w-full overflow-hidden bg-emerald-50">
                <img x-show="product.imageUrl" :src="product.imageUrl" alt="" loading="lazy" width="400" height="300"
                    class="block size-full object-cover transition duration-300 group-hover:scale-[1.03] motion-reduce:transition-none">
            </div>
            <div class="space-y-1 p-3">
                <p class="truncate font-semibold leading-5" x-text="product.name"></p>
                <p class="leading-5 text-stone-600"><span class="font-medium text-emerald-800" x-text="money(product.price)"></span> / <span x-text="product.unit"></span></p>
            </div>
        </li>
    </template>
</ul>
