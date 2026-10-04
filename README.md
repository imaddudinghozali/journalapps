# JournalApps

Jurnal trading yang mencatat **proses**, bukan hanya hasil. Trader mendefinisikan setup beserta rules/kriteria entry, mencentang rules itu saat mencatat setiap trade, lalu melihat korelasi antara kedisiplinan mengikuti rules dan hasil P&L.

Tujuannya menjawab satu pertanyaan yang tidak bisa dijawab jurnal biasa: **apakah saya rugi karena strateginya buruk, atau karena saya melanggar aturan saya sendiri?**

- Requirement: [`.claude/prds/trade-setup-rules.prd.md`](.claude/prds/trade-setup-rules.prd.md)
- Rencana implementasi: [`.claude/plans/trade-setup-rules.plan.md`](.claude/plans/trade-setup-rules.plan.md)

## Status

| Milestone | Status |
|---|---|
| 1 — Akun dan isolasi data | Selesai |
| 2 — Katalog setup dan rules | Selesai |
| 3 — Pencatatan trade dengan checklist | Selesai |
| 4 — Peringatan kepatuhan rendah | Selesai |
| 5 — Laporan kepatuhan vs P&L | Selesai |
| 6 — Baseline dari jurnal lama | Dibatalkan — datanya tidak ada |

## Yang belum dikerjakan

Diurutkan dari yang paling menghalangi, bukan dari yang paling mudah.

### Menghalangi rilis

| # | Hal | Kondisi sekarang | Akibat kalau dibiarkan |
|---|---|---|---|
| 1 | Pengirim email | `MAIL_MAILER=log` | Verifikasi email aktif, jadi **setiap orang yang mendaftar terkunci selamanya** di layar "cek emailmu" — tanpa satu pun pesan galat muncul. Reset sandi juga mati total. Kegagalan paling senyap di daftar ini |
| 2 | Mode debug | `APP_ENV=local`, `APP_DEBUG=true` | Halaman galat menampilkan stack trace, query SQL beserta parameternya, dan seluruh isi `.env` termasuk `APP_KEY` |
| 3 | Pengguna basis data | `root` tanpa sandi | Kredensial bawaan; butuh pengguna khusus berhak minimum |

Langkah lengkap ketiganya ada di [DEPLOY.md](DEPLOY.md). Semuanya butuh server
tujuan dan kredensial, jadi tidak bisa diselesaikan dari repo saja.

### Belum ada sama sekali

| Hal | Catatan |
|---|---|
| **Cadangan basis data** | Tidak ada apa pun. Jurnal bertahun-tahun yang hilang tidak bisa dibuat ulang dari mana pun. Ini risiko terbesar yang tidak masuk daftar blocker |
| **Data nyata** | Hipotesis produknya — kepatuhan berkorelasi dengan P&L — belum diuji dengan sampel trade sungguhan yang memadai. Kondisi datanya ada di baris berikutnya |
| **Memilah trade asli dari trade demo** | Akun pemilik (`imadlionel27@gmail.com`) berisi **50 trade: 4 asli bercampur 46 hasil `DemoTradesSeeder`**, atas keputusan sadar supaya tata letak dashboard bisa dinilai dengan data yang penuh. Keempat trade asli belum ditandai apa pun, jadi memilahnya nanti harus manual — lewat tanggal catat. Akun fixture `uji@localhost.test` juga sudah berisi demo (59 trade). **Sebelum rilis, keduanya harus dibersihkan** dan akun fixture dihapus |
| **Batas atas tanggal tutup** | `closedAt` divalidasi `['nullable','date','after_or_equal:openedAt','required_with:exitPrice']` — tanpa batas atas, jadi trade bisa dicatat tertutup di masa depan. Akun fixture sudah punya 4 trade seperti itu dari uji manual. Akibatnya nyata di dashboard: trade masa depan ikut terhitung pada "Minggu ini"/"Bulan ini" dan muncul di kalender sebagai hari yang belum terjadi |
| **Tampilan detail trade** | Hanya ada `/trades/{id}/edit`. Untuk sekadar melihat, pengguna harus masuk ke mode ubah |
| **Mata uang akun** | Angka uang diasumsikan dolar. Kalau ada pengguna yang akunnya bukan USD, ini harus jadi pengaturan, bukan simbol yang dipatri di template |

### Terjemahan yang belum selesai

Halaman login, registrasi, lupa sandi, verifikasi email, dan dua bagian di
`/profile` masih memakai teks Inggris bawaan Breeze, padahal CLAUDE.md
mewajibkan Bahasa Indonesia untuk teks antarmuka.

### Sudah selesai dan tidak perlu diulang

Seluruh 10 task pembenahan UI/UX ([`.claude/plans/ui-redesign.plan.md`](.claude/plans/ui-redesign.plan.md)),
plus empat blocker rilis: pembatasan laju pendaftaran, kebijakan privasi,
pengerasan cookie sesi, dan `.env.production.example`.

## Stack

| Komponen | Versi |
|---|---|
| PHP | 8.2 (XAMPP) |
| Laravel | 12.x |
| Livewire | 3.x (via Laravel Breeze) |
| Database | MariaDB 10.4 (XAMPP) |
| Test | Pest 3 |
| Build aset | Vite + Tailwind |

Laravel 13 **tidak dipakai** karena menuntut PHP ^8.3 sementara XAMPP di mesin pengembangan memakai PHP 8.2.

