# Plan: Trade Setup Rules

**Source PRD**: `.claude/prds/trade-setup-rules.prd.md`
**Selected Milestone**: 1 — Akun dan isolasi data
**Complexity**: Medium

## Summary

Membangun fondasi aplikasi: proyek Laravel baru di `C:\xampp\htdocs\journalapps`, autentikasi multi-user (registrasi, login, logout), dan — bagian terpentingnya — **mekanisme isolasi data antar pengguna yang dibangun lebih dulu sebagai infrastruktur**, bukan ditambahkan belakangan di setiap model. PRD menempatkan isolasi sebagai milestone pertama karena data P&L adalah informasi finansial pribadi; mekanismenya harus sudah ada dan teruji sebelum tabel domain pertama (setup, rules, trade) dibuat di milestone 2.

Milestone ini tidak membuat tabel domain apa pun. Keluarannya: pengguna bisa mendaftar dan masuk, dan ada satu pola kepemilikan data yang sudah terbukti lewat tes kebocoran lintas akun yang nyata.

## Verified Environment

Diverifikasi langsung di mesin ini, bukan asumsi:

| Komponen | Terpasang | Implikasi |
|---|---|---|
| PHP | 8.2.12 (`C:\xampp\php`) | **Laravel 13 butuh PHP ^8.3 — tidak bisa dipakai.** Plafon versi adalah Laravel 12 (butuh PHP ^8.2) |
| Composer | 2.9.7 | OK |
| MariaDB | 10.4.32 (`C:\xampp\mysql\bin\mysql.exe`, tidak ada di PATH) | Didukung Laravel 12. Perintah CLI harus memakai path penuh |
| Node / npm | 24.11.1 / 11.17.0 | Cukup untuk Vite + Tailwind |
| Ekstensi PHP | `pdo_mysql`, `mbstring`, `openssl`, `curl`, `fileinfo`, `tokenizer`, `xml` ada | OK |
| Ekstensi `zip` | **Ada DLL tapi mati** — `php.ini:962` masih `;extension=zip` | Harus diaktifkan sebelum `composer create-project`, kalau tidak ekstraksi paket jatuh ke binary eksternal |
| Coverage driver | **Tidak ada** — tidak ada Xdebug maupun PCOV di `C:\xampp\php\ext` | Aturan coverage 80% **tidak bisa diukur** sampai PCOV atau Xdebug dipasang. Lihat Risks |

## Technology Decisions

| Keputusan | Pilihan | Alasan |
|---|---|---|
| Framework | Laravel **12.x** | Laravel 13 butuh PHP 8.3; mesin ini PHP 8.2.12. Pakai 13 berarti harus upgrade PHP XAMPP lebih dulu |
| UI | **Livewire 3** (mendukung Laravel 10–13, PHP ^8.1) | Checklist rules dan skor kepatuhan live tanpa membangun SPA terpisah |
| Auth scaffolding | **Laravel Breeze**, stack Livewire (mendukung Laravel 11–13, PHP ^8.2.0) | Memberi registrasi/login/reset password yang sudah teruji; tidak menulis auth sendiri |
| Test framework | **Pest 3** | Sintaks ringkas; berjalan di atas PHPUnit sehingga tetap kompatibel dengan tooling standar |
| Isolasi data | Trait kepemilikan + global scope + Policy, bukan filter `where('user_id', ...)` manual di controller | Filter manual adalah pola yang bocor begitu ada satu query yang lupa difilter. PRD menilai dampak kebocoran sebagai Kritis |

## Patterns to Mirror

| Category | Source | Pattern |
|---|---|---|
| Naming | — | **Tidak ada kode yang bisa dijadikan acuan.** Repo `journalapps` kosong total (`find` hanya menemukan PRD). Konvensi akan mengikuti default Laravel 12 yang dihasilkan scaffolding, bukan pola buatan sendiri |
| Errors | — | Belum ada. Akan mengikuti exception handling default Laravel + `authorize()` pada Policy |
| Logging | — | Belum ada. Default channel `stack` dari Laravel |
| Data access | — | Belum ada. Eloquent dengan global scope kepemilikan, ditetapkan di Task 4 sebagai pola pertama |
| Tests | — | Belum ada. Breeze membawa feature test auth; Pest akan dijadikan acuan gaya tes |

