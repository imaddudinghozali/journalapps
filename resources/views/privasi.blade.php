{{--
    Kebijakan privasi.

    Berdiri sendiri seperti halaman 404: harus bisa dibuka tamu, dan layout
    aplikasi memuat navigasi yang membaca auth()->user().

    Isinya ditulis sebagai pernyataan tentang apa yang aplikasi ini BENAR-BENAR
    lakukan hari ini, bukan boilerplate. Setiap kalimat di bawah bisa dicek
    terhadap kodenya. Kalau perilakunya berubah, halaman ini ikut berubah.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>Kebijakan privasi — {{ config('app.name', 'JournalApps') }}</title>
        <meta name="description" content="Data apa yang disimpan JournalApps, untuk apa, dan bagaimana menghapusnya.">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=geist:300,400,500,600,700|geist-mono:400,500" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-bg text-ink">
        <div class="ambient" aria-hidden="true"></div>
        <div class="grain" aria-hidden="true"></div>

        <main class="mx-auto w-full max-w-2xl px-6 py-16 sm:py-24">
            <a href="{{ url('/') }}" class="text-sm text-ink-muted underline underline-offset-4 hover:text-ink transition-colors">
                Kembali ke halaman depan
            </a>

            <h1 class="mt-8 text-3xl sm:text-4xl font-semibold tracking-tighter">Kebijakan privasi</h1>
            <p class="mt-3 text-sm text-ink-faint">Terakhir diperbarui 3 Oktober 2026.</p>

            <div class="mt-10 space-y-10">
                <section>
                    <h2 class="text-xl font-semibold tracking-tight">Data yang disimpan</h2>
                    <ul class="mt-3 space-y-2 text-ink-muted leading-relaxed">
                        <li><strong class="text-ink">Akun</strong> — nama, alamat email, dan sandi yang disimpan dalam bentuk hash, bukan teks biasa.</li>
                        <li><strong class="text-ink">Isi jurnal</strong> — setup dan rules yang kamu tulis, trade yang kamu catat beserta instrumen, arah, ukuran lot, harga, waktu, catatan, dan jawaban checklist-mu.</li>
                        <li><strong class="text-ink">Pengaturan</strong> — ambang peringatan kepatuhanmu.</li>
                    </ul>
                    <p class="mt-3 text-ink-muted leading-relaxed">
                        Tidak ada data keuangan pihak ketiga yang diambil. Aplikasi ini tidak terhubung ke broker mana
                        pun dan tidak pernah meminta kredensial akun tradingmu.
                    </p>
                </section>

                <section>
                    <h2 class="text-xl font-semibold tracking-tight">Untuk apa</h2>
                    <p class="mt-3 text-ink-muted leading-relaxed">
                        Hanya untuk menampilkan kembali jurnal dan laporanmu sendiri. Datanya tidak dipakai melatih
                        model apa pun, tidak dijual, tidak dibagikan ke pengiklan, dan tidak dikirim ke layanan analitik
                        pihak ketiga.
                    </p>
                </section>

                <section>
                    <h2 class="text-xl font-semibold tracking-tight">Siapa yang bisa melihatnya</h2>
                    <p class="mt-3 text-ink-muted leading-relaxed">
                        Kamu. Setiap kueri ke data jurnal dibatasi ke akun yang sedang masuk oleh satu mekanisme di
                        tingkat basis data dan satu lagi di tingkat aplikasi, dan batasan itu diuji otomatis setiap
                        kali kodenya berubah.
                    </p>
                    <p class="mt-3 text-ink-muted leading-relaxed">
                        Administrator server secara teknis punya akses ke basis data, seperti pada layanan mana pun
                        yang kamu titipi data.
                    </p>
                </section>

                <section>
                    <h2 class="text-xl font-semibold tracking-tight">Cookie</h2>
                    <p class="mt-3 text-ink-muted leading-relaxed">
                        Satu cookie sesi untuk menjaga kamu tetap masuk, dan satu token untuk mencegah pemalsuan
                        permintaan lintas situs. Keduanya diperlukan agar aplikasinya berfungsi. Tidak ada cookie
                        pelacak dan tidak ada cookie pihak ketiga, jadi tidak ada spanduk persetujuan yang perlu
                        kamu klik.
                    </p>
                </section>

                <section>
                    <h2 class="text-xl font-semibold tracking-tight">Menghapus datamu</h2>
                    <p class="mt-3 text-ink-muted leading-relaxed">
                        Halaman Profil punya tombol hapus akun. Menghapus akun ikut menghapus seluruh setup, rules,
                        trade, dan checklist milikmu. Penghapusan itu permanen dan tidak ada salinan cadangan yang
                        bisa dipulihkan atas permintaan.
                    </p>
                </section>

                <section>
                    <h2 class="text-xl font-semibold tracking-tight">Perubahan</h2>
                    <p class="mt-3 text-ink-muted leading-relaxed">
                        Kalau yang disimpan atau cara memakainya berubah, halaman ini diperbarui dan tanggal di atas
                        ikut berubah.
                    </p>
                </section>
            </div>
        </main>
    </body>
</html>
