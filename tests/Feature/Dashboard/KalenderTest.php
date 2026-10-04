<?php

use App\Models\Trade;
use App\Models\TradingSetup;
use App\Models\User;
use Livewire\Volt\Volt;

/*
|--------------------------------------------------------------------------
| Sel kalender tetap utuh maknanya di lebar layar mana pun
|--------------------------------------------------------------------------
|
| Di bawah breakpoint lg, sel kalender hanya selebar ~26px isi, sementara
| angka seperti +$320.00 butuh 64px. Sebelumnya angka itu terpotong diam-diam
| oleh overflow-hidden milik gridnya dan terbaca sebagai "+$3" - bukan sekadar
| jelek, tapi salah.
|
| Jalan keluarnya menyembunyikan teks nominal di layar sempit. Konsekuensinya
| yang dijaga di sini: nominal TIDAK BOLEH ikut hilang dari aria-label. Kalau
| hilang, pengguna pembaca layar kehilangan satu-satunya jalan ke angka itu,
| dan perbaikan tampilan berubah jadi kemunduran aksesibilitas.
|
| aria-label dipilih justru karena ia tidak punya breakpoint: satu teks yang
| sama terbaca di ponsel maupun desktop, jadi mustahil menyimpang antar lebar.
|
*/

function tradeKalender(User $u, TradingSetup $s, float $pnl, string $tutup, ?int $skor = null): Trade
{
    return Trade::factory()->for($u)->for($s, 'setup')->create([
        'risk_amount' => 100,
        'pnl_amount' => $pnl,
        'compliance_score' => $skor,
        'opened_at' => $tutup,
        'closed_at' => $tutup,
    ]);
}

beforeEach(function () {
    // Pertengahan bulan, supaya kalender bawaan menampilkan Oktober 2026 dan
    // tanggal uji di awal bulan pasti ikut tergambar.
    $this->travelTo('2026-10-15 12:00:00');

    $this->pengguna = User::factory()->create(['compliance_threshold' => 80]);
    $this->actingAs($this->pengguna);
    $this->setup = TradingSetup::factory()->for($this->pengguna)->create();
});

it('menyebut nominal dan kepatuhan di aria-label sel, bukan hanya di teks yang bisa disembunyikan', function () {
    tradeKalender($this->pengguna, $this->setup, 200.00, '2026-10-01 09:00:00', 50);
    tradeKalender($this->pengguna, $this->setup, 120.00, '2026-10-01 14:00:00', 50);

    Volt::test('dashboard.overview')
        ->assertSee('2026-10-01, 2 trade, +$320.00, kepatuhan 50%');
});

it('tetap menyebut nominal ketika tradenya tanpa skor kepatuhan', function () {
    tradeKalender($this->pengguna, $this->setup, -75.50, '2026-10-02 09:00:00');

    Volt::test('dashboard.overview')
        ->assertSee('2026-10-02, 1 trade, -$75.50')
        ->assertDontSee('2026-10-02, 1 trade, -$75.50, kepatuhan');
});

it('tidak memberi aria-label pada hari tanpa trade', function () {
    tradeKalender($this->pengguna, $this->setup, 200.00, '2026-10-01 09:00:00', 90);

    // Hari kosong bukan tombol yang bisa ditekan, jadi ia juga tidak boleh
    // mengumumkan diri ke pembaca layar sebagai sesuatu yang bisa dibuka.
    Volt::test('dashboard.overview')->assertDontSee('2026-10-03, 0 trade');
});

it('menyebut nominal total mingguan lewat teks sr-only', function () {
    tradeKalender($this->pengguna, $this->setup, 200.00, '2026-10-01 09:00:00', 90);
    tradeKalender($this->pengguna, $this->setup, -50.00, '2026-10-02 09:00:00', 90);

    // Kolom total mingguan bukan tombol, jadi ia tidak punya aria-label
    // untuk menampung nominalnya. Tanpa teks sr-only ini, menyembunyikan
    // angka di layar sempit akan menghapusnya sama sekali bagi pembaca layar.
    // 1 dan 2 Oktober 2026 jatuh di petak minggu pertama.
    Volt::test('dashboard.overview')
        ->assertSee('Minggu 1, 2 trade, +$150.00');
});

it('menyembunyikan teks nominal lewat CSS, bukan menghapusnya dari render', function () {
    tradeKalender($this->pengguna, $this->setup, 200.00, '2026-10-01 09:00:00', 90);

    // Kalau nominalnya dihapus dari render alih-alih disembunyikan lewat
    // kelas, layar lebar ikut kehilangan angkanya.
    Volt::test('dashboard.overview')
        ->assertSeeHtml('lg:block')
        ->assertSee('+$200.00');
});
