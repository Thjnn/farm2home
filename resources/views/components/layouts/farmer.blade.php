@props(['title' => 'Nông hộ'])
<x-layouts.base :title="$title">
    <header class="border-b border-stone-200 bg-white">
        <div class="mx-auto max-w-xl px-4 py-3 text-lg font-bold tracking-tight text-emerald-800">FARM2HOME · Nông hộ</div>
    </header>

    <main class="mx-auto max-w-xl px-4 py-4 text-lg">
        {{ $slot }}
    </main>
</x-layouts.base>
