# Plan: Trade Setup Rules — Milestone 5

**Source PRD**: `.claude/prds/trade-setup-rules.prd.md`
**Selected Milestone**: 5 — Laporan kepatuhan vs P&L
**Complexity**: Medium

## Summary

Momen produk ini membuktikan dirinya. Pengguna akhirnya bisa menjawab pertanyaan yang jadi alasan aplikasi ini dibangun: **apakah saya rugi karena strateginya buruk, atau karena saya melanggar aturan saya sendiri?**

Satu halaman yang memecah win rate dan expectancy per setup dan per tingkat kepatuhan, lalu menunjukkan rule mana yang paling sering dilanggar dan rule mana yang paling berkaitan dengan hasil positif.

Tidak ada tabel baru. Seluruh milestone ini adalah agregasi di atas data yang sudah dikumpulkan milestone 3.

## Patterns to Mirror

| Category | Source | Pattern |
|---|---|---|
| Perhitungan murni | `app/Support/ComplianceScore.php` | Value object tanpa database, diuji di `tests/Unit`. `TradeStatistics` mengikuti bentuk yang sama |
| Kepemilikan | `app/Models/Trade.php:20` | Query lewat model; global scope yang membatasi. Tidak ada `where('user_id', ...)` manual |
| Komponen | `resources/views/livewire/trades/record.blade.php` | Volt single-file, computed + `unset()`, teks Bahasa Indonesia |
| Rute | `routes/web.php:11` | `['auth','verified']` |
| Test | `tests/Feature/Trades/*.php` | Unit untuk perhitungan, feature untuk komponen, isolasi lintas akun terpisah |
| Snapshot | `app/Models/TradeRuleCheck.php:19` | **Kelompokkan lewat `setup_rule_id`, jangan `rule_label`** — label bisa berubah antar trade |

## Keputusan Desain yang Perlu Disepakati

**1. Ambang sampel minimum: 10 trade tertutup. Di bawah itu angka disembunyikan.**
PRD mencatat risiko Tinggi "sampel terlalu kecil sehingga korelasi menyesatkan". Menampilkan "win rate 100%" dari 2 trade akan menghancurkan kepercayaan pada laporan, dan lebih buruk lagi, mendorong keputusan strategi yang salah. Di bawah ambang, tampilkan jumlah sampel dan pesan "belum cukup data", bukan angkanya.
**Angka 10 adalah tebakan beralasan, bukan hasil statistik.** Taruh sebagai konstanta bernama.

**2. Kepatuhan dipecah dua: patuh (≥ ambang pengguna) dan tidak patuh (< ambang).**
Bukan kuartil tetap. Ambang itu standar yang pengguna tetapkan sendiri, jadi perbandingannya bermakna baginya. Trade tanpa skor (setup tanpa rule) **dikeluarkan dari perbandingan ini**, bukan dianggap patuh maupun tidak.

**3. Hanya trade tertutup yang masuk analisis P&L.**
Trade terbuka tidak punya hasil untuk dibandingkan. Dihitung dan ditampilkan terpisah sebagai "masih terbuka: n".

**4. Perlu membereskan definisi "tertutup" lebih dulu.**
Saat ini ada dua penanda yang bisa berbeda: `closed_at` dan `pnl_amount`. Code review milestone 3 menandainya LOW dan menyarankan diseragamkan **sebelum laporan dibuat** — karena laporan inilah yang akan terpeleset. Keputusan: **trade tertutup berarti `closed_at` DAN `pnl_amount` sama-sama terisi**; form menolak mengisi salah satu saja. Perlu validasi baru plus perbaikan data yang sudah ada (belum ada data nyata, jadi murah).

**5. Expectancy diukur dalam R, bukan nominal.**
`expectancy = rata-rata R-multiple`. Nominal lintas instrumen tidak sebanding. Nominal tetap ditampilkan sebagai total, bukan sebagai dasar perbandingan.

**6. Laporan per-rule memakai `setup_rule_id`, dan tidak membuat klaim sebab-akibat.**
Untuk tiap rule: berapa kali dilanggar, dan rata-rata R saat terpenuhi vs saat tidak. Bahasanya deskriptif — "trade dengan rule ini terpenuhi rata-rata menghasilkan X R" — bukan "rule ini menyebabkan profit". PRD mencatat risiko pengguna salah menyimpulkan korelasi sebagai sebab-akibat dan membuang strategi yang valid.

**7. Tanpa pustaka chart.**
Tabel dan bar sederhana dari CSS. Menambah dependensi chart untuk perbandingan dua kelompok tidak sepadan, dan angka dengan jumlah sampel lebih jujur daripada grafik yang terlihat meyakinkan.

## Files to Change

