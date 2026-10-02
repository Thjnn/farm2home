@props(['label', 'value', 'hint' => null])
<div {{ $attributes->class('rounded-xl border border-stone-200 bg-white p-4') }}>
    <p class="text-sm text-stone-600">{{ $label }}</p>
    <p class="mt-1 text-2xl font-semibold tracking-tight text-stone-900">{{ $value }}</p>
    @if ($hint)
        <p class="mt-1 text-xs text-stone-500">{{ $hint }}</p>
    @endif
</div>
