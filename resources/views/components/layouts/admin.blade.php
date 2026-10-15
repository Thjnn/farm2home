@props(['title' => 'Quản trị'])
@php
    // Icon: nét SVG lấy từ bộ lucide (viewBox 24, stroke), nhúng thẳng để khỏi thêm thư viện.
    $operations = [
        'admin.dashboard' => ['Tổng quan', '<rect width="7" height="9" x="3" y="3" rx="1"/><rect width="7" height="5" x="14" y="3" rx="1"/><rect width="7" height="9" x="14" y="12" rx="1"/><rect width="7" height="5" x="3" y="16" rx="1"/>'],
        'admin.orders.index' => ['Đơn hàng', '<circle cx="8" cy="21" r="1"/><circle cx="19" cy="21" r="1"/><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"/>'],
        'admin.subscriptions.index' => ['Giỏ định kỳ', '<path d="m17 2 4 4-4 4"/><path d="M3 11v-1a4 4 0 0 1 4-4h14"/><path d="m7 22-4-4 4-4"/><path d="M21 13v1a4 4 0 0 1-4 4H3"/>'],
        'admin.supply.index' => ['Sản lượng', '<path d="M7 20h10"/><path d="M10 20c5.5-2.5.8-6.4 3-10"/><path d="M9.5 9.4c1.1.8 1.8 2.2 2.3 3.7-2 .4-3.5.4-4.8-.3-1.2-.6-2.3-1.9-3-4.2 2.8-.5 4.4 0 5.5.8z"/><path d="M14.1 6a7 7 0 0 0-1.1 4c1.9-.1 3.3-.6 4.3-1.4 1-1 1.6-2.3 1.7-4.6-2.7.1-4 1-4.9 2z"/>'],
        'admin.forecasts.index' => ['Dự báo', '<polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/><polyline points="16 7 22 7 22 13"/>'],
        'admin.harvest-plans.index' => ['Kế hoạch thu hoạch', '<rect width="8" height="4" x="8" y="2" rx="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><path d="M12 11h4"/><path d="M12 16h4"/><path d="M8 11h.01"/><path d="M8 16h.01"/>'],
        'admin.batches.index' => ['Lô hàng', '<rect width="5" height="5" x="3" y="3" rx="1"/><rect width="5" height="5" x="16" y="3" rx="1"/><rect width="5" height="5" x="3" y="16" rx="1"/><path d="M21 16h-3a2 2 0 0 0-2 2v3"/><path d="M21 21v.01"/><path d="M12 7v3a2 2 0 0 1-2 2H7"/><path d="M3 12h.01"/><path d="M12 3h.01"/><path d="M12 16v.01"/><path d="M16 12h1"/><path d="M21 12v.01"/><path d="M12 21v-1"/>'],
        'admin.feedbacks.index' => ['Phản hồi', '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>'],
        'admin.waste.index' => ['Rau dư', '<path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/>'],
        'admin.packaging-returns.index' => ['Bao bì', '<path d="m7.5 4.27 9 5.15"/><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/>'],
        'admin.loyalty.index' => ['Điểm thành viên', '<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>'],
    ];
    $catalog = [
        'admin.products.index' => ['Sản phẩm', '<path d="M11 20A7 7 0 0 1 9.8 6.1C15.5 5 17 4.48 19 2c1 2 2 4.18 2 8 0 5.5-4.78 10-10 10Z"/><path d="M2 21c0-3 1.85-5.36 5.08-6C9.5 14.52 12 13 13 12"/>'],
        'admin.baskets.index' => ['Giỏ rau', '<path d="m15 11-1 9"/><path d="m19 11-4-7"/><path d="M2 11h20"/><path d="m3.5 11 1.6 7.4a2 2 0 0 0 2 1.6h9.8a2 2 0 0 0 2-1.6l1.7-7.4"/><path d="M4.5 15.5h15"/><path d="m5 11 4-7"/><path d="m9 11 1 9"/>'],
        'admin.farmers.index' => ['Nông hộ', '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>'],
        'admin.zones.index' => ['Cụm giao', '<path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/>'],
        'admin.users.index' => ['Tài khoản', '<path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>'],
    ];
    $isAdmin = auth()->user()->role === \App\Enums\UserRole::Admin;
    // A menu entry stays highlighted on every page of its section (index, show, create...).
    $isCurrent = fn (string $routeName) => $routeName === 'admin.dashboard'
        ? request()->routeIs('admin.dashboard')
        : request()->routeIs(str($routeName)->beforeLast('.')->append('.*')->value());
    $icon = fn (string $paths, string $class = 'size-5') => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="'.$class.' shrink-0" aria-hidden="true">'.$paths.'</svg>';
    // Chữ ẩn khi thu gọn (chỉ màn hình lớn; trên điện thoại menu luôn mở đủ).
    $label = 'truncate lg:group-data-[collapsed=true]:hidden';
    // Số trên menu, cập nhật mỗi 15 giây qua admin.notifications.
    $badgeKeys = ['admin.orders.index' => 'orders', 'admin.feedbacks.index' => 'feedbacks'];
    $badges = app(\App\Services\NotificationService::class)->badges();
