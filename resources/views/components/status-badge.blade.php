@props(['status'])
@php
    $classes = match ($status->value) {
        'pending', 'draft', 'new' => 'bg-amber-100 text-amber-800',
        'confirmed', 'packing', 'delivering', 'sent', 'processing' => 'bg-sky-100 text-sky-800',
        'delivered', 'completed', 'passed', 'resolved', 'active', 'paid' => 'bg-emerald-100 text-emerald-800',
        'cancelled', 'rejected', 'inactive', 'refunded' => 'bg-rose-100 text-rose-800',
        default => 'bg-stone-100 text-stone-700',
    };
@endphp
<span {{ $attributes->class(['inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium', $classes]) }}>{{ $status->label() }}</span>
