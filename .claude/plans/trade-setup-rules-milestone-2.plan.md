# Plan: Trade Setup Rules — Milestone 2

**Source PRD**: `.claude/prds/trade-setup-rules.prd.md`
**Selected Milestone**: 2 — Katalog setup dan rules
**Complexity**: Medium

> Catatan penamaan: skill `/plan` menyarankan `{nama-prd}.plan.md`, tapi nama itu sudah dipakai plan milestone 1 yang mendokumentasikan pekerjaan selesai. Plan ini diberi akhiran `-milestone-2` agar riwayat milestone 1 tidak tertimpa.

## Summary

Pengguna dapat menuliskan strategi yang selama ini hanya ada di kepalanya menjadi data: beberapa **setup** trading (mis. Break of Structure, Order Block), masing-masing berisi daftar **rules** entry yang punya bobot dan bisa ditandai wajib. Setup dan rule dapat diarsipkan — tidak pernah dihapus keras — supaya trade historis yang kelak memakainya tidak kehilangan konteks.

Milestone ini belum menyentuh pencatatan trade maupun skor kepatuhan. Keluarannya murni katalog: tabel domain pertama, model domain pertama yang memakai mekanisme kepemilikan dari milestone 1, dan antarmuka CRUD-nya.

## Patterns to Mirror

Berbeda dari milestone 1, sekarang ada kode nyata untuk ditiru. Jangan membuat konvensi baru.

| Category | Source | Pattern |
|---|---|---|
| Kepemilikan data | `app/Models/Concerns/BelongsToUser.php` | Model domain memakai trait ini; `user_id` **tidak** masuk `$fillable`; kepemilikan terisi otomatis |
| Otorisasi | `app/Policies/OwnedRecordPolicy.php` | Policy domain mewarisi kelas ini, lalu didaftarkan agar `authorize()` bekerja |
| Komponen UI | `resources/views/livewire/profile/update-profile-information-form.blade.php` | Volt single-file: `new class extends Component`, `mount()`, metode aksi yang memanggil `$this->validate([...])` lalu `dispatch()` |
| Validasi form kompleks | `app/Livewire/Forms/LoginForm.php` | Form object dengan atribut `#[Validate]` bila state form mulai banyak |
| Rute | `routes/web.php:7` | `Route::view(...)->middleware(['auth','verified'])->name(...)` — rute baru wajib memakai kedua middleware |
| Test komponen | `tests/Feature/ProfileTest.php:21` | `Volt::test('area.nama')->set(...)->call(...)`, plus `assertSeeVolt` pada test halaman |
| Test isolasi | `tests/Feature/DataIsolationTest.php` | Pola arrange dua pengguna, lalu assert lintas akun |
| Migrasi | `database/migrations/0001_01_01_000000_create_users_table.php` | Penamaan dan gaya `Schema::create` bawaan Laravel 12 |

## Keputusan Desain yang Perlu Disepakati

Tiga hal di bawah mengunci bentuk data. Mengubahnya setelah milestone 3 berjalan jauh lebih mahal.

**1. `setup_rules` membawa `user_id` sendiri, bukan hanya `setup_id`.**
Global scope bekerja per-model. Tanpa `user_id` di tabel rules, query `SetupRule::find($id)` tidak terscope dan menjadi lubang IDOR. Konsekuensinya ada denormalisasi ringan (`user_id` muncul di dua tabel) yang harus dijaga konsisten — ditangani otomatis oleh trait, dan dikunci oleh test.

**2. Arsip, bukan hapus.**
`archived_at` (nullable timestamp) pada kedua tabel. Setup atau rule yang diarsipkan hilang dari form pencatatan trade tapi tetap bisa dirujuk laporan historis. Soft delete bawaan Laravel **tidak** dipakai karena semantiknya berbeda: ini bukan "terhapus", ini "tidak dipakai lagi".

**3. Versioning rule ditunda, dan itu keputusan sadar.**
PRD mencatat pertanyaan terbuka soal rule yang berubah seiring waktu. Milestone ini **tidak** membuat tabel versi. Alasannya: tanpa data trade, tidak ada yang bisa dibandingkan, sehingga desain versinya akan menebak. Mitigasi sementara: label dan bobot rule yang sudah dipakai trade sebaiknya tidak diedit melainkan diarsipkan lalu dibuat baru — aturan ini ditegakkan di milestone 3 saat relasi trade ada. **Risikonya nyata**: kalau pengguna mengedit bobot rule setelah ada trade, laporan historis jadi tidak setara.

