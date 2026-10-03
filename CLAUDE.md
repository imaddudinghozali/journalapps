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

## Model domain

| Model | Tabel | Catatan |
|---|---|---|
| `TradingSetup` | `trading_setups` | Satu pola entry. Unique `(user_id, name)`. Konstanta `MAX_ACTIVE_RULES` |
| `SetupRule` | `setup_rules` | Kriteria entry. Membawa `user_id` **sendiri**, bukan hanya `trading_setup_id` — tanpa itu `SetupRule::find()` tidak terscope. Konstanta `MIN_WEIGHT`/`MAX_WEIGHT` |

**Arsip, bukan hapus.** Keduanya memakai `archived_at` (nullable timestamp), bukan soft delete bawaan Laravel — semantiknya berbeda: ini "tidak dipakai lagi", bukan "terhapus". Record yang diarsipkan hilang dari form pencatatan trade tapi tetap bisa dirujuk laporan historis. Scope `active()` dan `archived()`, penanda `isArchived()`.

**Versioning rule diselesaikan lewat snapshot, bukan tabel versi.** `trade_rule_checks` menyimpan salinan `rule_label`, `rule_weight`, dan `rule_required` saat trade dicatat. Mengedit rule hari ini tidak pernah mengubah trade kemarin. Utang yang dicatat di milestone 2 sudah lunas; `tests/Feature/Trades/RuleSnapshotTest.php` adalah buktinya.

Konsekuensi yang harus diingat: **laporan "rule mana yang paling sering dilanggar" wajib mengelompokkan lewat `setup_rule_id`, bukan `rule_label`** — label bisa berubah antar trade. Dan jangan pernah membaca bobot dari relasi `rule()` untuk skor historis; pakai kolom snapshot.

**Integritas komposit.** `setup_rules` punya FK `(trading_setup_id, user_id)` → `trading_setups(id, user_id)`, jadi database sendiri menolak rule yang pemiliknya berbeda dari pemilik setup-nya. Tabel domain baru yang menyimpan FK ke tabel domain lain **wajib** memakai pola yang sama: tambahkan `unique(['id','user_id'])` di tabel induk, lalu FK komposit dari tabel anak.

**Cascade: sudah ditegakkan.** FK `trades.trading_setup_id` memakai `restrictOnDelete` — menghapus setup yang sudah dipakai trade akan ditolak database, dan itu disengaja. Jalur yang benar adalah mengarsipkan setup. `trade_rule_checks.setup_rule_id` adalah pengecualian dari aturan FK komposit: nullable kolom tunggal dengan `nullOnDelete`, karena komposit-cascade akan memusnahkan riwayat checklist dan komposit-restrict akan memblokir penghapusan akun. Snapshot sudah membawa datanya.

**Keunikan nama dicek lewat model, bukan `Rule::unique`.** `Rule::unique` menembak query builder mentah sehingga melihat data pengguna lain — nama setup orang lain akan bocor sebagai pesan "sudah dipakai". Lihat `uniqueNameRule()` di `resources/views/livewire/setups/manage.blade.php`.

Untuk instance model yang sudah dipegang (hasil `withoutGlobalScope`, route binding, atau relasi), otorisasi tetap diperiksa lewat `App\Policies\OwnedRecordPolicy`. Policy domain baru sebaiknya mewarisi kelas itu, lalu didaftarkan ke Gate.

### Scope ini gagal keras, dan itu disengaja

`OwnedByUserScope` **melempar `RuntimeException`** kalau dipakai tanpa pengguna terautentikasi — bukan mengembalikan hasil kosong. Hasil kosong terlihat lebih aman tapi menyembunyikan bug menjadi "data hilang tanpa sebab", yang jauh lebih sulit didiagnosis.

Konsekuensinya: **seeder, queue job, dan perintah artisan harus melepas scope secara eksplisit.**

```php
Model::withoutGlobalScope(OwnedByUserScope::class)->get();
```

Dan saat membuat record di luar konteks request, isi `user_id` secara eksplisit — kalau tidak, hook `creating` juga melempar exception.

Belum ada model domain yang memakai trait ini (milestone 1 hanya membangun mekanismenya). Risiko "seeder pecah" baru nyata di milestone 2 — saat membuat seeder pertama, uji jalurnya.

## Model domain trade

| Model | Tabel | Catatan |
|---|---|---|
| `Trade` | `trades` | Satu trade = tepat satu setup. `compliance_score` nullable: null berarti belum bisa dinilai (setup tanpa rule), bukan 0 |
| `TradeRuleCheck` | `trade_rule_checks` | Jawaban checklist + snapshot rule |

