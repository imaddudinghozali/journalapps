{{--
    Halaman jalan buntu.

    Tidak memakai x-app-layout: layout itu memuat navigasi yang membaca
    auth()->user(), dan 404 juga harus bisa tampil untuk tamu. Jadi ia berdiri
    sendiri, dengan bahasa visual yang sama.

    Tautan keluarnya mengikuti siapa yang melihat. Menawarkan Dashboard kepada
    tamu hanya akan melemparnya ke halaman masuk - jalan buntu kedua setelah
    yang pertama.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>Halaman tidak ditemukan — {{ config('app.name', 'JournalApps') }}</title>
        <meta name="robots" content="noindex, nofollow">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=geist:300,400,500,600,700|geist-mono:400,500" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-bg text-ink">
        <div class="ambient" aria-hidden="true"></div>
        <div class="grain" aria-hidden="true"></div>

        <main class="min-h-[100dvh] flex items-center">
            <div class="mx-auto w-full max-w-xl px-6 py-20">
                <p class="font-mono text-xs tracking-[0.25em] text-accent">404</p>

                <h1 class="mt-4 text-3xl sm:text-4xl font-semibold tracking-tighter">
                    Halaman ini tidak ada
                </h1>

                <p class="mt-4 text-ink-muted leading-relaxed">
                    Mungkin tautannya salah ketik, atau isinya sudah dihapus. Tidak ada yang rusak di
                    catatanmu.
                </p>

                <div class="mt-9 flex flex-wrap items-center gap-4">
                    @auth
                        <a href="{{ route('dashboard') }}" wire:navigate
                           class="press inline-flex items-center rounded-full bg-accent px-6 py-3 font-medium text-accent-contrast shadow-lift transition hover:bg-accent-hover">
                            Kembali ke dashboard
                        </a>
                        <a href="{{ route('trades') }}" wire:navigate
                           class="text-sm text-ink-muted underline underline-offset-4 hover:text-ink transition-colors">
                            Buka jurnal trade
                        </a>
                    @else
                        <a href="{{ url('/') }}"
                           class="press inline-flex items-center rounded-full bg-accent px-6 py-3 font-medium text-accent-contrast shadow-lift transition hover:bg-accent-hover">
                            Kembali ke halaman depan
                        </a>
                    @endauth
                </div>
            </div>
        </main>
    </body>
</html>