## Files to Change

| File | Action | Why |
|---|---|---|
| `database/migrations/*_create_trading_setups_table.php` | CREATE | `user_id`, `name`, `description` (nullable), `archived_at` (nullable), timestamps. Unique `(user_id, name)` — **wajib memuat `user_id`** sesuai aturan di `CLAUDE.md` |
| `database/migrations/*_create_setup_rules_table.php` | CREATE | `user_id`, `setup_id`, `label`, `weight` (unsigned tinyint), `is_required` (bool), `position` (unsigned smallint), `archived_at`, timestamps. Index `(setup_id, position)` |
| `app/Models/TradingSetup.php` | CREATE | Model domain pertama; memakai `BelongsToUser`; relasi `rules()`; scope `aktif()` |
| `app/Models/SetupRule.php` | CREATE | Memakai `BelongsToUser`; relasi `setup()` |
| `app/Policies/TradingSetupPolicy.php` | CREATE | Mewarisi `OwnedRecordPolicy` |
| `app/Policies/SetupRulePolicy.php` | CREATE | Mewarisi `OwnedRecordPolicy` |
| `app/Providers/AppServiceProvider.php` | UPDATE | Daftarkan kedua policy (`Gate::policy`) |
| `database/factories/TradingSetupFactory.php` | CREATE | Untuk test; `user_id` diisi eksplisit karena factory berjalan tanpa autentikasi |
| `database/factories/SetupRuleFactory.php` | CREATE | Idem |
| `resources/views/livewire/setups/index.blade.php` | CREATE | Daftar setup: aktif dan arsip terpisah, jumlah rule per setup |
| `resources/views/livewire/setups/manage-setup.blade.php` | CREATE | Buat/edit setup beserta rules-nya dalam satu layar |
| `resources/views/setups.blade.php` | CREATE | Halaman pembungkus, mengikuti pola `resources/views/profile.blade.php` |
| `routes/web.php` | UPDATE | Rute `setups` dengan middleware `['auth','verified']` |
| `resources/views/livewire/layout/navigation.blade.php` | UPDATE | Tautan navigasi ke katalog setup |
| `tests/Feature/Setups/ManageSetupTest.php` | CREATE | CRUD, validasi, arsip |
| `tests/Feature/Setups/SetupIsolationTest.php` | CREATE | Isolasi lintas akun pada model domain nyata |
| `tests/Feature/DataIsolationTest.php` | UPDATE | Pertimbangkan memindahkan sebagian kasus dari fixture `OwnedThing` ke `TradingSetup` |
| `CLAUDE.md` | UPDATE | Catat model domain pertama dan keputusan arsip-bukan-hapus |

## Tasks

### Task 1: Skema dan model domain
- **Action**: Dua migrasi dan dua model. Keduanya memakai `BelongsToUser`. `user_id` tidak masuk `$fillable` pada model mana pun.
- **Mirror**: `app/Models/Concerns/BelongsToUser.php`, aturan unique index di `CLAUDE.md`.
- **Validate**: `php artisan migrate` lalu `php artisan migrate:rollback` bersih; `SHOW CREATE TABLE setup_rules` memperlihatkan unique index yang memuat `user_id`.

### Task 2: Factory yang bekerja tanpa autentikasi (test dulu)
- **Action**: Factory wajib mengisi `user_id` eksplisit — kalau tidak, hook `creating` melempar exception, tepat seperti yang didokumentasikan di `CLAUDE.md`. Tulis satu test yang membuktikan factory bekerja di luar konteks request **sebelum** menulis factory-nya.
- **Mirror**: `database/factories/UserFactory.php`.
- **Validate**: Factory dipanggil tanpa `actingAs()` tidak melempar exception.
- **Catatan**: ini adalah uji nyata pertama atas risiko "seeder pecah" yang dicatat di plan milestone 1.

### Task 3: Policy domain
- **Action**: Dua policy yang mewarisi `OwnedRecordPolicy`, didaftarkan di `AppServiceProvider`.
- **Mirror**: `app/Policies/OwnedRecordPolicy.php`.
- **Validate**: Test membuktikan policy benar-benar ter-registrasi di aplikasi, bukan hanya di `beforeEach` test — ini celah LOW yang dicatat security review milestone 1.

