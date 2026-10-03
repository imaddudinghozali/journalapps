<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        @php
            $namaApl = config('app.name', 'JournalApps');
            $judulHalaman = ($title ?? null) ? $title.' — '.$namaApl : $namaApl;
            $deskripsiHalaman = ($description ?? null)
                ?: 'Jurnal trading yang mencatat kriteria apa yang terpenuhi saat kamu entry, lalu menunjukkan hasilnya saat kamu patuh dan saat tidak.';
        @endphp

        <title>{{ $judulHalaman }}</title>
        <meta name="description" content="{{ $deskripsiHalaman }}">

        {{-- Halaman aplikasi ada di balik autentikasi, jadi tidak ada yang bisa
             dibagikan ke publik. robots dipasang supaya kalaupun tautannya
             tersebar, isinya tidak ikut terindeks. --}}
        <meta name="robots" content="noindex, nofollow">

        <!-- Fonts -->
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=geist:300,400,500,600,700|geist-mono:400,500" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        <div class="ambient" aria-hidden="true"></div>
        <div class="grain" aria-hidden="true"></div>

        <a href="#konten"
           class="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-[70] focus:rounded-md focus:bg-accent focus:px-4 focus:py-2 focus:text-accent-contrast">
            Lompat ke konten
        </a>
        <div class="min-h-screen bg-bg">
            <livewire:layout.navigation />

            <!-- Page Heading -->
            @if (isset($header))
                <header class="bg-surface shadow">
                    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endif

            <!-- Page Content -->
            <main id="konten">
                {{ $slot }}
            </main>
        </div>
    </body>
</html>