Tidak ada pola yang ditiru karena tidak ada apa pun untuk ditiru. Scaffolding resmi Laravel 12 dan Breeze menjadi sumber konvensi, dan keputusan ini disengaja agar milestone berikutnya punya acuan yang konsisten.

## Files to Change

Mayoritas berkas dihasilkan oleh scaffolding. Tabel ini hanya memuat yang **kita tulis atau ubah sendiri**:

| File | Action | Why |
|---|---|---|
| `.env` | UPDATE | Koneksi MariaDB (`DB_DATABASE=journalapps`), `APP_NAME`, `APP_URL` |
| `.env.example` | UPDATE | Agar developer lain tahu variabel yang dibutuhkan; tanpa nilai rahasia |
| `app/Models/Concerns/BelongsToUser.php` | CREATE | Trait kepemilikan: relasi `user()`, auto-isi `user_id` saat create, global scope pembatas |
| `app/Models/Scopes/OwnedByUserScope.php` | CREATE | Global scope yang membatasi setiap query ke pengguna yang sedang masuk |
| `app/Policies/OwnedRecordPolicy.php` | CREATE | Policy dasar: hanya pemilik yang boleh view/update/delete. Diwarisi policy domain di milestone 2 |
| `app/Providers/AppServiceProvider.php` | UPDATE | Registrasi policy dasar dan `Model::shouldBeStrict()` untuk mode dev |
| `tests/Pest.php` | UPDATE | Helper `actingAsUser()` dan binding `TestCase` untuk feature test |
| `tests/Feature/Auth/*` | UPDATE | Tes auth dari Breeze — dipakai apa adanya, hanya disesuaikan kalau redirect berbeda |
| `tests/Feature/DataIsolationTest.php` | CREATE | Tes kebocoran lintas akun yang nyata, memakai model fixture (lihat Task 5) |
| `tests/Fixtures/OwnedThing.php` | CREATE | Model fixture khusus tes untuk memvalidasi mekanisme kepemilikan sebelum tabel domain ada |
| `database/migrations/*_create_owned_things_table.php` | CREATE | Migrasi fixture, **hanya dijalankan di environment testing** |
| `.gitignore` | UPDATE | Pastikan `.env` dan `/vendor` terabaikan sebelum commit pertama |
| `README.md` | CREATE | Cara menjalankan di XAMPP: aktifkan `zip`, buat database, `migrate`, `npm run dev` |
| `CLAUDE.md` | CREATE | Konvensi proyek agar sesi berikutnya tidak menebak-nebak stack dan pola isolasi |

## Tasks

### Task 1: Siapkan prasyarat lingkungan
- **Action**: Aktifkan `extension=zip` di `C:\xampp\php\php.ini` baris 962 (hapus `;`). Pastikan layanan MySQL XAMPP berjalan. Buat database `journalapps` dengan charset `utf8mb4` dan collation `utf8mb4_unicode_ci`.
- **Mirror**: Tidak ada pola — konfigurasi lingkungan.
- **Validate**: `php -m | grep zip` memunculkan `zip`; `C:\xampp\mysql\bin\mysql.exe -u root -e "SHOW DATABASES LIKE 'journalapps'"` menampilkan satu baris.
- **Catatan**: mengubah `php.ini` berada di luar folder proyek. Perlu persetujuan eksplisit sebelum dilakukan.

### Task 2: Bootstrap proyek Laravel 12
- **Action**: `composer create-project laravel/laravel . "^12.0"` di dalam `C:\xampp\htdocs\journalapps` (folder kosong, aman). Set `.env` ke MariaDB. Jalankan `php artisan key:generate` dan `php artisan migrate` untuk memastikan koneksi hidup.
- **Mirror**: Struktur direktori default Laravel 12 — tidak ada penyimpangan.
- **Validate**: `php artisan migrate:status` menampilkan migrasi `users`, `cache`, `jobs` dalam status Ran; `php artisan about` menampilkan versi 12.x.

