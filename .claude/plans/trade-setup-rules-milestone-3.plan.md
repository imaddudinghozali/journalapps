# Plan: Trade Setup Rules — Milestone 3

**Source PRD**: `.claude/prds/trade-setup-rules.prd.md`
**Selected Milestone**: 3 — Pencatatan trade dengan checklist (mencakup milestone 4, lihat Catatan Cakupan)
**Complexity**: Large

## Summary

Inti produk. Saat mencatat trade, pengguna memilih satu setup, mencentang rules yang benar-benar terpenuhi, dan sistem menghitung skor kepatuhan berbobot. Jawaban checklist tersimpan permanen sebagai bagian dari catatan trade itu — bukan sekadar angka, tapi **bukti kriteria apa yang terpenuhi saat entry**.

Tanpa milestone ini, dua milestone sebelumnya tidak menghasilkan nilai apa pun bagi pengguna: katalog rules yang tidak pernah dipakai mencatat hanyalah daftar niat.

## Catatan Cakupan

Plan ini menggabungkan **milestone 3 dan 4** dari PRD. Peringatan kepatuhan rendah (milestone 4) hanyalah satu cabang kondisi di atas skor yang dihitung milestone 3 — memisahkannya jadi plan sendiri berarti menyentuh file yang sama dua kali tanpa manfaat. Laporan (milestone 5) tetap terpisah karena bentuk kerjanya berbeda: agregasi dan penyajian, bukan pencatatan.

## Patterns to Mirror

| Category | Source | Pattern |
|---|---|---|
| Kepemilikan | `app/Models/TradingSetup.php:20` | `use BelongsToUser, HasFactory`; `user_id` tidak di `$fillable` |
| Integritas komposit | `database/migrations/2026_10_03_120100_create_setup_rules_table.php` | `unique(['id','user_id'])` di induk + FK komposit dari anak |
| Arsip | `app/Models/TradingSetup.php:40` | `archived_at`, scope `active()`/`archived()`, penanda `isArchived()` |
| Komponen | `resources/views/livewire/setups/manage.blade.php` | Volt single-file, computed + `unset()` setelah aksi, validasi pesan Bahasa Indonesia |
| Operasi atomik | `manage.blade.php` (`addRule`) | `DB::transaction` + `lockForUpdate()` untuk cek-lalu-simpan |
| Keunikan terscope | `manage.blade.php` (`uniqueNameRule`) | Cek lewat model, bukan `Rule::unique`/`exists:` yang menembus scope |
| Factory | `database/factories/TradingSetupFactory.php` | Tanpa default `user_id`; pemanggil wajib `->for($user)` |
| Test | `tests/Feature/Setups/*.php` | Tiga lapis: model, isolasi lintas akun, komponen |

## Keputusan Desain yang Perlu Disepakati

Enam hal di bawah mengunci bentuk data inti produk. Empat yang pertama menjawab pertanyaan terbuka di PRD.

**1. Snapshot rule ke dalam checklist — ini menjawab masalah versioning.**
`trade_rule_checks` menyimpan `rule_label`, `rule_weight`, dan `rule_required` **hasil salinan saat trade dicatat**, bukan hanya `setup_rule_id`. Konsekuensinya: mengedit bobot rule hari ini tidak pernah mengubah skor trade bulan lalu, dan tabel versi terpisah tidak diperlukan sama sekali. Utang yang dicatat di milestone 2 lunas di sini.
*Harga yang dibayar:* duplikasi data, dan laporan "rule X paling sering dilanggar" harus mengelompokkan lewat `setup_rule_id`, bukan label — karena label bisa berubah.

**2. Satu trade = tepat satu setup.**
PRD menanyakan apakah konfluensi dua setup perlu didukung. Jawaban: tidak untuk sekarang. Mengizinkan banyak setup membuat skor kepatuhan ambigu (dirata-rata? diambil minimum?) dan agregasi laporan jauh lebih rumit. Kalau pengguna rutin memakai konfluensi, jalan yang lebih jujur adalah membuat setup gabungan tersendiri.

**3. Jawaban rule bersifat biner: terpenuhi atau tidak.**
PRD menanyakan apakah perlu jawaban bertingkat (terpenuhi/sebagian/tidak). Jawaban bertingkat lebih jujur tapi memperlambat pencatatan, dan PRD punya metrik guardrail "pencatatan tidak boleh lebih lama dari sebelumnya". Biner dulu; kalau ternyata terlalu kasar, kolom bisa ditingkatkan tanpa membuang data.

