@props(['title' => 'Quản trị'])
@php
    // Menu lấy từ config/admin_menu.php; icon là nét SVG lucide nhúng thẳng để khỏi thêm thư viện.
    $menu = config('admin_menu');
    $isAdmin = auth()->user()->role === \App\Enums\UserRole::Admin;
    $groups = collect($menu['groups'])->reject(fn ($group) => ($group['admin_only'] ?? false) && ! $isAdmin);
    // Một mục sáng trên mọi trang của nó (index, show, create...).
    $isCurrent = fn (string $routeName) => $routeName === 'admin.dashboard'
        ? request()->routeIs('admin.dashboard')
        : request()->routeIs(str($routeName)->beforeLast('.')->append('.*')->value());
    $activeGroup = $groups->search(fn ($group) => collect(array_keys($group['items']))->contains($isCurrent)) ?: null;
    $icon = fn (string $paths, string $class = 'size-5') => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="'.$class.' shrink-0" aria-hidden="true">'.$paths.'</svg>';
    // Chữ ẩn khi thu gọn (chỉ màn hình lớn; trên điện thoại menu luôn mở đủ).
    $label = 'truncate lg:group-data-[collapsed=true]:hidden';
    // Số trên menu, cập nhật mỗi 15 giây qua admin.notifications.
    $badgeOf = $menu['badges'];
    $badges = app(\App\Services\NotificationService::class)->badges();
    $badgeSum = fn (array $routes) => collect($routes)->map(fn ($route) => isset($badgeOf[$route]) ? 'badges.'.$badgeOf[$route]['key'] : null)->filter()->join(' + ') ?: '0';