### Task 3: Pasang autentikasi via Breeze (stack Livewire)
- **Action**: `composer require laravel/breeze --dev` lalu `php artisan breeze:install livewire`, kemudian `npm install && npm run build`. Jangan menulis controller auth sendiri.
- **Mirror**: Scaffolding Breeze apa adanya; perubahan hanya pada teks antarmuka bila perlu.
- **Validate**: `php artisan test --filter=Auth` hijau (tes registrasi, login, logout, reset password bawaan Breeze lolos).

### Task 4: Bangun mekanisme kepemilikan data
- **Action**: Buat `BelongsToUser` (relasi + auto-isi `user_id` pada event `creating` + penerapan global scope), `OwnedByUserScope` (membatasi query ke `Auth::id()`, dan **melempar exception bila dipakai tanpa pengguna terautentikasi** alih-alih mengembalikan seluruh baris), dan `OwnedRecordPolicy`. Aktifkan `Model::shouldBeStrict()` saat `local` agar lazy-loading dan atribut hilang terdeteksi dini.
- **Mirror**: Konvensi Eloquent scope & policy resmi Laravel. Ini menjadi pola rujukan untuk seluruh model domain di milestone 2.
- **Validate**: lihat Task 5 — mekanisme ini tidak dianggap selesai sebelum tes kebocoran lolos.
- **Keputusan desain penting**: scope **gagal keras (throw)** saat tidak ada pengguna terautentikasi. Opsi alternatif — mengembalikan query kosong — terlihat lebih aman tapi menyembunyikan bug menjadi "data hilang tanpa sebab", yang jauh lebih sulit didiagnosis. Mode fail-loud dipilih dengan sengaja, dan harus dikecualikan untuk konteks CLI/queue/seeder.

### Task 5: Tes kebocoran lintas akun (menulis tes lebih dulu)
- **Action**: Tulis `DataIsolationTest` **sebelum** Task 4 dianggap selesai, mengikuti alur RED → GREEN. Model fixture `OwnedThing` dipakai karena tabel domain belum ada di milestone ini — mekanismenya diuji nyata tanpa harus memalsukan fitur yang belum dibuat. Kasus yang harus tertutup:
  1. User A membuat record; User B melakukan `all()` → tidak mendapat record milik A.
  2. User B mengakses record A lewat ID langsung (`find`) → tidak ketemu / 404, bukan 403 (tidak membocorkan keberadaan record).
  3. User B mencoba update dan delete record A → ditolak Policy.
  4. Record dibuat tanpa mengisi `user_id` secara manual → `user_id` terisi otomatis dari pengguna yang masuk.
  5. Query dijalankan tanpa pengguna terautentikasi → melempar exception, bukan mengembalikan semua baris.
  6. Pengguna yang belum masuk mengakses rute terproteksi → diarahkan ke login.
- **Mirror**: Gaya feature test Breeze + sintaks Pest.
- **Validate**: `php artisan test` seluruhnya hijau; setiap kasus di atas gagal lebih dulu saat mekanisme belum ada (bukti tes benar-benar menguji sesuatu).

### Task 6: Dokumentasi proyek
- **Action**: Tulis `README.md` (langkah setup XAMPP, termasuk mengaktifkan `zip`) dan `CLAUDE.md` (stack, plafon PHP 8.2 → Laravel 12, pola `BelongsToUser` wajib untuk setiap model domain baru).
- **Mirror**: Tidak ada — berkas pertama jenisnya.
- **Validate**: Developer lain bisa mengikuti README dari nol sampai `php artisan test` hijau tanpa bertanya.

### Task 7: Pasang coverage driver (menentukan apakah gate 80% bisa ditegakkan)
- **Action**: Pasang PCOV (lebih ringan dari Xdebug untuk coverage) atau Xdebug untuk PHP 8.2 x64 — catatan: build PHP ini **ZTS/Thread Safe**, jadi DLL yang diunduh harus versi TS, bukan NTS. Tanpa ini, perintah `--coverage` gagal.
- **Mirror**: Tidak ada.
- **Validate**: `php artisan test --coverage` menghasilkan laporan, bukan error "No code coverage driver available".
- **Catatan**: dipisah sebagai task terakhir agar milestone tidak terblokir oleh urusan lingkungan. Kalau task ini dilewati, aturan coverage 80% ECC **tidak terverifikasi** dan itu harus dinyatakan terbuka, bukan dianggap lolos.

