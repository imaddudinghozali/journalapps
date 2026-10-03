# Trade Setup Rules

## Problem

Trader retail umumnya sudah memiliki strategi, tetapi tidak memiliki cara untuk memisahkan **kualitas strategi** dari **kedisiplinan eksekusi**. Ketika rugi, trader tidak bisa menjawab pertanyaan paling penting: apakah setup-nya memang tidak profitable, atau saya yang melanggar aturan saya sendiri? Akibatnya strategi yang sebenarnya valid dibuang, strategi yang buruk dipertahankan, dan pelanggaran aturan (FOMO, entry tanpa konfirmasi, revenge trade) tidak pernah terukur sehingga tidak pernah berhenti.

Jurnal trading yang ada saat ini mencatat *hasil* (entry, exit, P&L) tapi tidak mencatat *proses* (kriteria apa yang terpenuhi saat entry). Tanpa data proses, tidak ada evaluasi yang bisa dilakukan selain menebak.

## Evidence

- Pemilik produk memiliki catatan trade historis dari jurnal sebelumnya yang menunjukkan pola pelanggaran aturan berulang. **Status: perlu ekstraksi** — data belum dikuantifikasi menjadi angka baseline (berapa % trade yang melanggar rules, dan selisih P&L antara trade patuh vs tidak patuh).
- Observasi di komunitas trading: keluhan bahwa strategi sebenarnya bekerja dan masalahnya ada pada kedisiplinan, sering muncul. **Status: Assumption — needs validation via wawancara 5–10 trader atau analisis thread komunitas.**
- Belum ada bukti kuantitatif dari pengguna di luar pemilik produk. Semua angka target di bawah bersifat hipotesis sampai baseline dari jurnal lama selesai diekstrak.

## Users

**Primary — Trader retail yang punya strategi tapi tidak konsisten mengeksekusinya.**
Konteks: trading mandiri, 5–40 trade/bulan, sudah mengenal konsep setup (Break of Structure, Order Block, Supply/Demand) tapi mencatat jurnal secara tidak terstruktur. Pemicu kebutuhan: setelah serangkaian loss, ingin tahu penyebabnya tanpa harus membaca ulang ratusan catatan manual.

**Secondary — Trader yang sedang memvalidasi strategi.**
Sudah relatif disiplin; kebutuhannya bergeser dari "apakah saya patuh" ke "rule mana yang sebenarnya memberi edge, dan rule mana yang hanya ritual". Pengguna ini menuntut laporan per-rule, bukan hanya skor agregat.

**Secondary — Pasangan mentor dan murid.**
Mentor perlu melihat apakah murid benar-benar menerapkan rules yang diajarkan, bukan hanya melihat hasil P&L murid.

**Not for**

- Trader algoritmik/bot — eksekusi mereka deterministik, tidak ada masalah kedisiplinan manusia untuk diukur.
- Broker, prop firm, atau penyedia sinyal yang butuh pengawasan kepatuhan tingkat institusi (audit trail, regulasi, kontrol risiko real-time).
- Investor jangka panjang yang bertransaksi beberapa kali setahun — volume data terlalu kecil untuk analisis korelasi.

## Hypothesis

Kami percaya bahwa **mengubah rules setup menjadi checklist terstruktur yang dicentang pada saat pencatatan setiap trade, lalu melaporkan korelasi antara skor kepatuhan dan hasil P&L**, akan **memungkinkan trader memisahkan kesalahan eksekusi dari kelemahan strategi** bagi **trader retail yang mencatat jurnal secara mandiri**.

Kami akan tahu hipotesis ini benar ketika **persentase trade yang dicatat tanpa setup/checklist (trade impulsif) turun secara signifikan, dan pengguna mengambil minimal satu keputusan strategi nyata — menonaktifkan atau merevisi setup/rule — berdasarkan laporan dari sistem.**

## Success Metrics

| Metric | Target | How measured |
|---|---|---|
| **Primary** — Trade tanpa setup/checklist | Turun ke < 10% dari total trade dalam 30 hari pemakaian aktif | Rasio trade tanpa setup terhadap total trade, dibandingkan terhadap baseline dari jurnal lama (baseline: TBD — perlu ekstraksi) |
| **Primary** — Keputusan strategi berbasis data | ≥ 1 setup atau rule dinonaktifkan/direvisi per pengguna dalam 60 hari | Log perubahan status setup/rule, dikaitkan dengan kunjungan ke halaman laporan |
| Secondary — Cakupan pencatatan | ≥ 90% trade baru memiliki checklist terisi lengkap | Trade dengan checklist lengkap dibagi total trade baru |
| Secondary — Nilai laporan terbukti | Selisih expectancy antara kelompok patuh dan tidak patuh terdeteksi jelas | Perbandingan expectancy antar bucket kepatuhan setelah sampel mencukupi (ambang sampel: TBD — needs validation) |
| Guardrail — Beban pencatatan | Pencatatan satu trade lengkap tidak lebih lama dari sebelum fitur ada | Waktu pengisian form, diukur lewat uji pemakaian pada 3–5 sesi |

