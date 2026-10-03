<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>JournalApps — jurnal trading yang mencatat prosesnya</title>
        <meta name="description" content="Jurnal trading yang mencatat kriteria apa yang benar-benar terpenuhi saat kamu entry, lalu menunjukkan hasilnya saat kamu patuh dan saat tidak.">
        <meta property="og:title" content="JournalApps">
        <meta property="og:description" content="Rugi karena strateginya, atau karena kamu melanggarnya? Jurnal yang mencatat proses, bukan cuma hasil.">
        <meta property="og:type" content="website">
        <meta property="og:image" content="{{ url('/images/dashboard-dark.jpg') }}">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=geist:300,400,500,600,700|geist-mono:400,500" rel="stylesheet" />
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <style>
            html { scroll-behavior: smooth; }

            /* Judul terungkap baris demi baris dari balik topeng. Topengnya hanya
               menutup arah vertikal — clip-path dipakai alih-alih overflow:hidden
               supaya huruf terakhir tidak pernah terpotong di tepi kanan. */
            .mask-line { display: block; padding-bottom: 0.1em; clip-path: inset(0 -4% 0 -2%); }
            .mask-line > span { display: block; }

            @media (prefers-reduced-motion: no-preference) {
                .mask-line > span {
                    animation: naik 1s cubic-bezier(0.16, 1, 0.3, 1) both;
                    animation-delay: calc(var(--i, 0) * 110ms);
                }

                @keyframes naik {
                    from { transform: translateY(110%); }
                    to { transform: translateY(0); }
                }

                /* Bobot variable font bergerak saat masuk. */
                .berat { animation: berat 1.5s cubic-bezier(0.16, 1, 0.3, 1) both; animation-delay: 220ms; }

                @keyframes berat {
                    from { font-variation-settings: 'wght' 320; letter-spacing: 0.015em; }
                    to { font-variation-settings: 'wght' 620; letter-spacing: -0.035em; }
                }

                .reveal { animation: reveal 0.9s cubic-bezier(0.16, 1, 0.3, 1) both; }

                @keyframes reveal {
                    from { opacity: 0; transform: translateY(26px); }
                    to { opacity: 1; transform: none; }
                }

                /* Di peramban yang mendukung animasi berbasis scroll, elemen yang
                   sama ikut gerak scroll. Yang tidak mendukung tetap dapat animasi
                   masuk di atas — bukan halaman kosong. */
                @supports (animation-timeline: view()) {
                    .reveal { animation-timeline: view(); animation-range: entry 4% cover 26%; }

                    .parallax {
                        animation: parallax linear both;
                        animation-timeline: view();
                        animation-range: entry 0% exit 100%;
                    }

                    @keyframes parallax {
                        from { transform: translateY(4%); }
                        to { transform: translateY(-6%); }
                    }

                    .tilt { animation: tegak linear both; animation-timeline: view(); animation-range: entry 20% cover 45%; }

                    @keyframes tegak {
                        from { transform: perspective(2200px) rotateX(10deg) rotateY(-7deg) scale(0.96); }
                        to { transform: perspective(2200px) rotateX(0deg) rotateY(0deg) scale(1); }
                    }
                }
            }

            /* Kartu menempel lalu menumpuk saat di-scroll. Sticky murni: tidak ada
               scroll yang dibajak, tidak ada yang patah kalau JS mati. */
            .pin { position: sticky; top: 15vh; }

            .tilt { transform: perspective(2200px) rotateX(6deg) rotateY(-5deg); transform-style: preserve-3d; }

            @media (prefers-reduced-motion: reduce) {
                html { scroll-behavior: auto; }
                .tilt { transform: none; }
            }
        </style>
    </head>
    <body class="font-sans antialiased bg-bg text-ink">
        <div class="ambient" aria-hidden="true"></div>
        <div class="grain" aria-hidden="true"></div>

        <a href="#konten" class="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-[70] focus:rounded-md focus:bg-accent focus:px-4 focus:py-2 focus:text-accent-contrast">
            Lompat ke konten
        </a>

        <header class="glass sticky top-0 z-50 border-x-0 border-t-0">
            <div class="max-w-6xl mx-auto px-6 h-16 flex items-center justify-between">
                <span class="flex shrink-0 items-center gap-2 font-semibold tracking-tight">
                    <x-application-logo class="h-6 w-6 shrink-0 text-accent" />
                    {{-- Di layar sempit logonya sudah cukup; wordmark-nya menempel
                         ke tautan "Masuk" kalau dipaksa ikut. --}}
                    <span class="sr-only sm:not-sr-only">JournalApps</span>
                </span>

                @if (Route::has('login'))
                    <nav class="flex shrink-0 items-center gap-4 text-sm sm:gap-5">
                        @auth
                            <a href="{{ url('/dashboard') }}" class="whitespace-nowrap text-ink-muted hover:text-ink transition-colors">Dashboard</a>
                        @else
                            <a href="{{ route('login') }}" class="whitespace-nowrap text-ink-muted hover:text-ink transition-colors">Masuk</a>
                            @if (Route::has('register'))
                                <a href="{{ route('register') }}"
                                   class="press inline-flex items-center whitespace-nowrap rounded-full bg-accent px-4 py-2 font-medium text-accent-contrast shadow-soft transition hover:bg-accent-hover hover:shadow-lift sm:px-5">
                                    Mulai mencatat
                                </a>
                            @endif
                        @endauth
                    </nav>
                @endif
            </div>
        </header>

        <main id="konten">
            {{-- Hero: terbagi 5/7, bukan terpusat. --}}
            <section class="relative min-h-[100dvh] flex items-center overflow-hidden">
                <div class="max-w-6xl mx-auto px-6 w-full grid gap-y-16 gap-x-10 lg:grid-cols-12 lg:items-center py-24">
                    <div class="lg:col-span-6">
                        <h1 class="berat text-[clamp(2.25rem,5vw,3.5rem)] leading-[1.05] tracking-tighter">
                            <span class="mask-line" style="--i:0"><span>Strategimu,</span></span>
                            <span class="mask-line" style="--i:1"><span class="text-accent">atau kedisiplinanmu?</span></span>
                        </h1>

                        <p class="mt-7 text-lg text-ink-muted leading-relaxed max-w-[46ch] reveal" style="animation-delay:.5s">
                            Jurnal yang mencatat kriteria apa yang terpenuhi saat kamu entry, lalu menunjukkan hasilnya
                            saat kamu patuh dan saat tidak.
                        </p>

                        <div class="mt-9 flex flex-wrap items-center gap-5 reveal" style="animation-delay:.62s">
                            @if (Route::has('register'))
                                <a href="{{ route('register') }}"
                                   class="press inline-flex items-center rounded-full bg-accent px-7 py-3.5 font-medium text-accent-contrast shadow-lift transition hover:bg-accent-hover">
                                    Mulai mencatat
                                </a>
                            @endif
                            <a href="#cara" class="text-sm text-ink-muted hover:text-ink underline underline-offset-4 decoration-line transition-colors">
                                Lihat cara kerjanya
                            </a>
                        </div>
                    </div>

                    {{-- Tangkapan layar aplikasi sendiri, bukan foto stok. --}}
                    <div class="lg:col-span-6 reveal" style="animation-delay:.28s">
                        <figure class="glass spotlight rounded-2xl p-2 shadow-lift tilt">
                            <img src="/images/dashboard-dark.jpg"
                                 alt="Dashboard JournalApps: P&amp;L bersih, rata-rata per trade, kepatuhan rata-rata, grafik P&amp;L kumulatif, dan kalender yang menandai hasil tiap hari"
                                 width="1512" height="945" loading="eager" decoding="async"
                                 class="rounded-xl w-full h-auto" />
                        </figure>
                    </div>
                </div>
            </section>

            {{-- Bagian "cara": teks menempel di kiri, kartu bergulir di kanan. --}}
            <section id="cara" class="max-w-6xl mx-auto px-6 py-28">
                <div class="grid gap-14 lg:grid-cols-12">
                    <div class="lg:col-span-5">
                        <div class="pin">
                            <h2 class="text-[clamp(1.875rem,3.6vw,3rem)] font-semibold tracking-tighter leading-[1.1] text-balance">
                                Jurnal biasa mencatat hasil. Yang hilang justru prosesnya.
                            </h2>
                            <p class="mt-6 text-ink-muted leading-relaxed max-w-[42ch]">
                                Setelah serangkaian loss, pertanyaannya selalu sama: strateginya yang tidak jalan, atau
                                saya yang tidak menjalankannya? Tanpa catatan proses, jawabannya cuma tebakan.
                            </p>
                        </div>
                    </div>

                    {{-- Kartu menumpuk: tiap kartu berhenti sedikit di bawah kartu
                         sebelumnya, jadi tumpukannya terlihat. Permukaannya solid,
                         bukan kaca, supaya teks kartu di bawahnya tidak tembus. --}}
                    <div class="lg:col-span-7 space-y-6 lg:pb-[30vh]">
                        @foreach ([
                            ['01', 'Rules jadi checklist', 'Tulis kriteria entry tiap setup, beri bobot, tandai mana yang wajib. Saat mencatat trade, kamu mencentang yang benar-benar terpenuhi — bukan yang seharusnya terpenuhi.'],
                            ['02', 'Skor melekat pada trade', 'Label dan bobot rule disalin saat itu juga. Merevisi rules besok tidak akan pernah mengubah skor trade bulan lalu.'],
                            ['03', 'Angka ditahan sampai cukup', 'Laporan diam sampai sampelnya memadai. Win rate 100% dari dua trade bukan informasi, itu jebakan.'],
                            ['04', 'Revisi meninggalkan jejak', 'Checklist masih bisa diperbaiki kalau kamu salah centang, tapi perubahannya tercatat dan ikut muncul di laporan.'],
                        ] as $i => [$no, $judul, $isi])
                            {{-- Offset 3rem: cukup lebar supaya nomor kartu di bawahnya
                                 tetap terbaca di tepi tumpukan. --}}
                            <article class="spotlight lg:sticky rounded-2xl border border-line bg-surface px-8 pt-6 pb-8 shadow-lift"
                                     style="top: calc(16vh + {{ $i }} * 3rem)">
                                <span class="font-mono text-xs text-accent tracking-[0.2em]">{{ $no }}</span>
                                <h3 class="mt-3 text-xl font-semibold tracking-tight">{{ $judul }}</h3>
                                <p class="mt-2 text-ink-muted leading-relaxed max-w-[52ch]">{{ $isi }}</p>
                            </article>
                        @endforeach
                    </div>
                </div>
            </section>

            {{-- Yang sengaja tidak ada. --}}
            <section class="max-w-6xl mx-auto px-6 pb-28">
                <div class="border-t border-line pt-14 grid gap-10 md:grid-cols-12">
                    <h2 class="md:col-span-4 text-2xl font-semibold tracking-tight">
                        Yang sengaja tidak ada di sini
                    </h2>
                    <ul class="md:col-span-8 grid gap-x-10 gap-y-4 sm:grid-cols-2 text-ink-muted">
                        @foreach ([
                            'Lencana dan rentetan hari',
                            'Perayaan saat skor tinggi',
                            'Papan peringkat antar pengguna',
                            'Sinyal, rekomendasi, atau prediksi',
                        ] as $tidak)
                            <li class="flex items-start gap-3 reveal">
                                <span class="mt-2 h-px w-4 shrink-0 bg-line" aria-hidden="true"></span>
                                <span>{{ $tidak }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </section>

            {{-- Penutup. --}}
            <section class="max-w-6xl mx-auto px-6 pb-28">
                <div class="glass spotlight rounded-3xl px-8 py-16 md:px-16 md:py-20 text-center reveal">
                    <h2 class="text-[clamp(1.875rem,4vw,3rem)] font-semibold tracking-tighter leading-[1.1] max-w-[22ch] mx-auto text-balance">
                        Mulai dari satu trade yang kamu catat jujur.
                    </h2>
                    <p class="mt-5 text-ink-muted max-w-[52ch] mx-auto leading-relaxed">
                        Tidak ada yang perlu kamu tunjukkan ke siapa pun. Cuma catatan yang bisa kamu percaya ketika
                        harus mengambil keputusan berikutnya.
                    </p>

                    @if (Route::has('register'))
                        <a href="{{ route('register') }}"
                           class="press mt-9 inline-flex items-center rounded-full bg-accent px-8 py-4 font-medium text-accent-contrast shadow-lift transition hover:bg-accent-hover">
                            Mulai mencatat
                        </a>
                    @endif
                </div>
            </section>
        </main>

        <footer class="border-t border-line">
            <div class="max-w-6xl mx-auto px-6 py-10 flex flex-wrap items-center justify-between gap-4 text-sm text-ink-faint">
                <span>Jurnal pribadi. Datanya tinggal di mesinmu sendiri.</span>
                <span class="font-mono text-xs">JournalApps</span>
            </div>
        </footer>

        <script>
            // Sorotan tepi mengikuti kursor. Hanya menulis properti kustom, tidak
            // menyentuh layout, jadi tidak memicu reflow.
            if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                document.querySelectorAll('.spotlight').forEach(function (el) {
                    el.addEventListener('pointermove', function (e) {
                        var r = el.getBoundingClientRect();
                        el.style.setProperty('--mx', (e.clientX - r.left) + 'px');
                        el.style.setProperty('--my', (e.clientY - r.top) + 'px');
                    });
                });
            }
        </script>
    </body>
</html>
