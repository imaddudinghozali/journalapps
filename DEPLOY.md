# Menyiapkan JournalApps untuk dipakai orang lain

Aplikasi ini dibangun di XAMPP sebagai jurnal pribadi. Begitu ada orang lain
yang mendaftar, beberapa hal yang aman di mesin sendiri jadi tidak aman.

Daftar di bawah bukan saran umum. Setiap butir menyebut kondisi nyata di repo
ini pada saat ditulis, dan apa akibatnya kalau dibiarkan.

## Blocker — jangan rilis sebelum ini beres

### 1. Matikan mode debug

```
APP_ENV=production
APP_DEBUG=false
```

Dengan `APP_DEBUG=true`, setiap galat tak tertangani menampilkan stack trace,
potongan kode sumber, query SQL beserta parameternya, dan seluruh isi
environment — termasuk `APP_KEY` dan sandi basis data — kepada siapa pun yang
memicunya. Ini satu-satunya butir yang bisa membocorkan semuanya sekaligus.

`APP_ENV=production` juga mematikan mode strict Eloquent. Itu memang disengaja:
strict melempar exception saat ada lazy load, dan di produksi exception itu
berubah jadi halaman galat untuk pengguna, bukan pesan untuk pengembang.

### 2. Ganti pengguna basis data

Sekarang: `DB_USERNAME=root` tanpa sandi.

Buat pengguna khusus yang haknya hanya pada satu basis data:

```sql
CREATE USER 'journalapps'@'127.0.0.1' IDENTIFIED BY '<sandi-panjang-acak>';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, DROP, REFERENCES
  ON journalapps.* TO 'journalapps'@'127.0.0.1';
FLUSH PRIVILEGES;
```

Hak DDL (`CREATE`, `ALTER`, `DROP`, `INDEX`, `REFERENCES`) diperlukan
`php artisan migrate`. Kalau migrasi dijalankan terpisah dengan kredensial
lain, cabut semuanya dan sisakan empat hak DML saja.

### 3. Sediakan pengirim email sungguhan

Sekarang: `MAIL_MAILER=log`.

Verifikasi email **aktif** di aplikasi ini — `routes/web.php` memasang
middleware `verified` pada setiap halaman jurnal. Dengan driver `log`, email
verifikasi cuma ditulis ke `storage/logs`, jadi setiap orang yang mendaftar
akan terkunci selamanya di layar "cek emailmu" dan tidak ada satu pun pesan
galat yang muncul. Ini kegagalan paling senyap di daftar ini.

Isi `MAIL_*` dengan SMTP sungguhan, lalu **uji dengan mendaftarkan satu akun
sungguhan** sebelum mengumumkan apa pun.

### 4. Amankan cookie sesi

`config/session.php` sudah menyetel `encrypt` dan `secure` menjadi aktif
secara bawaan ketika `APP_ENV=production`, jadi butir ini beres sendiri
begitu butir 1 dikerjakan — **asalkan situsnya benar-benar dilayani lewat
HTTPS**. Tanpa HTTPS, `secure` justru membuat sesi tidak pernah tersimpan dan
tidak ada yang bisa masuk.

### 5. Hapus akun fixture lokal

`uji@localhost.test` ("Trader Uji") berisi trade sintetis dan sandinya
tercatat di README. Akun itu hanya ada di basis data pengembangan, tapi kalau
basis data dev pernah disalin ke server, ia ikut terbawa.

```sql
DELETE FROM users WHERE email = 'uji@localhost.test';
```

## Sebelum tiap deploy

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

`php artisan key:generate` hanya sekali, saat pertama kali menyiapkan server.
Mengganti `APP_KEY` setelah ada data berarti seluruh sesi dan seluruh nilai
terenkripsi tidak bisa dibaca lagi.

## Sudah beres, tidak perlu dikerjakan lagi

- **Kebijakan privasi** — `/privasi`, tertaut dari halaman depan dan halaman
  pendaftaran. Isinya menjelaskan perilaku aplikasi hari ini; kalau
  perilakunya berubah, halaman itu ikut diperbarui.
- **Pembatasan laju pendaftaran** — lima akun per jam per alamat IP, ditegakkan
  di dalam komponen Volt, bukan sebagai middleware rute. Livewire mengirim form
  ke `/livewire/update`, jadi middleware pada rute `register` hanya akan
  membatasi pemuatan halamannya.
- **Pembatasan laju login** — lima percobaan, bawaan Breeze.
- **Isolasi data antar akun** — global scope plus policy, dengan test khusus di
  `tests/Feature/DataIsolationTest.php`.
- **`noindex` pada halaman aplikasi** — isinya ada di balik autentikasi.
- **Halaman 404 sendiri** — menawarkan jalan keluar yang berbeda untuk tamu dan
  pengguna yang sudah masuk.

## Yang belum dipikirkan, dan sebaiknya dipikirkan

- **Cadangan basis data.** Belum ada sama sekali. Jurnal trading bertahun-tahun
  yang hilang tidak bisa dibuat ulang dari mana pun.
- **Pemulihan sandi** bergantung pada butir 3. Tanpa email, tombolnya ada tapi
  tidak melakukan apa-apa.
- **Lokasi penyimpanan data pengguna** mungkin punya konsekuensi hukum tersendiri
  tergantung di mana kamu dan penggunamu berada. Daftar ini soal teknis, bukan
  nasihat hukum.