Catatan: metrik "compliance score naik" **sengaja tidak dijadikan metrik utama**. Skor itu diisi sendiri oleh pengguna sehingga mudah dimanipulasi; menargetkannya justru mendorong pengguna mencentang rules secara tidak jujur dan merusak data yang menjadi inti nilai produk.

## Scope

**MVP** — Satu siklus utuh dari mendefinisikan aturan sampai mendapat umpan balik atas kepatuhan:

1. **Definisi setup dan rules.** Pengguna dapat membuat beberapa setup trading, masing-masing berisi daftar rules/kriteria entry. Setiap rule memiliki bobot (tidak semua aturan sama pentingnya) dan dapat ditandai sebagai wajib. Setup dan rule dapat diarsipkan tanpa menghapus riwayat trade yang sudah memakainya.
2. **Checklist pada pencatatan trade.** Saat mencatat trade, pengguna memilih satu setup dan mencentang rules yang benar-benar terpenuhi. Sistem menghitung skor kepatuhan berbobot secara otomatis dan menyimpan jawaban checklist sebagai bagian permanen dari catatan trade tersebut.
3. **Laporan kepatuhan vs hasil.** Laporan yang memecah win rate dan expectancy per setup dan per tingkat kepatuhan, serta menunjukkan rule mana yang paling sering dilanggar dan rule mana yang paling berkorelasi dengan hasil positif.
4. **Peringatan kepatuhan rendah.** Pada saat pencatatan, sistem memperingatkan pengguna ketika skor kepatuhan berada di bawah ambang yang ditetapkan pengguna sendiri, atau ketika rule wajib tidak terpenuhi.
5. **Isolasi antar pengguna.** Setiap akun hanya dapat melihat dan mengelola setup, rules, trade, dan laporannya sendiri.

**Out of scope**

- **Memblokir pencatatan trade.** Hanya peringatan, tidak pernah penolakan. Jurnal yang menolak mencatat trade akan membuat trade buruk tidak tercatat sama sekali — tepat menghapus data yang paling perlu dipelajari. *(Pemilik produk memilih seluruh item MVP termasuk enforcement; versi blocking diturunkan menjadi peringatan atas alasan integritas data di atas. Keputusan ini bisa dibalik — lihat Open Questions.)*
- **Template setup yang bisa dibagikan antar pengguna / marketplace rules.** Ditunda sampai nilai single-user terbukti; butuh model izin dan moderasi tersendiri.
- **Dashboard mentor untuk memantau murid.** Mentor diakui sebagai pengguna sekunder, tapi pemantauan lintas akun membutuhkan model berbagi akses yang belum ada di MVP. Untuk sementara ditangani dengan ekspor laporan.
- **Integrasi otomatis ke broker/MT5/API exchange.** Pencatatan tetap manual; checklist bersifat penilaian manusia dan tidak bisa diturunkan dari data eksekusi.
- **Validasi rule otomatis dari chart/harga.** Sistem tidak memverifikasi apakah klaim adanya Break of Structure itu benar. Data kepatuhan bersifat self-reported secara desain.
- **Analisis statistik lanjutan** (uji signifikansi, Monte Carlo, machine learning pencari pola). MVP hanya menyajikan perbandingan deskriptif.
- **Aplikasi mobile native.** Web responsif saja.

**Batasan**

- Platform web multi-user yang berjalan di lingkungan XAMPP lokal. Keputusan teknologi dicatat terpisah dan bukan bagian dari dokumen requirement ini.
- Skor kepatuhan bersifat self-reported; seluruh nilai produk bergantung pada kejujuran pengisian, sehingga desain tidak boleh memberi insentif untuk mencentang rules secara palsu.

## Delivery Milestones