| File | Action | Why |
|---|---|---|
| `app/Support/TradeStatistics.php` | CREATE | Perhitungan murni: win rate, expectancy R, total nominal, jumlah sampel. Tanpa database |
| `app/Support/ComplianceBreakdown.php` | CREATE | Membandingkan kelompok patuh vs tidak patuh, menegakkan ambang sampel |
| `app/Support/RuleImpact.php` | CREATE | Agregasi per `setup_rule_id`: frekuensi pelanggaran, rata-rata R saat terpenuhi vs tidak |
| `resources/views/livewire/reports/compliance.blade.php` | CREATE | Halaman laporan |
| `resources/views/reports.blade.php` | CREATE | Pembungkus |
| `routes/web.php` | UPDATE | Rute `reports` dengan `['auth','verified']` |
| `resources/views/livewire/layout/navigation.blade.php` | UPDATE | Tautan navigasi |
| `resources/views/livewire/trades/record.blade.php` | UPDATE | Validasi: `closed_at` dan `pnl_amount` harus terisi bersama |
| `app/Models/Trade.php` | UPDATE | `isClosed()` memakai definisi baru; scope `closed()`/`open()` |
| `tests/Unit/TradeStatisticsTest.php` | CREATE | Win rate, expectancy, kasus batas |
| `tests/Unit/ComplianceBreakdownTest.php` | CREATE | Ambang sampel, pemisahan kelompok |
| `tests/Unit/RuleImpactTest.php` | CREATE | Agregasi per rule, termasuk label yang berubah |
| `tests/Feature/Reports/ComplianceReportTest.php` | CREATE | Komponen, isolasi lintas akun, penyembunyian sampel kecil |
| `CLAUDE.md`, `README.md` | UPDATE | Dokumentasi |

## Tasks

### Task 1: Seragamkan definisi trade tertutup (test dulu)
- **Action**: Validasi di `record` — `closed_at` dan `pnl_amount` wajib terisi bersama atau sama-sama kosong. Tambah scope `closed()`/`open()` di `Trade`; `isClosed()` memakai keduanya.
- **Validate**: Test menolak salah satu terisi sendirian; test scope.
- **Catatan**: utang LOW dari review milestone 3, dibereskan di sini karena laporan bergantung padanya.

### Task 2: Perhitungan statistik sebagai unit murni (test dulu)
- **Action**: `TradeStatistics::from(array $rMultiples)` → `winRate`, `expectancy`, `sampleSize`. Kasus batas yang harus diputuskan dan diuji: sampel kosong (semua `null`), semua menang, semua kalah, R tepat 0 (dihitung menang atau tidak — **usul: 0 bukan menang**).
- **Validate**: `php artisan test --filter=TradeStatistics`.

### Task 3: Pemecahan kepatuhan dan ambang sampel (test dulu)
- **Action**: `ComplianceBreakdown` menerima trade tertutup berikut skornya dan ambang pengguna, mengembalikan dua kelompok plus jumlah yang dikecualikan karena tanpa skor. Menyembunyikan statistik kelompok yang sampelnya di bawah `MIN_SAMPLE`.
- **Validate**: Test membuktikan kelompok dengan 9 trade tidak menampilkan angka, 10 trade menampilkan.

### Task 4: Dampak per rule (test dulu)
- **Action**: `RuleImpact` mengelompokkan `trade_rule_checks` lewat `setup_rule_id`, menghitung frekuensi pelanggaran dan rata-rata R saat terpenuhi vs tidak. Rule yang sudah dihapus (`setup_rule_id` null) dikelompokkan terpisah sebagai "rule yang sudah dihapus", memakai snapshot label.
- **Validate**: Test dengan label yang berubah antar trade membuktikan pengelompokan tetap benar.

### Task 5: Halaman laporan
- **Action**: Komponen Volt. Bagian: ringkasan keseluruhan, perbandingan patuh vs tidak patuh, rincian per setup, rincian per rule. Setiap angka menyertakan **n**.
- **Validate**: `Volt::test('reports.compliance')`; test isolasi lintas akun.

### Task 6: Dokumentasi dan penutupan
- **Action**: Perbarui `CLAUDE.md` (aturan pengelompokan per `setup_rule_id`, ambang sampel), `README.md`. Tandai pertanyaan terbuka PRD soal ambang sampel sebagai terjawab.

## Validation

```bash
php artisan test
php artisan test --filter=Report
php artisan test --coverage --min=80
```

## Risks

