# JournalApps

Jurnal trading yang mencatat **proses**, bukan hanya hasil. Trader mendefinisikan setup beserta rules/kriteria entry, mencentang rules itu saat mencatat setiap trade, lalu melihat korelasi antara kedisiplinan mengikuti rules dan hasil P&L.

Tujuannya menjawab satu pertanyaan yang tidak bisa dijawab jurnal biasa: **apakah saya rugi karena strateginya buruk, atau karena saya melanggar aturan saya sendiri?**

- Requirement: [`.claude/prds/trade-setup-rules.prd.md`](.claude/prds/trade-setup-rules.prd.md)
- Rencana implementasi: [`.claude/plans/trade-setup-rules.plan.md`](.claude/plans/trade-setup-rules.plan.md)

## Status

| Milestone | Status |
|---|---|
| 1 — Akun dan isolasi data | Selesai (kecuali gate coverage, lihat Catatan) |
| 2 — Katalog setup dan rules | Belum |
| 3 — Pencatatan trade dengan checklist | Belum |
| 4 — Peringatan kepatuhan rendah | Belum |
| 5 — Laporan kepatuhan vs P&L | Belum |
| 6 — Baseline dari jurnal lama | Belum |

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

## Pengujian

```bash
php artisan test
php artisan test --filter=DataIsolation
php artisan test --coverage --min=80
```

Test berjalan di SQLite in-memory (`phpunit.xml`), jadi menjalankan test tidak pernah menyentuh database MariaDB.

Coverage saat ini **82,4%** dari 41 test, di atas ambang minimum 80%.

## Catatan

**Driver coverage.** Coverage memakai PCOV 1.0.12 (`extension=pcov` di `php.ini`, DLL di `C:\xampp\php\ext\php_pcov.dll`). Kalau menyiapkan mesin baru, unduh varian yang cocok dengan build PHP-nya — XAMPP ini memakai PHP 8.2 **ZTS/Thread Safe**, jadi filenya `php_pcov-<versi>-8.2-ts-vs16-x64.zip` dari `https://downloads.php.net/~windows/pecl/releases/pcov/`. Varian `nts` tidak akan termuat.

**`.env` tidak boleh di-commit.** Sudah tercakup `.gitignore` bawaan Laravel. Gunakan `.env.example` sebagai acuan variabel.
