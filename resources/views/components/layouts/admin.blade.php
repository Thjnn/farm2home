@props(['title' => 'Quản trị'])
@php
    $operations = [
        'admin.dashboard' => 'Tổng quan',
        'admin.orders.index' => 'Đơn hàng',
        'admin.subscriptions.index' => 'Giỏ định kỳ',
        'admin.supply.index' => 'Sản lượng',
        'admin.forecasts.index' => 'Dự báo',
        'admin.harvest-plans.index' => 'Kế hoạch thu hoạch',
        'admin.batches.index' => 'Lô hàng',
        'admin.feedbacks.index' => 'Phản hồi',
        'admin.waste.index' => 'Rau dư',
        'admin.packaging-returns.index' => 'Bao bì',
        'admin.loyalty.index' => 'Điểm thành viên',
    ];
    $catalog = [
        'admin.products.index' => 'Sản phẩm',
        'admin.baskets.index' => 'Giỏ rau',
        'admin.farmers.index' => 'Nông hộ',
        'admin.zones.index' => 'Cụm giao',
        'admin.users.index' => 'Tài khoản',
    ];
    $isAdmin = auth()->user()->role === \App\Enums\UserRole::Admin;
    // A menu entry stays highlighted on every page of its section (index, show, create...).
    $isCurrent = fn (string $routeName) => $routeName === 'admin.dashboard'
        ? request()->routeIs('admin.dashboard')
        : request()->routeIs(str($routeName)->beforeLast('.')->append('.*')->value());
@endphp
<x-layouts.base :title="$title">
    @isset($head)
        <x-slot:head>{{ $head }}</x-slot:head>
    @endisset

    <header class="border-b border-stone-200 bg-white print:hidden">
        <div class="mx-auto flex max-w-6xl flex-wrap items-center gap-x-6 gap-y-1 px-4 py-2">
            <a href="{{ route('admin.dashboard') }}" class="py-2 text-lg font-bold tracking-tight text-emerald-800">FARM2HOME</a>
            <form method="POST" action="{{ route('logout') }}" class="ml-auto flex items-center gap-3 text-sm">
                @csrf
                <span class="text-stone-600">{{ auth()->user()->name }} · {{ auth()->user()->role->label() }}</span>
                <button class="min-h-11 rounded-lg border border-stone-300 px-3 hover:bg-stone-100">Đăng xuất</button>
            </form>
        </div>
        <nav class="mx-auto flex max-w-6xl flex-wrap gap-x-1 gap-y-1 px-3 pb-2 text-sm" aria-label="Điều hướng chính">
            @foreach ($operations as $routeName => $label)
                @if (Route::has($routeName))
                    <a href="{{ route($routeName) }}" @class(['rounded-md px-2.5 py-1.5 hover:bg-stone-100', 'bg-emerald-50 font-semibold text-emerald-800' => $isCurrent($routeName)])>{{ $label }}</a>
                @else
                    <span class="px-2.5 py-1.5 text-stone-400">{{ $label }}</span>
                @endif
            @endforeach
            @if ($isAdmin)
                <span class="mx-1 hidden border-l border-stone-200 sm:block" aria-hidden="true"></span>
                @foreach ($catalog as $routeName => $label)
                    @if (Route::has($routeName))
                        <a href="{{ route($routeName) }}" @class(['rounded-md px-2.5 py-1.5 hover:bg-stone-100', 'bg-emerald-50 font-semibold text-emerald-800' => $isCurrent($routeName)])>{{ $label }}</a>
                    @endif
                @endforeach
            @endif
        </nav>
    </header>

    <main class="mx-auto max-w-6xl px-4 py-6">
        <h1 class="mb-4 text-xl font-semibold">{{ $title }}</h1>
        <x-flash />
        {{ $slot }}
    </main>
</x-layouts.base>
