# Plan: Pembenahan UI/UX

**Sumber**: permintaan langsung pemilik produk, dengan skill `redesign-existing-projects`
**Mode**: Redesign - evolusi terarah, bukan penulisan ulang
**Complexity**: Medium

## Ringkasan

Aplikasi ini sudah berfungsi dan punya sistem token warna yang matang. Yang belum ada adalah **hierarki**: 27 kartu dengan pola yang persis sama membuat setiap hal terlihat sama pentingnya, dan hal yang paling butuh tindakan justru tidak terlihat sama sekali.

Plan ini tidak mengganti framework, tidak mengganti sistem warna, dan tidak menyentuh logika bisnis. Seluruhnya bekerja di atas Tailwind 3 dan Volt yang sudah ada.

## Hasil Diagnosis

Dijalankan terhadap kode, bukan dikira-kira.

### Yang sudah benar dan tidak akan diutak-atik

- Token warna semantik dua mode, tervalidasi komputasional untuk buta warna
- `tabular-nums` pada angka
- Aksen tunggal, satu keluarga netral
- Keadaan kosong di dashboard, laporan, setups, trades
- Validasi inline dengan pesan Bahasa Indonesia
- Penanda halaman aktif di navigasi
- Lapisan gerak yang patuh `prefers-reduced-motion`
- Favicon

### Temuan, diurutkan dari yang paling berdampak

| # | Temuan | Bukti | Dampak |
|---|---|---|---|
| 1 | **Posisi terbuka tidak terlihat di dashboard** | 0 rujukan `stillOpen` di `dashboard/overview` | Satu-satunya hal yang menuntut tindakan justru tidak muncul di layar pertama |
| 2 | **27 kartu berpola identik** | `bg-surface shadow sm:rounded-lg` x27 | Tidak ada hierarki; ringkasan, form, dan arsip terlihat setara |
| 3 | **Form catat trade: 10 field dalam satu kolom datar** | 10 `x-input-label`, file 514 baris | PRD punya metrik guardrail kecepatan pencatatan. Ini permukaan paling sering dipakai dan paling melelahkan |
| 4 | **Tembok persiapan bagi pengguna baru** | instrumen -> setup -> rules -> baru bisa mencatat | Tiga halaman harus dilewati sebelum nilai pertama terasa |
| 5 | **Tidak ada ikon sama sekali** | tidak ada pustaka ikon terpasang | Semua navigasi dan aksi murni teks; pemindaian cepat jadi lambat |
| 6 | **46 shadow hitam bawaan** | `shadow`/`shadow-sm` x46 | Di mode gelap, bayangan hitam di atas latar hitam tidak terbaca sebagai elevasi |
| 7 | **HTML nyaris tanpa semantik** | 1 `<main>`, 1 `<nav>`, 0 `<section>` | Pembaca layar kehilangan struktur halaman |
| 8 | **Tidak ada skip-link** | 0 | Pengguna keyboard harus menelusuri seluruh navigasi tiap halaman |
| 9 | **Tidak ada halaman 404 kustom** | `resources/views/errors` tidak ada | Jalan buntu memakai halaman bawaan Laravel |
| 10 | **Tidak ada meta description maupun og:image** | 0 | Tautan yang dibagikan tampil kosong |
| 11 | **Tidak ada tampilan detail trade** | hanya ada `/edit` | Untuk sekadar melihat, pengguna harus masuk ke mode ubah |
| 12 | **Tidak ada skeleton saat memuat** | hanya `wire:loading` pada tombol | Perpindahan halaman terasa kosong sesaat |

## Yang SENGAJA Tidak Diambil dari Skill

Skill ini menyarankan beberapa teknik yang akan merusak produk ini. Dicatat supaya keputusannya terlihat, bukan terlewat:

| Saran skill | Alasan ditolak |
|---|---|
| Grain/noise overlay, gambar latar, gradien mesh | Teknik halaman pemasaran. Ini alat yang dipakai tiap hari; tekstur dekoratif jadi kebisingan |
| Parallax card stack, split-screen scroll, smooth scroll inersia | Scroll yang dibajak memperlambat orang yang sedang mencatat trade |
| Glassmorphism, spotlight border | Dekorasi tanpa fungsi di UI data |
| Variable font animation, text mask reveal | Sama |
| Ganti font ke Geist/Satoshi | Figtree sudah bukan Inter dan sudah termuat. Menukarnya menambah unduhan font demi perbedaan yang tipis. **Yang ditambahkan hanya font mono untuk angka** |