## Menjalankan dari nol

Prasyarat: XAMPP dengan PHP 8.2, Composer, Node.js.

**1. Aktifkan ekstensi `zip` PHP.** Buka `C:\xampp\php\php.ini`, cari `;extension=zip`, hapus tanda `;` di depannya. Tanpa ini Composer tidak bisa mengekstrak paket.

```bash
php -m | grep zip
```

**2. Jalankan MariaDB.** Lewat XAMPP Control Panel (tombol Start pada baris MySQL), atau langsung:

```bash
C:\xampp\mysql\bin\mysqld.exe --defaults-file=C:\xampp\mysql\bin\my.ini --standalone
```

**3. Buat database.**

```bash
C:\xampp\mysql\bin\mysql.exe -u root -e "CREATE DATABASE journalapps CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

**4. Pasang dependensi dan siapkan aplikasi.**

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm run build
```

**5. Jalankan.**

```bash
php artisan serve
```

Buka `http://127.0.0.1:8000`, lalu daftar akun baru lewat halaman Register.

## Memakai sehari-hari

**1. Nyalakan MySQL** dari XAMPP Control Panel (tombol Start pada baris MySQL). Tidak terpasang sebagai service, jadi harus dinyalakan tiap kali.

**2. Jalankan aplikasinya.**

```bash
php artisan serve
```

Buka `http://127.0.0.1:8000`, lalu daftar akun.

**3. Lewati verifikasi email (hanya untuk dev lokal).** Verifikasi diwajibkan karena aplikasi ini menyimpan data finansial, tapi di lokal emailnya hanya ditulis ke log. Daripada menggali `storage/logs/laravel.log`, tandai akun yang **baru saja kamu daftarkan** sebagai terverifikasi:

```bash
php artisan tinker --execute="App\Models\User::latest()->first()->markEmailAsVerified();"
```

**4. Urutan pakainya.** Buat setup di `/setups` lengkap dengan rules-nya, catat trade di `/trades`, lihat hasilnya di `/reports`.

Laporan baru menampilkan angka setelah ada **10 trade tertutup** per kelompok. Di bawah itu hanya jumlah sampel yang muncul — itu disengaja, bukan bug.

### Kalau pencatatannya terasa berat

Itu sinyal, bukan kegagalan. Catat apa yang terasa menghambat — field yang tidak perlu, langkah yang berulang, checklist yang kepanjangan. Itu bahan perbaikan yang jauh lebih berguna daripada menebak-nebak.

## Pengujian

```bash
php artisan test
php artisan test --filter=DataIsolation
php artisan test --coverage --min=80
```

Test berjalan di SQLite in-memory (`phpunit.xml`), jadi menjalankan test tidak pernah menyentuh database MariaDB.

Coverage saat ini **95,7%** dari 244 test, di atas ambang minimum 80%.

## Fitur yang sudah jalan

- **Akun** — registrasi, login, verifikasi email, reset password, kelola profil
- **Katalog setup** (`/setups`) — buat setup trading, kelola rules berbobot (1–5) dengan penanda wajib, arsipkan dan pulihkan. Maksimal 15 rule aktif per setup
- **Jurnal trade** (`/trades`) — catat trade, centang checklist rules, skor kepatuhan dihitung otomatis dan tersimpan bersama snapshot rule. Peringatan muncul bila kepatuhan di bawah ambangmu, tapi tidak pernah memblokir penyimpanan
- **Laporan** (`/reports`) — win rate dan expectancy (dalam R) dipecah per setup dan per tingkat kepatuhan, plus rule mana yang paling sering dilanggar dan bagaimana hasilnya berbeda. Angka disembunyikan sampai sampelnya cukup
- **Ambang peringatan** — diatur sendiri di halaman profil
- **Isolasi data** — setiap akun hanya melihat datanya sendiri, ditegakkan global scope dan policy, bukan filter manual

## Catatan

**Driver coverage.** Coverage memakai PCOV 1.0.12 (`extension=pcov` di `php.ini`, DLL di `C:\xampp\php\ext\php_pcov.dll`). Kalau menyiapkan mesin baru, unduh varian yang cocok dengan build PHP-nya — XAMPP ini memakai PHP 8.2 **ZTS/Thread Safe**, jadi filenya `php_pcov-<versi>-8.2-ts-vs16-x64.zip` dari `https://downloads.php.net/~windows/pecl/releases/pcov/`. Varian `nts` tidak akan termuat.

**Mau dipakai orang lain?** Baca [DEPLOY.md](DEPLOY.md) lebih dulu. Ada lima hal yang aman di mesin sendiri tapi tidak aman begitu ada orang lain mendaftar, dan satu di antaranya (`MAIL_MAILER=log`) membuat setiap pendaftar baru terkunci selamanya tanpa pesan galat apa pun.

**Akun fixture lokal.** `uji@localhost.test` ("Trader Uji") berisi trade sintetis untuk memeriksa tampilan, dan sandinya `password-uji-lokal`. Akun ini hanya ada di database dev di mesin ini — tidak pernah di-seed, tidak pernah dikirim ke mana pun, dan tidak boleh ada di instalasi mana pun selain dev lokal. Kalau aplikasi ini dirilis, hapus akunnya lebih dulu.

**`.env` tidak boleh di-commit.** Sudah tercakup `.gitignore` bawaan Laravel. Gunakan `.env.example` sebagai acuan variabel.
