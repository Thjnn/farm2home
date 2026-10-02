@props(['title' => 'FARM2HOME'])
<x-layouts.base :title="$title">
    <header class="border-b border-stone-200 bg-white">
        <div class="mx-auto flex max-w-xl items-center gap-4 px-4 py-2">
            <a href="{{ route('home') }}" class="py-2 text-lg font-bold tracking-tight text-emerald-800">FARM2HOME</a>
            <nav class="ml-auto flex gap-1 text-sm" aria-label="Điều hướng">
                @if (Route::has('orders.create'))
                    <a href="{{ route('orders.create') }}" class="rounded-md px-3 py-2.5 hover:bg-stone-100">Đặt hàng</a>
                @endif
                @if (Route::has('lookup.show'))
                    <a href="{{ route('lookup.show') }}" class="rounded-md px-3 py-2.5 hover:bg-stone-100">Tra cứu đơn</a>
                @endif
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