**4. Hasil disimpan sebagai `risk_amount` dan `pnl_amount`, R-multiple dihitung.**
PRD menanyakan satuan hasil. Menyimpan nominal saja membuat expectancy lintas instrumen tidak sebanding; menyimpan R saja menghilangkan konteks uang. Menyimpan keduanya memberi R = `pnl_amount / risk_amount` tanpa kehilangan apa pun. Satu mata uang per akun untuk sekarang — multi-mata-uang adalah pekerjaan tersendiri.

**5. Skor kepatuhan disimpan, bukan dihitung ulang saat laporan.**
`compliance_score` (0–100, integer) ditulis saat trade disimpan. Alasannya sama dengan snapshot: skor harus mencerminkan aturan yang berlaku saat itu. Menghitung ulang dari `trade_rule_checks` akan memberi hasil sama, tapi menyimpannya membuat laporan sederhana dan cepat.
*Risikonya:* kolom bisa menyimpang dari isi checklist kalau ada kode yang menulis salah satu tanpa yang lain. Mitigasi: hanya satu jalur penulisan, dan test yang membandingkan kolom dengan hasil hitung ulang.

**6. Peringatan, tidak pernah blocking.**
Sudah diputuskan di PRD dan tidak berubah. Trade dengan kepatuhan rendah **selalu bisa disimpan** — justru trade itu yang paling perlu dipelajari. Peringatan muncul sebelum simpan; rule wajib yang tidak terpenuhi ditandai lebih tegas, tetap tanpa memblokir.

## Files to Change

| File | Action | Why |
|---|---|---|
| `database/migrations/*_create_trades_table.php` | CREATE | `user_id`, `trading_setup_id`, `symbol`, `direction`, `risk_amount`, `pnl_amount`, `compliance_score`, `opened_at`, `closed_at`, `notes`, timestamps. **FK ke `trading_setups` memakai `restrictOnDelete`**, bukan cascade |
| `database/migrations/*_create_trade_rule_checks_table.php` | CREATE | `user_id`, `trade_id`, `setup_rule_id` (nullable, `nullOnDelete`), `is_met`, snapshot `rule_label`/`rule_weight`/`rule_required`, `position` |
| `app/Models/Trade.php` | CREATE | `BelongsToUser`; relasi `setup()`, `ruleChecks()`; cast tanggal dan desimal |
| `app/Models/TradeRuleCheck.php` | CREATE | `BelongsToUser`; relasi `trade()`, `rule()` |
| `app/Support/ComplianceScore.php` | CREATE | Satu tempat perhitungan skor, dipakai komponen dan test. Bukan di model agar bisa diuji tanpa database |
| `app/Policies/TradePolicy.php`, `app/Policies/TradeRuleCheckPolicy.php` | CREATE | Mewarisi `OwnedRecordPolicy` |
| `database/factories/TradeFactory.php`, `TradeRuleCheckFactory.php` | CREATE | Tanpa default `user_id` |
| `resources/views/livewire/trades/record.blade.php` | CREATE | Form pencatatan: pilih setup, checklist muncul, skor live, peringatan |
| `resources/views/livewire/trades/index.blade.php` | CREATE | Daftar trade dengan skor dan hasil |
| `resources/views/trades.blade.php` | CREATE | Halaman pembungkus |
| `routes/web.php` | UPDATE | Rute `trades` dengan `['auth','verified']` |
| `resources/views/livewire/layout/navigation.blade.php` | UPDATE | Tautan navigasi |
| `tests/Unit/ComplianceScoreTest.php` | CREATE | Perhitungan skor murni, tanpa database |
| `tests/Feature/Trades/RecordTradeTest.php` | CREATE | Alur pencatatan, validasi, peringatan |
| `tests/Feature/Trades/TradeIsolationTest.php` | CREATE | Isolasi lintas akun |
| `tests/Feature/Trades/RuleSnapshotTest.php` | CREATE | Mengunci jaminan versioning |
| `CLAUDE.md`, `README.md` | UPDATE | Dokumentasi |

## Tasks