## Validation

```bash
php -m | grep -E "zip|pdo_mysql"
php artisan about
php artisan migrate:status
php artisan test
php artisan test --filter=DataIsolation
php artisan test --coverage --min=80
npm run build
```

Urutan penegakan: `php artisan test` wajib hijau sebelum milestone ditutup. `--coverage --min=80` hanya bisa ditegakkan setelah Task 7 selesai.

## Risks

| Risk | Likelihood | Mitigation |
|---|---|---|
| Aturan coverage 80% tidak bisa diukur karena tidak ada Xdebug/PCOV | **Pasti** (sudah terverifikasi) | Task 7. Sampai selesai, laporkan status coverage sebagai "tidak terverifikasi" — jangan klaim lolos |
| `composer create-project` gagal atau sangat lambat karena ekstensi `zip` mati | Tinggi | Task 1 mengaktifkannya lebih dulu; DLL sudah tersedia di `C:\xampp\php\ext\php_zip.dll` |
| Global scope fail-loud memecahkan seeder, queue job, atau perintah artisan yang berjalan tanpa pengguna | Sedang | Sediakan jalan keluar eksplisit (`withoutGlobalScope`) dan dokumentasikan di `CLAUDE.md`; uji satu seeder sebagai bukti |
| Mengubah `php.ini` menyentuh konfigurasi di luar proyek dan bisa mempengaruhi aplikasi XAMPP lain | Sedang | Mengaktifkan `zip` bersifat aditif dan tidak mengubah perilaku aplikasi lain; tetap minta persetujuan sebelum menyentuh berkas tersebut |
| Model fixture khusus tes ikut termigrasi ke database produksi | Sedang | Migrasi fixture ditempatkan agar hanya berjalan di environment `testing`; verifikasi dengan `migrate:status` pada database utama |
| Upgrade PHP ke 8.3+ di kemudian hari demi Laravel 13 memaksa migrasi besar | Rendah | Catat plafon versi di `CLAUDE.md` sekarang, sehingga keputusan ini tidak mengejutkan nanti |
| Breeze men-scaffold tampilan dalam Bahasa Inggris sementara produk berbahasa Indonesia | Rendah | Diterima untuk milestone ini; lokalisasi ditangani terpisah, bukan di jalur kritis isolasi data |

## Acceptance

- [x] Semua task 1–7 selesai
- [x] `php artisan test` hijau — 41 test, 103 assertion, termasuk 14 kasus di `DataIsolationTest`
- [x] Setiap kasus isolasi terbukti pernah gagal sebelum implementasi — RED: `Trait "App\Models\Concerns\BelongsToUser" not found`, lalu GREEN 6/6
- [x] Tidak ada filter `where('user_id', ...)` manual — isolasi terpusat di `OwnedByUserScope`
- [x] `README.md` memuat langkah dari nol sampai tes hijau, termasuk mengaktifkan `zip` dan start MariaDB
- [x] Pola mengikuti scaffolding Laravel 12 + Breeze, tanpa konvensi tandingan
- [x] Gate coverage **ditegakkan dan lolos**: PCOV 1.0.12 terpasang, `php artisan test --coverage --min=80` menghasilkan **82,4%** (exit 0)

## Penyimpangan dari Rencana

| Rencana awal | Yang dikerjakan | Alasan |
|---|---|---|
| Migrasi fixture `database/migrations/*_create_owned_things_table.php` yang hanya jalan di env testing | Tabel fixture dibuat lewat `Schema::create` di dalam `DataIsolationTest`, tanpa file migrasi | `loadMigrationsFrom` hanya tersedia di ServiceProvider, bukan TestCase. Pendekatan ini juga menutup total risiko tabel fixture terbentuk di MariaDB — terverifikasi: `SHOW TABLES LIKE 'owned_things'` pada database `journalapps` kosong |
| `composer create-project laravel/laravel .` langsung di folder proyek | Scaffold di direktori temp, lalu isinya dikopi ke proyek | Folder sudah berisi `.claude/`, dan `create-project` menolak direktori yang tidak kosong |
| Task 7 (coverage driver): PCOV atau Xdebug | PCOV 1.0.12 (`php_pcov-1.0.12-8.2-ts-vs16-x64.zip` dari downloads.php.net), diaktifkan di `php.ini` | PCOV lebih ringan dari Xdebug untuk keperluan coverage. Varian `ts` dipilih karena build PHP XAMPP ini ZTS |

