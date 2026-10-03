# JournalApps — Instruksi Proyek

Jurnal trading multi-user. Inti produknya: mencatat **proses** entry (rules mana yang terpenuhi), bukan hanya hasil, lalu melaporkan korelasi kepatuhan dengan P&L.

Requirement ada di `.claude/prds/trade-setup-rules.prd.md`. Jangan menambah fitur yang dinyatakan Out of scope di sana tanpa membahasnya lebih dulu.

## Stack dan batasannya

| Komponen | Versi | Catatan |
|---|---|---|
| PHP | 8.2 (XAMPP) | **Plafon keras.** Laravel 13 butuh PHP ^8.3, jadi jangan upgrade Laravel ke 13 tanpa upgrade PHP lebih dulu |
| Laravel | 12.x | |
| Livewire | 3.x | Dipasang lewat Laravel Breeze stack `livewire` |
| Database | MariaDB 10.4 | Dev: database `journalapps`. CLI tidak ada di PATH — pakai `C:\xampp\mysql\bin\mysql.exe` |
| Test | Pest 3 | Berjalan di SQLite in-memory (`phpunit.xml`), tidak menyentuh MariaDB |

MariaDB tidak terpasang sebagai Windows service di mesin ini. Kalau koneksi ditolak (error 2002), MySQL-nya belum jalan — start dari XAMPP Control Panel.

## Aturan wajib: kepemilikan data

Data jurnal dan P&L adalah informasi finansial pribadi. PRD menilai kebocoran antar akun sebagai dampak **Kritis**.

**Setiap model domain yang menyimpan data milik pengguna WAJIB memakai trait `App\Models\Concerns\BelongsToUser`,** dan tabelnya wajib punya kolom `user_id` (FK ke `users.id`).

Trait itu memberi tiga hal sekaligus:

1. Relasi `user()`.
2. Global scope `App\Models\Scopes\OwnedByUserScope` — membatasi semua query ke pengguna yang sedang masuk.
3. Pengisian `user_id` otomatis saat `creating`.

**Jangan menulis `where('user_id', auth()->id())` manual di controller atau komponen Livewire.** Isolasi berada di satu tempat; filter manual bocor begitu ada satu query yang lupa difilter.

### Aturan yang tidak boleh dilanggar oleh model domain

Hasil review keamanan milestone 1 — setiap butir di bawah menutup jalur serang konkret:

1. **`user_id` tidak boleh masuk `$fillable`, dan jangan pakai `$guarded = []`.** Kalau `user_id` fillable, `create($request->all())` memungkinkan pengguna membuat record atas nama orang lain. Trait sudah menolak hal ini dengan exception, tapi jangan bergantung pada jaring terakhir — pakai `$request->validated()` dari FormRequest.
2. **Setiap unique index pada tabel domain WAJIB memuat `user_id`.** Unique index tanpa `user_id` (mis. hanya `symbol`) membuat `upsert()` pengguna B bisa menimpa baris pengguna A lewat ON DUPLICATE KEY UPDATE, dan membocorkan keberadaan data pengguna lain lewat `QueryException` pada `firstOrCreate`.
3. **Jangan pakai `Model::insert()`, `Model::upsert()`, `DB::table()`, atau `DB::select()` untuk tabel domain.** Semuanya melewati global scope dan model event, jadi melewati seluruh mekanisme isolasi.
4. **Validasi ID relasi dengan query terscope,** yaitu `Rule::exists` berbasis model yang terscope, bukan `exists:table,id`. Tabel pivot tidak punya scope, sehingga `attach($idMilikOrangLain)` akan menulis baris pivot tanpa pemeriksaan.
5. **Daftarkan policy setiap model domain** (`Gate::policy`, atau ikuti penamaan `XPolicy` agar auto-discovery bekerja), lalu panggil `authorize()` di controller/komponen. Global scope melindungi query; policy melindungi instance yang sudah dipegang.

Trait juga menolak **perpindahan kepemilikan**: mengubah `user_id` pada record yang sudah ada melempar exception lewat hook `updating`.

Untuk instance model yang sudah dipegang (hasil `withoutGlobalScope`, route binding, atau relasi), otorisasi tetap diperiksa lewat `App\Policies\OwnedRecordPolicy`. Policy domain baru sebaiknya mewarisi kelas itu, lalu didaftarkan ke Gate.

### Scope ini gagal keras, dan itu disengaja

`OwnedByUserScope` **melempar `RuntimeException`** kalau dipakai tanpa pengguna terautentikasi — bukan mengembalikan hasil kosong. Hasil kosong terlihat lebih aman tapi menyembunyikan bug menjadi "data hilang tanpa sebab", yang jauh lebih sulit didiagnosis.

Konsekuensinya: **seeder, queue job, dan perintah artisan harus melepas scope secara eksplisit.**

```php
Model::withoutGlobalScope(OwnedByUserScope::class)->get();
```

Dan saat membuat record di luar konteks request, isi `user_id` secara eksplisit — kalau tidak, hook `creating` juga melempar exception.

Belum ada model domain yang memakai trait ini (milestone 1 hanya membangun mekanismenya). Risiko "seeder pecah" baru nyata di milestone 2 — saat membuat seeder pertama, uji jalurnya.

## Pengujian

TDD: tulis test dulu sampai gagal (RED), baru implementasi (GREEN).

```bash
php artisan test
php artisan test --filter=DataIsolation
```

`tests/Feature/DataIsolationTest.php` adalah penjaga utama isolasi data: 12 kasus isolasi plus 1 kasus redirect autentikasi. **Jangan melemahkan test ini** untuk membuat kode baru lolos — kalau test itu gagal, yang salah hampir selalu kode barunya.

Yang dijaga: query koleksi, find by ID, policy view/update/delete, auto-isi `user_id`, mass update, mass delete, penolakan pembuatan atas nama orang lain, penolakan perpindahan kepemilikan, exception tanpa autentikasi (baik pada query maupun pada create), jalur `user_id` eksplisit untuk seeder, dan escape hatch `withoutGlobalScope`.

Model fixture `tests/Fixtures/OwnedThing.php` hanya untuk menguji mekanisme kepemilikan. Tabelnya dibuat lewat `Schema::create` di dalam test, bukan migrasi, agar mustahil terbentuk di MariaDB. Setelah model domain nyata ada di milestone 2, pertimbangkan memindahkan kasus-kasus isolasi ke model nyata tersebut.

**Coverage ditegakkan** lewat PCOV 1.0.12:

```bash
php artisan test --coverage --min=80
```

Angka saat ini 82,4% dari 41 test. Margin di atas ambang hanya ~2 poin, jadi kode baru tanpa test akan cepat menjatuhkannya — tulis test bersamaan dengan kodenya, jangan menunda.

Celah coverage yang diketahui dan disengaja: cabang exception pada hook `updating` di `BelongsToUser` (82,8%) dan `View/Components\GuestLayout` (0%, kelas layout bawaan Breeze tanpa logika).

## Gaya kode

Ikuti konvensi scaffolding Laravel 12 dan Breeze yang sudah ada. Jangan membuat konvensi tandingan. Komentar dan teks antarmuka produk ditulis dalam Bahasa Indonesia; nama kelas, metode, dan kolom tetap Bahasa Inggris mengikuti idiom Laravel.