### Task 1: Perhitungan skor sebagai unit murni (test dulu)
- **Action**: `ComplianceScore` menerima daftar `{bobot, terpenuhi, wajib}` dan mengembalikan skor 0–100 plus daftar pelanggaran rule wajib. Tulis `tests/Unit/ComplianceScoreTest.php` lebih dulu.
- **Formula**: `round(100 * Σ(bobot rule terpenuhi) / Σ(bobot semua rule))`.
- **Kasus batas yang harus diputuskan dan diuji**: setup tanpa rule sama sekali (usul: skor `null`, bukan 0 maupun 100 — tidak ada yang bisa dinilai); semua rule bobot sama; rule wajib tidak terpenuhi padahal skor tinggi.
- **Validate**: `php artisan test --filter=ComplianceScore`.

### Task 2: Skema dan model
- **Action**: Dua migrasi, dua model, dua policy, dua factory.
- **Mirror**: FK komposit seperti `setup_rules`; `restrictOnDelete` ke `trading_setups`.
- **Validate**: `SHOW CREATE TABLE trades` memperlihatkan `ON DELETE RESTRICT` ke `trading_setups`, dan FK komposit memuat `user_id`.

### Task 3: Test isolasi dan snapshot (RED dulu)
- **Action**: Port pola isolasi dari `SetupIsolationTest`. Lalu `RuleSnapshotTest`: catat trade, ubah bobot dan label rule, pastikan skor dan label pada trade lama **tidak berubah**.
- **Validate**: Gagal lebih dulu, lalu hijau.

### Task 4: Form pencatatan trade
- **Action**: Komponen Volt. Pilih setup aktif → checklist rules aktif muncul → skor terhitung live saat mencentang. Simpan trade dan seluruh checklist dalam satu transaksi.
- **Mirror**: `setups/manage.blade.php`.
- **Validate**: `Volt::test('trades.record')` untuk alur lengkap.

### Task 5: Peringatan kepatuhan rendah (milestone 4 PRD)
- **Action**: Ambang default **80**, dapat diubah pengguna (kolom di `users` atau tabel preferensi — putuskan saat implementasi, usul: kolom `compliance_threshold` di `users`). Peringatan muncul saat skor di bawah ambang atau ada rule wajib tak terpenuhi. **Tombol simpan tetap aktif.**
- **Validate**: Test membuktikan trade berkepatuhan rendah **tetap tersimpan**.

### Task 6: Daftar trade
- **Action**: Daftar dengan setup, skor, hasil, tanggal. Belum ada agregasi — itu milestone 5.
- **Validate**: `assertSee` data trade; test isolasi daftar.

### Task 7: Dokumentasi dan penutupan utang
- **Action**: Perbarui `CLAUDE.md` (snapshot sebagai jawaban versioning, aturan `restrictOnDelete`), `README.md`. Tandai pertanyaan terbuka PRD yang sudah terjawab.

## Validation

```bash
php artisan migrate:fresh
php artisan test
php artisan test --filter=Trade
php artisan test --coverage --min=80
```

## Risks

| Risk | Likelihood | Mitigation |
|---|---|---|
| `compliance_score` menyimpang dari isi `trade_rule_checks` | Sedang | Satu jalur penulisan saja, di dalam transaksi; test yang menghitung ulang dari checklist dan membandingkan dengan kolom |
| Form pencatatan jadi lambat diisi sehingga melanggar metrik guardrail PRD | Sedang | Checklist muncul langsung tanpa pindah halaman; batas 15 rule dari milestone 2 menjaga panjangnya. Ukur waktu pengisian pada uji pemakaian |
| Pengguna mencentang semua rule secara refleks demi skor bagus | Tinggi | Masalah desain, bukan teknis. Jangan ada badge, streak, atau perayaan skor tinggi di mana pun. Peringatan ditulis netral, bukan menghakimi |
| Menghapus setup yang sudah dipakai trade gagal karena `restrictOnDelete` | Sedang | Memang disengaja. UI hanya menawarkan arsip, bukan hapus; pesan errornya harus menjelaskan itu, bukan menampilkan kegagalan SQL |
| Snapshot membuat data membengkak | Rendah | Beberapa baris per trade; tidak signifikan pada skala jurnal pribadi |
| Cakupan gabungan milestone 3+4 membuat PR terlalu besar | Sedang | Task 1–4 bisa berdiri sendiri sebagai satu potongan yang utuh kalau perlu dipecah saat implementasi |