Skill juga menyarankan "tambah gambar latar berkualitas" pada seksi yang terasa datar. Untuk jurnal pribadi, foto stok justru membuatnya terasa seperti brosur. Kepadatan visual dicapai lewat hierarki, bukan gambar.

## Keputusan Desain yang Perlu Disepakati

**1. Kartu berhenti jadi wadah default.**
Tiga tingkat, bukan satu: **panel** (kartu dengan elevasi, untuk hal yang butuh fokus seperti form), **blok** (hanya garis pemisah dan ruang, untuk daftar dan ringkasan), **inline** (tanpa wadah sama sekali). Elevasi dipakai hanya saat ia menyampaikan hierarki, sesuai aturan skill.

**2. Dashboard dibalik urutannya: tindakan dulu, baru ringkasan.**
Posisi terbuka naik ke paling atas sebagai daftar yang bisa langsung ditutup. Angka ringkasan turun di bawahnya. Yang menuntut tindakan harus terlihat lebih dulu daripada yang sekadar menginformasikan.

**3. Form catat trade dipecah dua kelompok dalam satu layar.**
Bagian atas: setup dan checklist (penilaian). Bagian bawah: angka (eksekusi). Keduanya tetap di satu halaman - memecah jadi wizard justru memperlambat. Yang berubah: pengelompokan visual dan urutan tab keyboard, bukan jumlah langkahnya.

**4. Ikon dari Phosphor, bukan Lucide.**
Skill menandai Lucide sebagai pilihan default AI. Phosphor tersedia sebagai SVG tanpa membawa runtime JS. **Ikon hanya untuk navigasi dan aksi berulang**, tidak ditempel di setiap judul.

**5. Bayangan ditint ke latar.**
Token `--shadow-color` mengikuti mode, menggantikan 46 `shadow` hitam bawaan.

## Files to Change

| File | Action | Why |
|---|---|---|
| `resources/css/app.css` | UPDATE | Token bayangan bertint, skala tipografi, kelas `panel`/`blok`, font mono untuk angka |
| `tailwind.config.js` | UPDATE | Daftarkan boxShadow dan fontFamily mono |
| `resources/views/components/ui-panel.blade.php` | CREATE | Wadah berelevasi, menggantikan 27 pengulangan |
| `resources/views/components/ui-block.blade.php` | CREATE | Wadah tanpa elevasi |
| `resources/views/components/icon.blade.php` | CREATE | Pembungkus satu set ikon dengan stroke seragam |
| `resources/views/layouts/app.blade.php` | UPDATE | `<main>`, skip-link, meta description dan og |
| `resources/views/livewire/layout/navigation.blade.php` | UPDATE | Ikon pada tautan, struktur semantik |
| `resources/views/livewire/dashboard/overview.blade.php` | UPDATE | Posisi terbuka naik ke atas; hierarki tiga tingkat |
| `resources/views/livewire/trades/record.blade.php` | UPDATE | Pengelompokan penilaian vs eksekusi |
| `resources/views/livewire/trades/index.blade.php` | UPDATE | Baris jadi blok, bukan kartu; penanda posisi terbuka |
| `resources/views/livewire/{setups,instruments,reports}/*.blade.php` | UPDATE | Ganti kartu berulang dengan `ui-panel`/`ui-block` |
| `resources/views/errors/404.blade.php` | CREATE | Jalan buntu yang menawarkan jalan keluar |
| `tests/Feature/Ui/AccessibilityTest.php` | CREATE | Skip-link, landmark, judul halaman, meta |
| `tests/Feature/Dashboard/OpenPositionsTest.php` | CREATE | Posisi terbuka muncul di dashboard dan bisa ditutup dari sana |

## Tasks

Urutan mengikuti **Fix Priority** skill, disesuaikan: dampak terbesar dengan risiko terkecil lebih dulu.

### Task 1: Fondasi token dan komponen wadah
- **Action**: Bayangan bertint, skala tipografi eksplisit, font mono untuk angka, komponen `ui-panel` dan `ui-block`.
- **Validate**: `npm run build` bersih; seluruh test tetap hijau; tampilan belum berubah banyak (ini lapisan dasar).