| # | Milestone | Outcome | Status | Plan |
|---|---|---|---|---|
| 1 | Akun dan isolasi data | Pengguna dapat mendaftar, masuk, dan yakin bahwa data jurnalnya tidak terlihat oleh pengguna lain | complete | `.claude/plans/trade-setup-rules.plan.md` |
| 2 | Katalog setup dan rules | Pengguna dapat menuliskan strategi tak tertulisnya menjadi daftar setup dan rules berbobot yang bisa diarsipkan | complete | `.claude/plans/trade-setup-rules-milestone-2.plan.md` |
| 3 | Pencatatan trade dengan checklist | Setiap trade baru tersimpan bersama bukti kriteria apa yang terpenuhi, dan pengguna langsung melihat skor kepatuhannya | complete | `.claude/plans/trade-setup-rules-milestone-3.plan.md` |
| 4 | Peringatan kepatuhan rendah | Pengguna mendapat peringatan sebelum menyimpan trade berkepatuhan rendah, tanpa pernah kehilangan kemampuan mencatatnya | complete | `.claude/plans/trade-setup-rules-milestone-3.plan.md` (digabung) |
| 5 | Laporan kepatuhan vs P&L | Pengguna dapat menjawab setup mana yang menghasilkan dan rule mana yang paling mahal dilanggar, dari satu halaman | complete | `.claude/plans/trade-setup-rules-milestone-5.plan.md` |
| 6 | Baseline dari jurnal lama | Catatan trade historis masuk ke sistem sehingga target metrik punya pembanding nyata, bukan angka tebakan | pending | — |

## Open Questions

- [ ] Berapa baseline sebenarnya dari jurnal lama — persentase trade tanpa aturan, dan selisih P&L antara trade patuh vs tidak patuh? Semua target metrik bergantung pada angka ini.
- [x] **Terjawab (milestone 3/4):** peringatan saja, tidak pernah memblokir. Ambangnya diatur pengguna sendiri lewat halaman profil.
- [x] **Terjawab (milestone 3):** snapshot. `trade_rule_checks` menyalin label, bobot, dan status wajib saat trade dicatat, jadi trade lama memakai aturan yang berlaku saat itu tanpa perlu tabel versi.
- [x] **Terjawab (milestone 3):** tepat satu. Banyak setup membuat skor ambigu dan agregasi laporan jauh lebih rumit; konfluensi ditangani dengan membuat setup gabungan tersendiri.
- [x] **Terjawab (milestone 5):** 10 trade tertutup per kelompok (`TradeStatistics::MIN_SAMPLE`). Di bawah itu angkanya disembunyikan dan hanya jumlah sampel yang ditampilkan. Angka 10 tebakan beralasan, bukan hasil statistik — tinjau setelah ada data baseline.
- [x] **Terjawab (milestone 3):** biner, demi metrik guardrail kecepatan pencatatan. Bisa ditingkatkan nanti tanpa membuang data.
- [ ] Bagaimana menangani trade multi-posisi (scale-in / partial close) — apakah checklist menempel pada posisi atau pada trade gabungan?
- [x] **Terjawab sebagian (milestone 3):** `risk_amount` dan `pnl_amount` disimpan, R-multiple dihitung darinya. Satu mata uang per akun; dukungan multi-mata-uang masih terbuka.

## Risks

| Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|
| Pengguna mencentang rules secara tidak jujur agar skor terlihat bagus, membuat seluruh data laporan tak bernilai | Tinggi | Kritis | Jangan jadikan skor sebagai target atau capaian; jangan beri badge/streak atas skor tinggi. Tampilkan skor sebagai alat diagnosis, dan tonjolkan nilai dari mengakui pelanggaran (mis. trade dengan pelanggaran tertentu merugi rata-rata sebesar X) |
| Checklist terasa memberatkan sehingga pengguna berhenti mencatat sama sekali | Tinggi | Tinggi | Batasi jumlah rule per setup, sediakan pengisian cepat, izinkan menyimpan trade dengan checklist belum lengkap untuk dilengkapi kemudian. Pantau metrik guardrail waktu pengisian |
| Sampel trade terlalu kecil sehingga korelasi yang ditampilkan menyesatkan | Tinggi | Tinggi | Sembunyikan atau beri label "data belum cukup" di bawah ambang sampel minimum; tampilkan jumlah sampel di setiap angka |
| Rules berubah seiring waktu sehingga perbandingan historis tidak setara | Sedang | Sedang | Arsip, bukan hapus; pertimbangkan versioning rule (lihat Open Questions) dan tandai laporan yang melintasi perubahan definisi |
| Pengguna salah menyimpulkan korelasi sebagai sebab-akibat lalu membuang strategi yang sebenarnya valid | Sedang | Tinggi | Sertakan jumlah sampel dan bahasa yang hati-hati pada laporan; hindari rekomendasi otomatis untuk menghapus rule |
| Fitur dibangun hanya sesuai kebutuhan pemilik produk sendiri dan tidak cocok untuk trader lain | Sedang | Sedang | Selesaikan validasi wawancara pada bagian Evidence sebelum melewati MVP; jaga definisi setup/rule tetap bebas-bentuk agar tidak mengunci satu gaya trading |
| Data jurnal dan P&L adalah informasi finansial pribadi yang bocor antar akun | Rendah | Kritis | Isolasi antar pengguna dijadikan milestone pertama, bukan tambahan di akhir; uji kebocoran lintas akun secara eksplisit |

---
*Status: DRAFT — requirements only. Implementation planning pending via /plan.*