## Hasil Code Review dan Security Review

Keduanya memberi verdict APPROVE: **0 CRITICAL, 0 HIGH.**

Diperbaiki dalam milestone ini:

| Temuan | Severity | Tindakan |
|---|---|---|
| Sisi tulis tidak dijaga — `user_id` eksplisit dipercaya apa adanya, tidak ada hook `updating` | MEDIUM | `creating` kini menolak `user_id` yang berbeda dari pengguna terautentikasi; hook `updating` baru menolak perpindahan kepemilikan |
| Strict mode mati saat testing, sehingga test tidak menangkap kelas bug yang strict mode ada untuk menangkapnya | MEDIUM | Diubah ke `! $this->app->isProduction()`, berlaku di `local` dan `testing`. 39 test tetap hijau |
| Cabang hook `creating` yang melempar exception, mass update/delete, dan jalur seeder belum diuji | MEDIUM | 7 kasus test baru; `DataIsolationTest` naik dari 6 ke 13 kasus |
| `insert`/`upsert`/`DB::` melewati scope; unique index tanpa `user_id` memungkinkan upsert lintas pengguna | MEDIUM | Dicatat sebagai aturan wajib di `CLAUDE.md` (relevan saat tabel domain dibuat di milestone 2) |
| `attach()` pada tabel pivot tidak memeriksa kepemilikan ID | MEDIUM | Dicatat di `CLAUDE.md`: validasi ID relasi dengan `Rule::exists` berbasis model terscope |
| `user_id` fillable memungkinkan mass assignment lintas pengguna | LOW | Ditutup dua lapis: aturan di `CLAUDE.md` + exception dari hook `creating` |
| `MustVerifyEmail` dinonaktifkan, sehingga middleware `verified` tidak efektif — siapa pun bisa mendaftar dengan email milik orang lain dan langsung masuk dashboard | MEDIUM | Pemilik produk memilih mengaktifkan verifikasi. `User` kini `implements MustVerifyEmail`, dan satu test baru di `EmailVerificationTest` mengunci perilaku itu. Di dev, link verifikasi muncul di `storage/logs/laravel.log` karena `MAIL_MAILER=log` |
| Ability `viewAny` dan `create` pada policy tidak tercakup test (coverage policy 71,4%) | LOW | Satu test baru; policy kini 100%, total coverage naik dari 80,0% ke 82,4% |

Terbuka, belum ditindak:

| Temuan | Severity | Alasan |
|---|---|---|
| Nama kelas model muncul di pesan exception | LOW | Aman selama `APP_DEBUG=false` di produksi. Pesan itu berguna bagi developer; tidak diubah |
| `.env` dev memakai `root` tanpa password, `APP_DEBUG=true`, `LOG_LEVEL=debug` | LOW | Wajar untuk XAMPP lokal. Butuh checklist deploy sebelum produksi; belum relevan sekarang |
| Document root XAMPP mengarah ke `htdocs`, bukan `public/` — `.env` berpotensi terjangkau lewat web | LOW | Hanya berisiko di luar mesin lokal. Akses lewat `php artisan serve`, bukan `http://localhost/journalapps/` |

## Temuan Lingkungan Selama Eksekusi

- **MariaDB tidak terpasang sebagai Windows service** dan tidak berjalan. Harus di-start manual; proses yang dijalankan dari sesi tool ikut mati saat sesi berakhir.
- **Database `journalapps` sempat rusak** (error 1932: tabel ada di data dictionary tapi tablespace InnoDB hilang) karena `mysqld` ter-kill di tengah operasi. Dipulihkan dengan drop + hapus tablespace orphan + recreate.
- `php.ini` dibackup dua kali sebelum diubah: `php.ini.bak-journalapps` (sebelum `zip` diaktifkan) dan `php.ini.bak-prepcov` (sebelum `pcov` diaktifkan).

---
*Status: MILESTONE 1 SELESAI — seluruh task 1–7 tuntas, 41 test hijau, coverage 82,4% melewati gate 80%.*
