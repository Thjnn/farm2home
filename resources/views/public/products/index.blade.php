<x-layouts.public title="Rau đang có" wide>
    <section aria-labelledby="products-heading"
        x-data="pagedList({
            endpoint: @js(route('api.products')),
            key: 'trang',
            initial: @js($products),
            filters: { q: @js($search), category: @js($category?->value ?? '') },
            urlKeys: { q: 'q', category: 'danh_muc' },
        })">
        <div class="mb-4 flex flex-wrap items-end justify-between gap-x-4 gap-y-1">
            <h1 id="products-heading" x-ref="heading" class="scroll-mt-4 text-2xl font-bold tracking-tight">Rau đang có</h1>
            <a href="{{ route('orders.create') }}" class="inline-flex min-h-11 items-center font-medium text-emerald-800 hover:underline">Đặt hàng <span aria-hidden="true">&nbsp;→</span></a>
        </div>

        <form role="search" class="relative mb-3 max-w-xl" @submit.prevent="refilter()">
            <label for="product-search" class="sr-only">Tìm rau theo tên</label>
            <svg class="pointer-events-none absolute left-3 top-1/2 size-5 -translate-y-1/2 text-stone-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 1 0 0 11 5.5 5.5 0 0 0 0-11ZM2 9a7 7 0 1 1 12.45 4.39l3.08 3.08a.75.75 0 1 1-1.06 1.06l-3.08-3.08A7 7 0 0 1 2 9Z" clip-rule="evenodd"/>
            </svg>
            <input id="product-search" type="search" name="q" value="{{ $search }}" placeholder="Tìm rau, ví dụ: cải" autocomplete="off" enterkeyhint="search"
                x-model="filters.q" @input.debounce.300ms="refilter()"
                class="block min-h-12 w-full rounded-xl border border-stone-300 bg-white pl-10 pr-3 text-base placeholder:text-stone-400 focus:border-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-700/20">
        </form>

        <div class="-mx-4 mb-4 flex gap-2 overflow-x-auto px-4 pb-1 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden" role="group" aria-label="Danh mục">
            @foreach (['' => 'Tất cả'] + collect($categories)->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all() as $value => $label)
                <button type="button" @click="filters.category = @js($value); refilter()"
                    :aria-pressed="filters.category === @js($value)"
                    :class="filters.category === @js($value) ? 'border-emerald-700 bg-emerald-700 text-white' : 'border-stone-300 bg-white text-stone-800 hover:bg-stone-100'"
                    class="min-h-11 shrink-0 whitespace-nowrap rounded-full border px-4 text-base font-medium transition-colors">{{ $label }}</button>
            @endforeach
        </div>

        <p class="mb-3 text-stone-600" aria-live="polite">
            <span x-show="!loading">Tìm thấy <strong class="text-stone-900" x-text="total">{{ $products['total'] }}</strong> món</span>
            <span x-show="loading" x-cloak>Đang tìm…</span>
        </p>

        <x-product-grid :skeletons="max(2, count($products['items']))" />

        <div x-show="!loading && total === 0" @if ($products['total'] > 0) x-cloak @endif class="rounded-xl border border-dashed border-stone-300 bg-white px-4 py-10 text-center">
            <p class="font-semibold">Chưa có món nào khớp</p>
            <p class="mt-1 text-stone-600">Thử từ khoá ngắn hơn hoặc chọn danh mục khác.</p>
            <button type="button" @click="filters.q = ''; filters.category = ''; refilter()" class="mt-4 inline-flex min-h-11 items-center rounded-lg border border-emerald-700 px-4 font-semibold text-emerald-800 hover:bg-emerald-50">Xem tất cả rau</button>
        </div>

        <x-pagination label="Trang danh sách rau" :total-pages="$products['totalPages']" />
    </section>
</x-layouts.public>
