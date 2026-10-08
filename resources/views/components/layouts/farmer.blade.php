@props(['title' => 'Nông hộ'])
<x-layouts.base :title="$title">
    <header class="border-b border-stone-200 bg-white">
        <div class="mx-auto flex max-w-xl items-center gap-3 px-4 py-2"><img src="{{ asset('images/logo.jpg') }}" alt="FARM2HOME" width="269" height="70" class="h-10 w-auto rounded-md"><span class="text-lg font-bold text-emerald-800">· Nông hộ</span></div>
    </header>

    <main class="mx-auto max-w-xl px-4 py-4 text-lg">
        {{ $slot }}
    </main>
</x-layouts.base>
