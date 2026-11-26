@props(['title' => 'Giao hàng'])
<x-layouts.base :title="$title">
    <header class="sticky top-0 z-10 border-b border-stone-200 bg-white">
        <div class="mx-auto flex max-w-xl items-center gap-3 px-4 py-2">
            <x-brand />
            <span class="truncate text-sm text-stone-600">{{ auth()->user()->name }}</span>
            <form method="POST" action="{{ route('logout') }}" class="ml-auto">
                @csrf
                <button class="min-h-11 rounded-lg px-3 text-sm text-stone-600 hover:bg-stone-100">Đăng xuất</button>
            </form>
        </div>
    </header>

    <main class="mx-auto max-w-xl px-4 py-4">
        @if (session('status'))
            <p class="mb-3 rounded-lg border border-emerald-300 bg-emerald-50 px-4 py-3 text-emerald-900" role="status">{{ session('status') }}</p>
        @endif
        @error('action')
            <p class="mb-3 rounded-lg border border-red-300 bg-red-50 px-4 py-3 text-red-800" role="alert">{{ $message }}</p>
        @enderror
        @error('reason')
            <p class="mb-3 rounded-lg border border-red-300 bg-red-50 px-4 py-3 text-red-800" role="alert">{{ $message }}</p>
        @enderror

        {{ $slot }}
    </main>
</x-layouts.base>
