@props(['product'])
{{-- Tên rau luôn hiện ngay cạnh ảnh nên alt để trống (ảnh trang trí). --}}
@if ($product->image)
    <img src="{{ asset('images/'.$product->image) }}" alt="" loading="lazy" {{ $attributes->class('block shrink-0 bg-stone-100 object-cover') }}>
@else
    <span aria-hidden="true" {{ $attributes->class('block shrink-0 bg-emerald-50') }}></span>
@endif