## Acceptance

- [x] Task 1–7 selesai
- [x] `php artisan test` hijau — 143 test, 335 assertion; coverage **91,2%** (naik dari 84,8%)
- [x] Mengubah bobot, label, status wajib, arsip, bahkan menghapus rule **tidak mengubah** trade yang sudah tercatat — 6 kasus di `RuleSnapshotTest`
- [x] Trade kepatuhan rendah dan pelanggaran rule wajib tetap tersimpan — dibuktikan dua test
- [x] `compliance_score` selalu sama dengan hitung ulang dari checklist — dibuktikan termasuk saat rule berubah sebelum simpan
- [x] FK ke `trading_setups` memakai RESTRICT, diverifikasi di MariaDB
- [x] Isolasi lintas akun diuji pada `Trade` dan `TradeRuleCheck`
- [x] Tidak ada badge, streak, atau perayaan atas skor tinggi

## Hasil Code Review dan Security Review

Code review: **0 CRITICAL, 1 HIGH, 6 MEDIUM, 8 LOW** — verdict WARNING.
Security review: **0 CRITICAL, 0 HIGH, 3 LOW**, tanpa jalur kebocoran antar akun.

Diperbaiki:

| Temuan | Severity | Tindakan |
|---|---|---|
| Skor dan snapshot dibaca dari dua query terpisah di luar transaksi. Kalau rule berubah di antaranya, `compliance_score` menyimpang dari isi checklist tanpa ada yang tahu — justru invarian yang dijanjikan docblock `Trade` | **HIGH** | Rules dibaca satu kali di dalam transaksi dengan `lockForUpdate()`; skor dibangun dari array snapshot yang sama persis. Dikunci test yang mengubah bobot rule antara render dan simpan |
| `risk_amount`/`pnl_amount` tanpa batas: `1e30` lolos validasi lalu gagal di database, `0.001` tersimpan jadi `0.00` sehingga R-multiple mustahil | MEDIUM | `min:0.01` dan `max` sesuai kapasitas `decimal(18,2)`; dua test |
| Array `checks` tidak divalidasi ukuran maupun tipenya | LOW | `array`, `max:MAX_ACTIVE_RULES`, `checks.*` boolean |
| Penghapusan akun yang punya trade tidak pernah diuji — `restrictOnDelete` berpotensi memblokirnya pada engine lain | MEDIUM | Test penghapusan akun lengkap dengan setup, trade, dan checklist |
| Key `checks` milik rule pengguna lain dan rule arsip aman, tapi hanya kebetulan implementasi | MEDIUM | Dua test mengunci perilakunya |
| `compliance_threshold` masuk `$fillable` padahal komponennya memakai assignment langsung | LOW | Dikeluarkan dari `$fillable` |
| Setup bisa diarsipkan di sela antara render dan simpan | LOW | Setup dibaca ulang terkunci di dalam transaksi |
| Test ambang yang hampa — hanya menegaskan nilai factory | LOW | Diganti dua test yang me-render komponen dan memeriksa peringatan |

Terbuka, belum ditindak:

| Temuan | Severity | Alasan |
|---|---|---|
| Invarian "satu jalur penulisan" hanya konvensi; `$check->update()` di milestone berikutnya bisa memutusnya tanpa error | MEDIUM | Guard `updating` pada kolom `rule_*` layak ditambahkan saat fitur edit trade dibuat. Belum ada jalur yang memakainya sekarang |
| Tidak ada CHECK constraint `compliance_score <= 100` | LOW | Satu-satunya penulis adalah `ComplianceScore`, yang tidak bisa menghasilkan >100 |
| `pnl_amount` terisi tanpa `closed_at`: ada dua penanda "tertutup" yang bisa berbeda | LOW | Perlu diseragamkan sebelum laporan milestone 5 dibuat |
| Perbandingan ambang memakai skor yang sudah dibulatkan (79,6 jadi 80) | LOW | Konsisten dengan angka yang dilihat pengguna |
| `APP_DEBUG=true` di `.env.example` | LOW | Butuh checklist deploy; belum ada target deploy |
| Exception scope pada sesi kedaluwarsa menghasilkan 500, bukan 419 | LOW | Hanya menimpa pemilik halaman itu sendiri |

---
*Status: MILESTONE 3 DAN 4 SELESAI — 143 test hijau, coverage 91,2%.*