| Risk | Likelihood | Mitigation |
|---|---|---|
| Pengguna menyimpulkan korelasi sebagai sebab-akibat lalu membuang setup yang sebenarnya valid | Tinggi | Tampilkan n di setiap angka; bahasa deskriptif; tanpa rekomendasi otomatis "hapus rule ini". Ini risiko yang sudah dicatat PRD |
| Ambang 10 ternyata salah — terlalu longgar untuk keputusan serius, terlalu ketat untuk pengguna baru | Sedang | Konstanta bernama, mudah diubah. Tinjau setelah ada data nyata dari baseline milestone 6 |
| Query agregasi lambat saat trade menumpuk | Rendah | Skala jurnal pribadi; index `(user_id, opened_at)` sudah ada. Kalau perlu, tambah index `(user_id, setup_rule_id, is_met)` |
| Laporan kosong terasa seperti aplikasi rusak bagi pengguna baru | Sedang | Keadaan kosong yang menjelaskan apa yang harus dilakukan, bukan tabel kosong |
| Perubahan definisi "tertutup" memecahkan test milestone 3 | Sedang | Dikerjakan lebih dulu di Task 1 dengan test, bukan disisipkan belakangan |

## Acceptance

- [x] Task 1–6 selesai — **183 test, 426 assertion, coverage 95,1%**
- [x] Tidak ada angka statistik tanpa jumlah sampel — satu closure format di view jadi satu-satunya jalur
- [x] Kelompok di bawah ambang menampilkan "belum cukup data (n=…)", dibuktikan test per kelompok
- [x] Agregasi per rule memakai `setup_rule_id`, dibuktikan test dengan label berubah antar trade
- [x] Trade tanpa skor dihitung terpisah (`unscoredCount`), bukan masuk salah satu kelompok
- [x] Hanya trade tertutup yang masuk analisis; definisi "tertutup" diseragamkan
- [x] Tidak ada bahasa sebab-akibat; ada test yang memastikan frasa "menyebabkan" tidak muncul
- [x] Isolasi lintas akun diuji pada laporan

## Penyimpangan dari Rencana

| Rencana awal | Yang dikerjakan | Alasan |
|---|---|---|
| Tiga kelas: `TradeStatistics`, `ComplianceBreakdown`, `RuleImpact` | Dua: `TradeStatistics` + `ComplianceReport` | Breakdown dan rule impact mengagregasi koleksi yang sama; memisahkannya hanya menambah lalu lintas data antar kelas |
| `ComplianceBreakdown` dan `RuleImpact` diuji sebagai unit murni | `ComplianceReport` diuji sebagai feature test | Ia mengonsumsi koleksi Eloquent; memalsukannya akan menguji mock, bukan perilaku |

## Hasil Code Review

**0 CRITICAL, 0 HIGH, 4 MEDIUM, 4 LOW** — verdict APPROVE.

Diperbaiki:

| Temuan | Severity | Tindakan |
|---|---|---|
| Daftar trade masih memakai `pnl_amount` untuk menentukan "terbuka", sementara laporan memakai definisi baru. Pengguna melihat dua jawaban berbeda untuk trade yang sama | MEDIUM | `index.blade.php` memakai `isClosed()`; lima test mengunci keempat kombinasi penanda |
| Validasi `required_with` yang baru tidak punya test sama sekali | MEDIUM | `TradeClosedStateTest` dengan 8 kasus |
| `rMultiple()` dan scope `closed()`/`stillOpen()` tidak punya test | MEDIUM | Tercakup di file yang sama |
| Batas ambang tidak diuji: skor tepat 80 melawan ambang 80 | MEDIUM | Test batas; melindungi `>=` dari regresi jadi `>` |
| R dibulatkan sebelum agregasi, sehingga R 0,003 jadi 0,00 dan trade untung dihitung bukan kemenangan | LOW | `rMultipleExact()` untuk agregasi; `rMultiple()` tetap membulatkan untuk tampilan |
| Teks usang "analisis menyusul di laporan" | LOW | Diperbarui |

Terbuka, belum ditindak:

| Temuan | Severity | Alasan |
|---|---|---|
| Penjaga ambang sampel hanya ada di closure view; `winRate`/`expectancy` tetap bisa dibaca langsung | MEDIUM | Belum ada konsumen lain. Saat ekspor atau API dibuat, pindahkan penjaganya ke `TradeStatistics` |
| Trade tertutup dengan R tak terhitung (risiko nol) hilang diam-diam tanpa counter | LOW | Form sudah menolak risiko < 0,01; hanya bisa datang dari impor. Relevan saat milestone 6 |
| Baris per-rule tidak menampilkan setup-nya, jadi dua rule berlabel sama tampak identik | LOW | Baru mengganggu kalau pengguna memakai label sama di banyak setup |
| Query laporan memuat semua trade tertutup ke memori | LOW | Skala jurnal pribadi; `select` kolom atau `chunk` kalau data membesar |

---
*Status: MILESTONE 5 SELESAI — 183 test hijau, coverage 95,1%.*