### Task 4: Test isolasi pada model domain nyata (RED dulu)
- **Action**: Port kasus-kasus dari `DataIsolationTest` ke `TradingSetup` dan `SetupRule`: query koleksi, find by ID, mass update/delete, penolakan pembuatan atas nama orang lain, penolakan perpindahan kepemilikan. Tambahkan kasus khusus relasi: **rule milik setup pengguna lain tidak boleh di-attach ke setup milik kita**.
- **Mirror**: `tests/Feature/DataIsolationTest.php`.
- **Validate**: Semua gagal lebih dulu, lalu hijau.

### Task 5: Antarmuka katalog setup
- **Action**: Komponen Volt untuk daftar dan kelola. Rules dikelola inline pada layar setup (tambah, hapus baris, ubah urutan, bobot, tanda wajib) agar pengguna melihat keseluruhan aturan sekaligus.
- **Mirror**: `resources/views/livewire/profile/update-profile-information-form.blade.php`.
- **Validate**: `Volt::test('setups.manage-setup')->set(...)->call(...)` hijau.

### Task 6: Batas dan validasi
- **Action**: Maksimum **15 rule aktif per setup**; `weight` 1–5; `label` wajib, maks 120 karakter; nama setup unik per pengguna. Pesan validasi dalam Bahasa Indonesia.
- **Mirror**: Gaya `$this->validate([...])` pada komponen profil.
- **Validate**: Test untuk setiap batas, termasuk rule ke-16 ditolak.
- **Alasan batas 15**: PRD mencatat risiko Tinggi "checklist terasa memberatkan sehingga pengguna berhenti mencatat", dengan mitigasi membatasi jumlah rule. Angka 15 adalah tebakan beralasan, bukan hasil riset — tinjau ulang setelah pemakaian nyata.

### Task 7: Rute, navigasi, dan dokumentasi
- **Action**: Rute `setups` dengan `['auth','verified']`, tautan di navigasi, perbarui `CLAUDE.md` dan `README.md`.
- **Mirror**: `routes/web.php:7`.
- **Validate**: Test rute menolak tamu dan menolak pengguna belum terverifikasi.

## Validation

```bash
php artisan migrate:fresh
php artisan test
php artisan test --filter=Setup
php artisan test --coverage --min=80
```

Margin coverage saat ini hanya ~2,4 poin di atas ambang. Kode milestone ini cukup banyak, jadi test harus ditulis seiring kode, bukan di akhir.

## Risks

| Risk | Likelihood | Mitigation |
|---|---|---|
| Mengedit bobot atau label rule setelah ada trade membuat laporan historis tidak setara | Tinggi (begitu milestone 3 jalan) | Arsip-bukan-hapus sudah menyiapkan jalannya; aturan "arsipkan lalu buat baru" ditegakkan di milestone 3. Versioning penuh tetap jadi pertanyaan terbuka di PRD |
| `user_id` ganda di `setup_rules` menjadi tidak konsisten dengan pemilik `setup_id`-nya | Sedang | Isi otomatis lewat trait, plus test yang membuktikan rule tidak bisa menunjuk setup milik pengguna lain |
| Coverage jatuh di bawah 80% karena banyak kode UI baru | Sedang | Tulis test per task, bukan di akhir; jalankan `--coverage` setiap selesai satu task |
| Antarmuka kelola rules inline jadi rumit (urutan, hapus baris, state belum tersimpan) | Sedang | Mulai dari yang paling sederhana: tambah dan hapus baris saja. Pengurutan drag-and-drop ditunda kalau mulai memperlambat |
| Batas 15 rule ternyata salah — terlalu ketat atau terlalu longgar | Sedang | Angka ditaruh sebagai konstanta bernama, bukan tersebar sebagai angka ajaib, supaya murah diubah |
| Factory gagal karena hook `creating` melempar exception tanpa autentikasi | Rendah | Justru diuji lebih dulu di Task 2 — ini memang perilaku yang dirancang |

## Acceptance

- [x] Task 1–7 selesai
- [x] `php artisan test` hijau — 87 test, 207 assertion; coverage **84,8%** (naik dari 82,4%)
- [x] Unique index memuat `user_id` — diverifikasi: `trading_setups_user_id_name_unique (user_id, name)`
- [x] `user_id` tidak ada di `$fillable` model mana pun
- [x] Policy terdaftar lewat auto-discovery dan dibuktikan dua test tanpa `Gate::policy` di dalamnya
- [x] Isolasi lintas akun diuji pada `TradingSetup` dan `SetupRule`, bukan hanya fixture
- [x] Rule tidak bisa ditautkan ke setup pengguna lain — ditegakkan **database**, bukan hanya aplikasi
- [x] Tidak ada filter `where('user_id', ...)` manual