@endphp
<x-layouts.base :title="$title">
    @isset($head)
        <x-slot:head>{{ $head }}</x-slot:head>
    @endisset

    <div x-data="adminNotifications({ url: @js(route('admin.notifications')), since: {{ now()->getTimestamp() }}, badges: @js($badges) })">
    <div x-data="{
            collapsed: (() => { try { return localStorage.getItem('f2h-sidebar') === '1' } catch (e) { return false } })(),
            drawer: false,
            toggle() { this.collapsed = ! this.collapsed; try { localStorage.setItem('f2h-sidebar', this.collapsed ? '1' : '0') } catch (e) {} },
         }"
         @keydown.escape.window="drawer = false"
         class="min-h-screen lg:flex">

        {{-- Thanh trên cho điện thoại --}}
        <header class="sticky top-0 z-30 flex items-center gap-3 border-b border-stone-200 bg-white px-4 py-2 lg:hidden print:hidden">
            <button type="button" @click="drawer = true" class="relative inline-flex size-11 items-center justify-center rounded-lg border border-stone-300 hover:bg-stone-100" aria-label="Mở menu">
                {!! $icon('<line x1="4" x2="20" y1="12" y2="12"/><line x1="4" x2="20" y1="6" y2="6"/><line x1="4" x2="20" y1="18" y2="18"/>') !!}
                <span x-show="badges.orders + badges.feedbacks > 0" @if ($badges['orders'] + $badges['feedbacks'] === 0) x-cloak @endif class="absolute -right-1 -top-1 size-3 rounded-full bg-red-500" aria-hidden="true"></span>
            </button>
            <a href="{{ route('admin.dashboard') }}" class="py-1"><x-brand /></a>
        </header>

        <div x-show="drawer" x-cloak x-transition.opacity @click="drawer = false" class="fixed inset-0 z-40 bg-stone-900/40 lg:hidden" aria-hidden="true"></div>

        <aside :data-collapsed="collapsed"
               :class="[drawer ? 'translate-x-0' : '-translate-x-full', collapsed ? 'lg:w-16' : 'lg:w-64']"
               class="group fixed inset-y-0 left-0 z-50 flex w-64 -translate-x-full flex-col border-r border-stone-200 bg-white shadow-sm transition-all duration-300 lg:sticky lg:top-0 lg:h-screen lg:shrink-0 lg:translate-x-0 print:hidden">
            <div class="flex items-center justify-between gap-2 border-b border-stone-200 p-3">
                <a href="{{ route('admin.dashboard') }}" class="min-w-0 py-1">
                    <span class="lg:group-data-[collapsed=true]:hidden"><x-brand /></span>
                    <img src="{{ asset('images/icon-f2h-removebg-preview.png') }}" alt="FARM2HOME" class="hidden h-7 w-auto lg:group-data-[collapsed=true]:block">
                </a>
                <button type="button" @click="drawer = false" class="inline-flex size-10 items-center justify-center rounded-lg hover:bg-stone-100 lg:hidden" aria-label="Đóng menu">
                    {!! $icon('<path d="M18 6 6 18"/><path d="m6 6 12 12"/>') !!}
                </button>
            </div>

            <nav class="flex-1 space-y-1 overflow-y-auto p-2 text-sm" aria-label="Điều hướng chính">
                @foreach ([['Vận hành', $operations], ['Danh mục', $isAdmin ? $catalog : []]] as [$heading, $items])
                    @continue($items === [])
                    <p class="px-3 pb-1 pt-3 text-xs font-medium uppercase tracking-wide text-stone-500 first:pt-1 lg:group-data-[collapsed=true]:hidden">{{ $heading }}</p>
                    <hr class="mx-2 my-2 hidden border-stone-200 lg:group-data-[collapsed=true]:block">
                    @foreach ($items as $routeName => [$text, $paths])
                        @if (Route::has($routeName))
                            <a href="{{ route($routeName) }}" title="{{ $text }}"
                               @if ($isCurrent($routeName)) aria-current="page" @endif
                               @class([
                                   'relative flex min-h-11 items-center gap-3 rounded-md border-l-2 px-3 lg:min-h-9 transition-colors lg:group-data-[collapsed=true]:justify-center lg:group-data-[collapsed=true]:px-0',
                                   'border-emerald-600 bg-emerald-50 font-semibold text-emerald-800' => $isCurrent($routeName),
                                   'border-transparent text-stone-600 hover:bg-stone-100 hover:text-stone-900' => ! $isCurrent($routeName),
                               ])>
                                {!! $icon($paths) !!}
                                <span class="{{ $label }}">{{ $text }}</span>
                                @isset($badgeKeys[$routeName])
                                    @php($badgeKey = $badgeKeys[$routeName])
                                    <span x-show="badges.{{ $badgeKey }} > 0" x-text="badges.{{ $badgeKey }}" @if ($badges[$badgeKey] === 0) x-cloak @endif
                                          class="ml-auto min-w-5 rounded-full bg-red-500 px-1.5 text-center text-xs font-semibold leading-5 text-white lg:group-data-[collapsed=true]:absolute lg:group-data-[collapsed=true]:right-1 lg:group-data-[collapsed=true]:top-0.5 lg:group-data-[collapsed=true]:min-w-4 lg:group-data-[collapsed=true]:px-1 lg:group-data-[collapsed=true]:text-[10px] lg:group-data-[collapsed=true]:leading-4">{{ $badges[$badgeKey] }}</span>
                                @endisset
                            </a>
                        @else
                            <span class="flex min-h-11 items-center gap-3 px-3 text-stone-400 lg:group-data-[collapsed=true]:justify-center lg:group-data-[collapsed=true]:px-0" title="{{ $text }}">
                                {!! $icon($paths) !!}<span class="{{ $label }}">{{ $text }}</span>
                            </span>
                        @endif
                    @endforeach
                @endforeach
            </nav>

            <div class="space-y-1 border-t border-stone-200 p-2 text-sm">
                <p class="truncate px-3 py-1 text-stone-600 lg:group-data-[collapsed=true]:hidden">{{ auth()->user()->name }} · {{ auth()->user()->role->label() }}</p>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button title="Đăng xuất" class="flex min-h-11 w-full items-center gap-3 rounded-md px-3 text-stone-600 hover:bg-stone-100 hover:text-stone-900 lg:group-data-[collapsed=true]:justify-center lg:group-data-[collapsed=true]:px-0">
                        {!! $icon('<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" x2="9" y1="12" y2="12"/>') !!}
                        <span class="{{ $label }}">Đăng xuất</span>
                    </button>
                </form>
                <button type="button" @click="toggle()" :aria-expanded="(! collapsed).toString()" :title="collapsed ? 'Mở rộng menu' : 'Thu gọn menu'"
                        class="hidden min-h-11 w-full items-center gap-3 rounded-md px-3 text-stone-600 hover:bg-stone-100 lg:flex lg:group-data-[collapsed=true]:justify-center lg:group-data-[collapsed=true]:px-0">
                    {!! $icon('<path d="m11 17-5-5 5-5"/><path d="m18 17-5-5 5-5"/>', 'size-5 transition-transform duration-300 lg:group-data-[collapsed=true]:rotate-180') !!}
                    <span class="{{ $label }}">Thu gọn</span>
                </button>
            </div>
        </aside>

        <main class="min-w-0 flex-1">
            <div class="mx-auto max-w-6xl px-4 py-6">
                <h1 class="mb-4 text-xl font-semibold">{{ $title }}</h1>
                <x-flash />
                {{ $slot }}
            </div>
        </main>
    </div>

    {{-- Thông báo mới, tự hiện không cần F5 --}}
    <div class="fixed bottom-4 right-4 z-[60] flex w-[min(24rem,calc(100vw-2rem))] flex-col gap-2 print:hidden" role="status" aria-live="polite">
        <template x-for="toast in toasts" :key="toast.id">
            <div x-transition class="rounded-xl border border-emerald-200 bg-white p-3 text-sm shadow-lg">
                <div class="flex items-start gap-2">
                    <span class="mt-1.5 size-2 shrink-0 rounded-full bg-emerald-600" aria-hidden="true"></span>
                    <p class="min-w-0 flex-1 leading-snug" x-text="toast.text"></p>
                    <button type="button" @click="dismiss(toast.id)" class="-m-1 inline-flex size-8 shrink-0 items-center justify-center rounded-md text-stone-500 hover:bg-stone-100" aria-label="Đóng thông báo">
                        {!! $icon('<path d="M18 6 6 18"/><path d="m6 6 12 12"/>', 'size-4') !!}
                    </button>
                </div>
                <div class="mt-2 flex gap-2 pl-4">
                    <a :href="toast.url" class="inline-flex min-h-9 items-center rounded-lg bg-emerald-700 px-3 font-semibold text-white hover:bg-emerald-800">Xem</a>
                    <button type="button" @click="location.reload()" class="inline-flex min-h-9 items-center rounded-lg border border-stone-300 px-3 hover:bg-stone-100">Tải lại trang</button>
                </div>
            </div>
        </template>
    </div>
    </div>
</x-layouts.base>
