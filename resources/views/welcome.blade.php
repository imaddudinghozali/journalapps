<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>JournalApps</title>
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-bg text-ink">
        <div class="min-h-[100dvh] flex flex-col">
            <header class="w-full max-w-5xl mx-auto px-6 py-6 flex items-center justify-between">
                <span class="font-semibold tracking-tight">JournalApps</span>

                @if (Route::has('login'))
                    <nav class="flex items-center gap-5 text-sm">
                        @auth
                            <a href="{{ url('/dashboard') }}" class="text-ink-muted hover:text-ink transition">Dashboard</a>
                        @else
                            <a href="{{ route('login') }}" class="text-ink-muted hover:text-ink transition">Masuk</a>
                            @if (Route::has('register'))
                                <a href="{{ route('register') }}"
                                   class="inline-flex items-center rounded-md bg-accent px-4 py-2 font-medium text-accent-contrast transition hover:bg-accent-hover active:scale-[0.98]">
                                    Mulai mencatat
                                </a>
                            @endif
                        @endauth
                    </nav>
                @endif
            </header>

            <main class="flex-1 w-full max-w-5xl mx-auto px-6 flex items-center">
                <div class="py-16 md:py-24 grid gap-12 md:grid-cols-12 md:items-start">
                    <div class="md:col-span-7">
                        <h1 class="text-4xl md:text-5xl lg:text-6xl font-semibold tracking-tighter leading-[1.05]">
                            Strategimu, atau<br>kedisiplinanmu?
                        </h1>
                        <p class="mt-6 text-lg text-ink-muted leading-relaxed max-w-[52ch]">
                            Jurnal yang mencatat kriteria apa yang terpenuhi saat kamu entry, lalu menunjukkan
                            hasilnya saat kamu patuh dan saat tidak.
                        </p>
                    </div>

                    <div class="md:col-span-5 md:pt-3">
                        <dl class="divide-y divide-line border-y border-line">
                            <div class="py-4">
                                <dt class="font-medium">Rules jadi checklist</dt>
                                <dd class="mt-1 text-sm text-ink-muted">Tulis kriteria entry tiap setup, centang saat mencatat trade.</dd>
                            </div>
                            <div class="py-4">
                                <dt class="font-medium">Skor kepatuhan tersimpan</dt>
                                <dd class="mt-1 text-sm text-ink-muted">Melekat permanen pada trade itu, tidak berubah walau rules direvisi.</dd>
                            </div>
                            <div class="py-4">
                                <dt class="font-medium">Angka ditahan sampai cukup</dt>
                                <dd class="mt-1 text-sm text-ink-muted">Laporan diam sampai sampelnya memadai, daripada memberi angka yang menyesatkan.</dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </main>

            <footer class="w-full max-w-5xl mx-auto px-6 py-8 text-sm text-ink-faint">
                Jurnal pribadi. Datanya tinggal di mesinmu sendiri.
            </footer>
        </div>
    </body>
</html>
