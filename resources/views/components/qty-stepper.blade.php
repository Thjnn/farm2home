@props(['item'])
{{-- Nút −/số gói/+ (44px) cho một món; `item` là tên biến Alpine của món, đặt trong orderCart(...). --}}
<div class="flex shrink-0 items-center">
    <button type="button" x-show="quantity({{ $item }}.id) === 0" @click="change({{ $item }}, 1)"
        class="inline-flex min-h-11 items-center gap-1 rounded-lg border border-emerald-700 px-3 font-semibold text-emerald-800 hover:bg-emerald-50 active:scale-95 motion-reduce:active:scale-100"
        :aria-label="'Thêm ' + {{ $item }}.name"><span aria-hidden="true">+</span> Thêm</button>
    <div x-show="quantity({{ $item }}.id) > 0" class="flex items-center rounded-lg border border-emerald-700 bg-emerald-50">
        <button type="button" @click="change({{ $item }}, -1)" class="inline-flex size-11 items-center justify-center text-xl text-emerald-900 active:scale-90 motion-reduce:active:scale-100"
            :aria-label="'Bớt ' + {{ $item }}.name">−</button>
        <span class="min-w-6 text-center font-semibold tabular-nums" x-text="quantity({{ $item }}.id)" aria-live="polite"></span>
        <button type="button" @click="change({{ $item }}, 1)" class="inline-flex size-11 items-center justify-center text-xl text-emerald-900 active:scale-90 motion-reduce:active:scale-100"
            :aria-label="'Thêm ' + {{ $item }}.name">+</button>
    </div>
</div>