@endphp
<x-layouts.base :title="$title">
    @isset($head)
        <x-slot:head>{{ $head }}</x-slot:head>
    @endisset

    <div x-data="adminNotifications({ url: @js(route('admin.notifications')), since: {{ now()->getTimestamp() }}, badges: @js($badges) })">
    <div x-data="adminSidebar({ defaults: @js($groups->map(fn ($group) => $group['open'])), active: @js($activeGroup) })"
         @keydown.escape.window="drawer = false"
         class="min-h-screen lg:flex">

        {{-- Thanh trên cho điện thoại --}}
        <header class="sticky top-0 z-30 flex items-center gap-3 border-b border-stone-200 bg-white px-4 py-2 lg:hidden print:hidden">
            <button type="button" @click="drawer = true" class="relative inline-flex size-11 items-center justify-center rounded-lg border border-stone-300 hover:bg-stone-100" aria-label="Mở menu" :aria-expanded="drawer.toString()" aria-controls="admin-sidebar">
                {!! $icon('<line x1="4" x2="20" y1="12" y2="12"/><line x1="4" x2="20" y1="6" y2="6"/><line x1="4" x2="20" y1="18" y2="18"/>') !!}
                <span x-show="badges.orders + badges.feedbacks > 0" @if ($badges['orders'] + $badges['feedbacks'] === 0) x-cloak @endif class="absolute -right-1 -top-1 size-3 rounded-full bg-red-500" aria-hidden="true"></span>
            </button>
            <a href="{{ route('admin.dashboard') }}" class="py-1"><x-brand /></a>
        </header>

        <div x-show="drawer" x-cloak x-transition.opacity @click="drawer = false" class="fixed inset-0 z-40 bg-stone-900/40 lg:hidden" aria-hidden="true"></div>

        <aside id="admin-sidebar" :data-collapsed="collapsed"
               :class="[drawer ? 'translate-x-0' : '-translate-x-full', collapsed ? 'lg:w-16' : 'lg:w-64']"
               class="group fixed inset-y-0 left-0 z-50 flex h-dvh w-64 -translate-x-full flex-col border-r border-stone-200 bg-white shadow-sm transition-all duration-300 motion-reduce:transition-none lg:sticky lg:top-0 lg:h-screen lg:shrink-0 lg:translate-x-0 print:hidden">
            <div class="flex shrink-0 items-center justify-between gap-2 border-b border-stone-200 p-3">
                <a href="{{ route('admin.dashboard') }}" class="min-w-0 py-1">
                    <span class="lg:group-data-[collapsed=true]:hidden"><x-brand /></span>
                    <img src="{{ asset('images/icon-f2h-removebg-preview.png') }}" alt="FARM2HOME" class="hidden h-7 w-auto lg:group-data-[collapsed=true]:block">
                </a>
                <button type="button" @click="drawer = false" class="inline-flex size-11 items-center justify-center rounded-lg hover:bg-stone-100 lg:hidden" aria-label="Đóng menu">
                    {!! $icon('<path d="M18 6 6 18"/><path d="m6 6 12 12"/>') !!}
                </button>
            </div>

            {{-- Chỉ phần menu cuộn; đầu (logo) và chân (tài khoản) đứng yên. --}}
            <nav class="min-h-0 flex-1 overflow-y-auto px-2 py-1.5 text-sm" aria-label="Điều hướng chính">
                @php
                    $item = function (string $routeName, string $text, string $paths) use ($isCurrent, $icon, $label, $badgeOf, $badges) {
                        $current = $isCurrent($routeName);
                        $html = '<a href="'.e(route($routeName)).'" data-label="'.e($text).'"'.($current ? ' aria-current="page"' : '')
                            .' @click="drawer = false" @mouseenter="tip($el)" @mouseleave="tip()" @focus="tip($el)" @blur="tip()"'
                            .' class="relative flex min-h-11 items-center gap-3 rounded-md border-l-2 px-3 transition-colors focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-emerald-700 lg:min-h-8 lg:group-data-[collapsed=true]:justify-center lg:group-data-[collapsed=true]:px-0 '
                            .($current ? 'border-emerald-600 bg-emerald-50 font-semibold text-emerald-800' : 'border-transparent text-stone-600 hover:bg-stone-100 hover:text-stone-900').'">'
                            .$icon($paths).'<span class="'.$label.'">'.e($text).'</span>';
                        if (isset($badgeOf[$routeName])) {
                            ['key' => $key, 'label' => $what] = $badgeOf[$routeName];
                            $cloak = $badges[$key] === 0 ? ' x-cloak' : '';
                            $html .= '<span x-show="badges.'.$key.' > 0" x-text="count(badges.'.$key.')" :aria-label="badges.'.$key.' + \' '.e($what).'\'"'.$cloak
                                .' class="ml-auto min-w-5 rounded-full bg-red-500 px-1.5 text-center text-xs font-semibold leading-5 text-white lg:group-data-[collapsed=true]:hidden">'.($badges[$key] > 99 ? '99+' : $badges[$key]).'</span>'
                                .'<span x-show="badges.'.$key.' > 0"'.$cloak.' class="absolute left-[calc(50%+4px)] top-1 hidden size-2 rounded-full bg-red-500 ring-2 ring-white lg:group-data-[collapsed=true]:block" aria-hidden="true"></span>';
                        }

                        return $html.'</a>';
                    };
                @endphp

                @foreach ($menu['home'] as $routeName => [$text, $paths])
                    {!! $item($routeName, $text, $paths) !!}
                @endforeach

                @foreach ($groups as $key => $group)
                    @php($defaultShown = $group['open'] || $activeGroup === $key)
                    <div class="pt-1">
                        <hr class="mx-2 mb-1.5 mt-0.5 hidden border-stone-200 lg:group-data-[collapsed=true]:block">
                        <button type="button" @click="toggleGroup(@js($key))" :aria-expanded="(groups[@js($key)] ?? false).toString()" aria-controls="menu-{{ $key }}"
                            aria-expanded="{{ $defaultShown ? 'true' : 'false' }}"
                            class="flex min-h-11 w-full items-center gap-2 rounded-md px-3 text-xs font-semibold uppercase tracking-wide text-stone-500 hover:bg-stone-100 hover:text-stone-800 focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-emerald-700 lg:min-h-7 lg:group-data-[collapsed=true]:hidden [&[aria-expanded=false]>svg]:-rotate-90">
                            <span>{{ $group['label'] }}</span>
                            @if ($activeGroup === $key)
                                <span x-show="!groups[@js($key)]" x-cloak class="size-1.5 rounded-full bg-emerald-600" title="Đang ở trang trong nhóm này"></span>
                            @endif
                            @php($sum = $badgeSum(array_keys($group['items'])))
                            @if ($sum !== '0')
                                <span x-show="!groups[@js($key)] && {{ $sum }} > 0" x-cloak x-text="count({{ $sum }})"
                                    class="min-w-5 rounded-full bg-red-500 px-1.5 text-center text-[11px] font-semibold normal-case leading-5 tracking-normal text-white"></span>
                            @endif
                            {!! $icon('<path d="m6 9 6 6 6-6"/>', 'ml-auto size-4 transition-transform duration-200 motion-reduce:transition-none') !!}
                        </button>
                        {{-- Mở/đóng bằng grid-template-rows 0fr ↔ 1fr (~200 ms); inert để Tab không vào mục đang đóng. --}}
                        <div id="menu-{{ $key }}" class="grid transition-[grid-template-rows] duration-200 ease-out motion-reduce:transition-none"
                            style="grid-template-rows: {{ $defaultShown ? '1fr' : '0fr' }}" @unless ($defaultShown) inert @endunless
                            :style="{ gridTemplateRows: shown(@js($key)) ? '1fr' : '0fr' }" :inert="!shown(@js($key))">
                            <div class="min-h-0 space-y-0.5 overflow-hidden pt-0.5">
                                @foreach ($group['items'] as $routeName => [$text, $paths])
                                    @continue(! Route::has($routeName))
                                    {!! $item($routeName, $text, $paths) !!}
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endforeach
            </nav>

            <div class="shrink-0 border-t border-stone-200 p-2 text-sm">
                <p class="truncate px-3 pb-1 text-stone-600 lg:group-data-[collapsed=true]:hidden">{{ auth()->user()->name }} · {{ auth()->user()->role->label() }}</p>
                <div class="flex gap-1 lg:group-data-[collapsed=true]:flex-col">
                    <form method="POST" action="{{ route('logout') }}" class="min-w-0 flex-1">
                        @csrf
                        <button data-label="Đăng xuất" @mouseenter="tip($el)" @mouseleave="tip()" @focus="tip($el)" @blur="tip()"
                            class="flex min-h-11 w-full items-center gap-3 rounded-md px-3 text-stone-600 hover:bg-stone-100 hover:text-stone-900 focus-visible:outline-2 focus-visible:outline-emerald-700 lg:group-data-[collapsed=true]:justify-center lg:group-data-[collapsed=true]:px-0">
                            {!! $icon('<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" x2="9" y1="12" y2="12"/>') !!}
                            <span class="{{ $label }}">Đăng xuất</span>
                        </button>
                    </form>
                    <button type="button" @click="toggle()" :aria-expanded="(! collapsed).toString()" :aria-label="collapsed ? 'Mở rộng menu' : 'Thu gọn menu'"
                            :data-label="collapsed ? 'Mở rộng menu' : 'Thu gọn menu'" @mouseenter="tip($el)" @mouseleave="tip()" @focus="tip($el)" @blur="tip()"
                            class="hidden min-h-11 shrink-0 items-center gap-2 rounded-md px-3 text-stone-600 hover:bg-stone-100 focus-visible:outline-2 focus-visible:outline-emerald-700 lg:flex lg:group-data-[collapsed=true]:justify-center lg:group-data-[collapsed=true]:px-0">
                        {!! $icon('<path d="m11 17-5-5 5-5"/><path d="m18 17-5-5 5-5"/>', 'size-5 transition-transform duration-300 lg:group-data-[collapsed=true]:rotate-180') !!}
                        <span class="{{ $label }}">Thu gọn</span>
                    </button>
                </div>
            </div>

            {{-- Tooltip tên trang khi menu thu gọn; đặt fixed để không bị phần menu cuộn cắt mất. --}}
            <div x-show="tooltip" x-cloak role="tooltip" x-text="tooltip?.text" :style="tooltip ? { top: tooltip.top + 'px' } : {}"
                class="pointer-events-none fixed left-[4.5rem] z-[60] -translate-y-1/2 whitespace-nowrap rounded-md bg-stone-900 px-2.5 py-1.5 text-xs font-medium text-white shadow-lg"></div>
        </aside>

        <main class="min-w-0 flex-1">
            <div class="mx-auto max-w-6xl px-4 py-6">
                {{-- Đầu trang: tiêu đề, mô tả ngắn (subtitle) và nút chính bên phải (actions). --}}
                <div class="mb-4 flex flex-wrap items-end justify-between gap-x-4 gap-y-3">
                    <div class="min-w-0">
                        <h1 class="text-xl font-semibold sm:text-2xl">{{ $title }}</h1>
                        @isset($subtitle)
                            <p class="mt-1 text-sm text-stone-600">{{ $subtitle }}</p>
                        @endisset
                    </div>
                    @isset($actions)
                        <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
                    @endisset
                </div>
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
