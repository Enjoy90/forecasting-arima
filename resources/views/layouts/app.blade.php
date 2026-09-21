<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        <div x-data="{ sidebarOpen: false }" class="flex h-screen overflow-hidden bg-gray-50">
            {{-- Menu dipecah per modul di dalam partial ini, lihat docs/04-pembagian-modul.md --}}
            @include('partials.sidebar')

            <div class="flex min-w-0 flex-1 flex-col overflow-hidden">
                @include('layouts.navigation')

                <main class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8">
                    {{ $slot }}
                </main>
            </div>
        </div>

        {{-- Tempat halaman menyisipkan skrip sendiri, mis. baris dinamis
             pada form transaksi: @push('skrip') ... @endpush --}}
        @stack('skrip')
    </body>
</html>