## Penyimpangan dari Rencana

| Rencana awal | Yang dikerjakan | Alasan |
|---|---|---|
| Dua komponen: `setups/index` dan `setups/manage-setup` | Satu komponen `setups/manage` | 345 baris, jauh di bawah ambang 800; memecahnya sekarang menambah lalu lintas state antar komponen tanpa manfaat. Pemecahan jadi masuk akal saat edit/reorder rule ditambahkan |
| Daftarkan policy lewat `Gate::policy` di `AppServiceProvider` | Mengandalkan auto-discovery Laravel | Konvensi `App\Policies\{Model}Policy` sudah cukup, dan test membuktikan registrasinya nyata. Lebih sedikit kode |
| Dua file test (`ManageSetupTest`, `SetupIsolationTest`) | Tiga file, ditambah `SetupModelTest` | Test factory dan scope model tidak pas di kedua file itu secara semantik |
| Nama metode Bahasa Indonesia pada model | Diganti ke Bahasa Inggris | Melanggar konvensi yang ditulis sendiri di `CLAUDE.md`; dirapikan sebelum komponen bergantung padanya |

## Hasil Code Review dan Security Review

Keduanya APPROVE: **0 CRITICAL, 0 HIGH.** Code review: 3 MEDIUM, 7 LOW. Security review: 1 MEDIUM, 3 LOW.

Diperbaiki dalam milestone ini:

| Temuan | Severity | Tindakan |
|---|---|---|
| Batas `MAX_ACTIVE_RULES` memakai cek-lalu-simpan yang tidak atomik: dua tab bersamaan bisa menembus 15 | MEDIUM | `DB::transaction` dengan `lockForUpdate()` pada baris setup; hitungan dilakukan di dalam transaksi |
| `selectedSetupId` adalah properti publik, bisa di-set klien ke setup yang sudah diarsipkan lalu diberi rule | MEDIUM | `selectSetup()` dan `selectedSetup()` memakai `active()->find()`; dua test baru mengunci perilakunya |
| Tidak ada integritas komposit: database mengizinkan rule milik Bob menunjuk setup milik Alice | MEDIUM | `unique(['id','user_id'])` pada `trading_setups` + FK komposit `(trading_setup_id, user_id)`. Test yang tadinya membuktikan "tersembunyi oleh scope" kini membuktikan "ditolak database" |
| Race pada keunikan nama menghasilkan 500, bukan pesan validasi | LOW | `UniqueConstraintViolationException` ditangkap dan dijadikan error validasi |
| Nama tidak di-trim; Livewire tidak melewati middleware `TrimStrings` | LOW | Di-trim sebelum validasi; satu test mengunci |
| `position` dihitung dari `count()`, menghasilkan posisi kembar setelah ada rule diarsipkan | LOW | Diganti `max(position)+1`; satu test mengunci |
| `archiveRule` menggeser `archived_at` bila dipanggil dua kali | LOW | Dijadikan idempoten |
| Komentar migrasi mengklaim `unsignedTinyInteger` menegakkan skala 1–5 | LOW | Komentar dikoreksi — tipe kolom hanya mencegah nilai negatif |
| Celah test: manipulasi state klien lintas akun, `archiveRule`/`restoreSetup` lintas akun, batas bawah bobot, panjang label dan nama | MEDIUM | 11 test baru; total naik dari 76 ke 87 |

Terbuka, belum ditindak:

| Temuan | Severity | Alasan |
|---|---|---|
| `APP_DEBUG=true` dan `APP_ENV=local` di `.env.example` | MEDIUM | Wajar untuk dev. Butuh checklist deploy sebelum produksi; belum relevan karena belum ada target deploy |
| `authorize()` di komponen praktis tidak pernah tercapai — scope sudah menolak lebih dulu | LOW | Dipertahankan sebagai lapisan kedua. Hilangnya berarti kehilangan jaring saat ada kode yang memakai `withoutGlobalScope` |
| Test berjalan di SQLite sementara produksi MySQL; perbedaan collation pada keunikan nama tidak teruji | LOW | Unique index database sudah konsisten di MySQL. Menjalankan test di MySQL menambah kerumitan yang belum sepadan |
| Versioning rule belum ada | — | Pertanyaan terbuka di PRD; baru bisa dirancang setelah ada data trade |

---
*Status: MILESTONE 2 SELESAI — 87 test hijau, coverage 84,8%.*
