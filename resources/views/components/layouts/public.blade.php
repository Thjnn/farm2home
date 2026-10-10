@props(['title' => 'FARM2HOME'])
<x-layouts.base :title="$title">
    <header class="border-b border-stone-200 bg-white">
        <div class="mx-auto flex max-w-xl flex-wrap items-center gap-x-4 px-4 py-2">
            <a href="{{ route('home') }}" class="shrink-0 py-1"><x-brand /></a>
            <nav class="ml-auto flex gap-1 text-sm" aria-label="Điều hướng">
                @if (Route::has('orders.create'))
                    <a href="{{ route('orders.create') }}" class="whitespace-nowrap rounded-md px-2 py-2.5 hover:bg-stone-100 sm:px-3">Đặt hàng</a>
                @endif
                @if (Route::has('lookup.show'))
                    <a href="{{ route('lookup.show') }}" class="whitespace-nowrap rounded-md px-2 py-2.5 hover:bg-stone-100 sm:px-3">Tra cứu đơn</a>
                @endif
                @php($accountLabel = auth()->check() ? 'Quản trị' : 'Đăng nhập')
                <a href="{{ auth()->check() ? route('admin.dashboard') : route('login') }}" aria-label="{{ $accountLabel }}" class="flex items-center whitespace-nowrap rounded-md border border-emerald-700 px-2.5 py-2 font-medium text-emerald-800 hover:bg-emerald-50 sm:px-3">
                    <svg class="size-5 sm:hidden" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10 10a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm-7 8a7 7 0 0 1 14 0H3Z"/></svg>
                    <span class="hidden sm:inline">{{ $accountLabel }}</span>
                </a>
            </nav>
        </div>
    </header>

    <main class="mx-auto max-w-xl px-4 py-6">
        {{ $slot }}
    </main>

    <footer class="mx-auto max-w-xl px-4 pb-8 pt-2 text-center text-sm text-stone-500">
        Điều phối nông sản theo nhu cầu, giảm dư thừa.
    </footer>
</x-layouts.base>
