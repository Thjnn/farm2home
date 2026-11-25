@props(['label', 'value', 'hint' => null, 'size' => 'md', 'tone' => 'default'])
{{-- size="lg" + tone (default/emerald/amber) dùng cho hàng số chính ở trang Tổng quan; mặc định giữ kiểu cũ. --}}
<div {{ $attributes->class([
    'rounded-xl border bg-white',
    'p-4' => $size !== 'lg',
    'p-5' => $size === 'lg',
    'border-stone-200' => $tone === 'default',
    'border-emerald-200 bg-emerald-50/60' => $tone === 'emerald',
    'border-amber-200 bg-amber-50/60' => $tone === 'amber',
]) }}>
    <p @class(['text-sm', 'text-stone-600' => $size !== 'lg', 'font-medium text-stone-700' => $size === 'lg'])>{{ $label }}</p>
    <p @class([
        'mt-1 tracking-tight text-stone-900 tabular-nums',
        'text-2xl font-semibold' => $size !== 'lg',
        'text-3xl font-bold sm:text-4xl' => $size === 'lg',
    ])>{{ $value }}</p>
    @if ($hint)
        <p @class(['mt-1 text-xs text-stone-500' => $size !== 'lg', 'mt-2 text-sm text-stone-600' => $size === 'lg'])>{{ $hint }}</p>
    @endif
    {{ $slot }}
</div>
