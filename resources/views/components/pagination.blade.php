@props(['label' => 'Phân trang', 'totalPages' => 0])
{{--
    Thanh phân trang gọn cho điện thoại; đặt bên trong phần tử có x-data="pagedList(...)".
    Ẩn khi chỉ có 1 trang. Dưới 400px chỉ hiện mũi tên để vừa 360px.
--}}
@php($button = 'inline-flex min-h-11 min-w-11 items-center justify-center rounded-lg px-2 text-base font-medium disabled:pointer-events-none disabled:text-stone-300')
<nav aria-label="{{ $label }}" x-show="totalPages > 1" @if ($totalPages <= 1) x-cloak @endif
    {{ $attributes->class('mt-4 flex items-center justify-center gap-1') }}>
    <button type="button" class="{{ $button }} text-stone-800 hover:bg-stone-100" aria-label="Trang trước" @click="go(page - 1)" :disabled="page <= 1">
        <span aria-hidden="true">‹</span><span class="ml-1 hidden min-[400px]:inline" aria-hidden="true">Trước</span>
    </button>
    <template x-for="(number, index) in pages" :key="index + '-' + number">
        <span class="contents">
            <span x-show="number === '…'" class="w-4 text-center text-stone-500" aria-hidden="true">…</span>
            <button type="button" x-show="number !== '…'" @click="go(number)" x-text="number"
                :aria-label="'Trang ' + number" :aria-current="number === page ? 'page' : null"
                :class="number === page ? 'bg-emerald-700 text-white' : 'text-stone-800 hover:bg-stone-100'" class="{{ $button }}"></button>
        </span>
    </template>
    <button type="button" class="{{ $button }} text-stone-800 hover:bg-stone-100" aria-label="Trang sau" @click="go(page + 1)" :disabled="page >= totalPages">
        <span class="mr-1 hidden min-[400px]:inline" aria-hidden="true">Sau</span><span aria-hidden="true">›</span>
    </button>
</nav>
