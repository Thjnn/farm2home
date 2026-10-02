@props(['title' => 'FARM2HOME', 'bodyClass' => ''])
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} · FARM2HOME</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    {{ $head ?? '' }}
</head>
<body class="min-h-screen bg-stone-50 font-sans text-stone-900 antialiased {{ $bodyClass }}">
    {{ $slot }}
</body>
</html>