### Task 2: Hierarki tiga tingkat di seluruh halaman
- **Action**: Ganti 27 kartu identik dengan panel/blok/inline sesuai perannya.
- **Validate**: Hitung ulang `bg-surface shadow sm:rounded-lg` harus mendekati nol; cek visual di dua mode.
- **Risiko**: perubahan menyentuh banyak file sekaligus. Dikerjakan per halaman, bukan sekali sapu.

### Task 3: Posisi terbuka naik ke dashboard (test dulu)
- **Action**: Daftar posisi terbuka di paling atas dashboard, dengan tautan tutup langsung. Test ditulis lebih dulu.
- **Validate**: Test membuktikan posisi terbuka tampil dan tertutup tidak; isolasi lintas akun diuji.
- **Catatan**: ini satu-satunya perubahan yang menambah perilaku, bukan sekadar tampilan.

### Task 4: Form catat trade dikelompokkan ulang
- **Action**: Dua kelompok dengan judul: penilaian (setup + checklist) dan eksekusi (angka). Urutan tab keyboard mengikuti.
- **Validate**: Seluruh test `RecordTrade` tetap hijau tanpa diubah. Kalau ada yang perlu diubah, berarti perilakunya ikut berubah dan itu di luar maksud task ini.

### Task 5: Ikon dan navigasi
- **Action**: Pasang Phosphor, ikon pada navigasi dan aksi berulang saja, stroke seragam.
- **Validate**: Cek `package.json` sebelum impor; ukuran bundle sebelum dan sesudah.

### Task 6: Aksesibilitas dan jalan buntu (test dulu)
- **Action**: Skip-link, landmark semantik, judul halaman per rute, meta description dan og, halaman 404.
- **Validate**: Test memeriksa skip-link, `<main>`, dan judul unik per halaman.

### Task 7: Skeleton saat memuat
- **Action**: Skeleton yang mengikuti bentuk akhir pada dashboard dan laporan, bukan spinner.
- **Validate**: Terlihat saat navigasi; patuh `prefers-reduced-motion`.

## Validation

```bash
npm run build
php artisan test
php artisan test --coverage --min=80
```

Ditambah pemeriksaan visual di browser pada dua mode untuk tiap halaman: dashboard, trades, edit, setups, instruments, reports, profile, welcome, 404.

## Risks

| Risk | Likelihood | Mitigation |
|---|---|---|
| Mengganti 27 kartu sekaligus memecahkan tata letak di satu halaman tanpa ketahuan | Sedang | Per halaman, dengan pemeriksaan visual dua mode tiap selesai satu |
| Menata ulang form mengubah perilaku tanpa sengaja | Sedang | Test `RecordTrade` tidak boleh ikut diubah. Kalau perlu diubah, berarti ada perilaku yang bergeser |
| Ikon menambah berat halaman | Rendah | Hanya ikon yang dipakai, SVG inline, tanpa runtime JS. Ukur bundle sebelum dan sesudah |
| Hierarki baru justru mengurangi keterbacaan di mode gelap | Sedang | Elevasi di mode gelap memakai perbedaan permukaan, bukan bayangan; diperiksa langsung di browser |
| Scope melebar jadi penulisan ulang | Sedang | Skill melarangnya eksplisit. Tidak ada file logika yang disentuh; perubahan hanya pada view, CSS, dan komponen |

## Acceptance

- [ ] Task 1-7 selesai
- [ ] `php artisan test` hijau, coverage tetap >= 80%
- [ ] Tidak ada file di `app/` yang berubah selain yang memang dibutuhkan task 6
- [ ] Posisi terbuka terlihat di dashboard dan bisa ditutup dari sana
- [ ] Pengulangan kartu identik mendekati nol; hierarki terbaca di dua mode
- [ ] Skip-link, `<main>`, judul unik, dan meta tersedia di tiap halaman
- [ ] Tidak ada badge, streak, atau perayaan skor yang masuk lewat pintu belakang
- [ ] Tidak ada grain, parallax, scroll hijack, atau gambar stok

---
*Status: AWAITING CONFIRMATION - belum ada kode yang ditulis.*
