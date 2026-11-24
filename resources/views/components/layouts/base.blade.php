@props(['title' => 'FARM2HOME', 'bodyClass' => ''])
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} · FARM2HOME</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="48x48">
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}" sizes="256x256">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <meta name="theme-color" content="#047857">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="Farm2Home">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@600;700&display=swap">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    {{ $head ?? '' }}
</head>
<body class="min-h-screen bg-stone-50 font-sans text-stone-900 antialiased {{ $bodyClass }}">
    {{ $slot }}

    {{-- Lên đầu trang: hiện khi đã cuộn quá 400px. Trang có thanh dưới đáy đặt --back-to-top-offset để đẩy nút lên. --}}
    <button type="button" x-data="backToTop" x-show="visible" x-cloak @click="toTop()"
        x-transition:enter="transition duration-200 motion-reduce:transition-none" x-transition:enter-start="opacity-0 translate-y-2"
        x-transition:leave="transition duration-150 motion-reduce:transition-none" x-transition:leave-end="opacity-0 translate-y-2"
        aria-label="Lên đầu trang" title="Lên đầu trang"
        style="bottom: calc(var(--back-to-top-offset, 1rem) + env(safe-area-inset-bottom))"
        class="fixed right-4 z-30 flex size-12 items-center justify-center rounded-full border border-stone-200 bg-white/95 text-emerald-800 shadow-lg backdrop-blur hover:-translate-y-0.5 hover:bg-emerald-50 active:scale-95 motion-reduce:hover:translate-y-0 motion-reduce:active:scale-100 print:hidden">
        {{-- Thân mũi tên vẽ từ dưới lên (0,3s) rồi tới đầu mũi tên (0,2s); JS gọi beginElement() mỗi lần nút hiện. --}}
        <svg x-ref="arrow" class="size-6" viewBox="0 0 24 24" aria-hidden="true">
            <g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2">
                <path stroke-dasharray="16" stroke-dashoffset="16" d="M12 19l0 -13.5">
                    <animate begin="indefinite" fill="freeze" attributeName="stroke-dashoffset" dur="0.3s" values="16;0"/>
                </path>
                <path stroke-dasharray="10" stroke-dashoffset="10" d="M12 5l5 5M12 5l-5 5">
                    <animate begin="indefinite" fill="freeze" attributeName="stroke-dashoffset" dur="0.5s" values="10;10;0" keyTimes="0;0.6;1"/>
                </path>
            </g>
        </svg>
    </button>
</body>
</html>