**Hasil disimpan sebagai `risk_amount` dan `pnl_amount`,** R-multiple dihitung (`Trade::rMultiple()`). Nominal saja membuat expectancy lintas instrumen tidak sebanding; R saja menghilangkan konteks uang.

**`App\Support\ComplianceScore` adalah satu-satunya tempat skor dihitung.** Rumusnya `round(100 * bobot terpenuhi / total bobot)`; tanpa rule hasilnya `null`. Pelanggaran rule wajib dilaporkan terpisah dari skor, karena bobotnya bisa kecil sehingga skor tetap tinggi padahal syarat mutlak dilanggar. Mengubah rumus ini merusak perbandingan dengan data historis.

**Checklist boleh direvisi, tapi revisinya tidak boleh bisa disembunyikan.** `original_compliance_score` disimpan sekali saat trade dicatat dan **tidak pernah ditimpa**; `checklist_revised_at` menandai perubahan; laporan menampilkan berapa trade yang direvisi. Jangan menambah jalur yang mengubah skor tanpa menyentuh dua kolom itu — tanpa jejak, pengguna bisa mencentang ulang setelah melihat hasil dan seluruh laporan kehilangan dasarnya.

**Jangan pernah memblokir penyimpanan trade karena kepatuhan rendah.** Hanya peringatan. Jurnal yang menolak mencatat trade buruk akan menghapus justru data yang paling perlu dipelajari. Ambangnya milik pengguna (`users.compliance_threshold`), bukan angka tetap aplikasi.

**Jangan memberi badge, streak, atau perayaan atas skor tinggi.** Skor diisi sendiri oleh pengguna; memberinya penghargaan mendorong pencentangan tidak jujur dan merusak satu-satunya aset produk ini.

## Laporan

`App\Support\TradeStatistics` dan `App\Support\ComplianceReport` adalah satu-satunya tempat statistik dihitung.

**Jangan tampilkan angka statistik tanpa jumlah sampel.** `TradeStatistics::MIN_SAMPLE` (10) adalah ambang di bawahnya angka disembunyikan. Win rate 100% dari dua trade bukan sekadar tidak berguna — ia mendorong keputusan strategi yang salah, dan PRD menilainya risiko Tinggi.

**Agregasi per rule WAJIB mengelompokkan lewat `setup_rule_id`, bukan `rule_label`.** Label adalah snapshot dan boleh berbeda antar trade; mengelompokkan lewat label memecah satu rule jadi beberapa baris palsu. Rule yang sudah dihapus (`setup_rule_id` null) dikelompokkan lewat label snapshot-nya.

**Trade tertutup berarti `closed_at` DAN `pnl_amount` sama-sama terisi.** Form menolak mengisi salah satu saja. Pakai scope `closed()` dan `stillOpen()`, jangan memeriksa kolomnya langsung.

**Trade tanpa skor dikeluarkan dari perbandingan patuh/tidak patuh,** bukan dianggap salah satunya. Setup tanpa rule tidak punya kepatuhan untuk dinilai.

**Jangan menulis bahasa sebab-akibat atau rekomendasi otomatis di laporan.** Data ini korelasional dan self-reported. Antarmuka menyebut "kaitan", bukan "menyebabkan", dan tidak pernah menyarankan "hapus rule ini".

`ComplianceReport::from()` mensyaratkan relasi `setup` dan `ruleChecks` sudah dimuat — strict mode melempar exception kalau tidak.

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

Angka saat ini 95,7% dari 244 test. Margin di atas ambang kecil, jadi kode baru tanpa test akan cepat menjatuhkannya — tulis test bersamaan dengan kodenya, jangan menunda.

Celah coverage yang diketahui dan disengaja: cabang exception pada hook `updating` di `BelongsToUser` (82,8%) dan `View/Components\GuestLayout` (0%, kelas layout bawaan Breeze tanpa logika).

Strict mode aktif saat testing, jadi **lazy loading melempar exception di test**. Muat relasi secara eksplisit (`$model->load('rules.setup')`); jangan melonggarkan strict mode untuk membuat test lolos.

## Gaya kode

Ikuti konvensi scaffolding Laravel 12 dan Breeze yang sudah ada. Jangan membuat konvensi tandingan. Komentar dan teks antarmuka produk ditulis dalam Bahasa Indonesia; nama kelas, metode, dan kolom tetap Bahasa Inggris mengikuti idiom Laravel.
