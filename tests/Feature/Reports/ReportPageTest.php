<?php

use App\Models\Trade;
use App\Models\TradingSetup;
use App\Models\User;
use Livewire\Volt\Volt;

function tradeHasil(User $user, TradingSetup $setup, float $pnl, ?int $score = 90): Trade
{
    $trade = Trade::factory()->for($user)->for($setup, 'setup')->create([
        'risk_amount' => 100,
        'pnl_amount' => $pnl,
        'opened_at' => now()->subDays(2),
        'closed_at' => now()->subDay(),
    ]);

    $trade->compliance_score = $score;
    $trade->saveQuietly();

    return $trade;
}

it('menampilkan halaman laporan bagi pengguna terverifikasi', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/reports')
        ->assertOk()
        ->assertSeeVolt('reports.compliance');
});

it('menolak tamu membuka laporan', function () {
    $this->get('/reports')->assertRedirect('/login');
});

it('menolak pengguna belum terverifikasi membuka laporan', function () {
    $this->actingAs(User::factory()->unverified()->create());

    $this->get('/reports')->assertRedirect(route('verification.notice'));
});

it('menampilkan keadaan kosong yang menjelaskan langkah berikutnya', function () {
    $this->actingAs(User::factory()->create());

    Volt::test('reports.compliance')
        ->assertSee('Belum ada trade tertutup')
        ->assertSee('Jurnal Trade');
});

it('menyembunyikan angka ketika sampel masih sedikit', function () {
    $alice = User::factory()->create();
    $this->actingAs($alice);
    $setup = TradingSetup::factory()->for($alice)->create();

    tradeHasil($alice, $setup, 200);
    tradeHasil($alice, $setup, 300);

    // Dua trade menang semua. Tanpa penjaga sampel, ini akan tampil sebagai
    // "100% menang" dan mendorong keputusan strategi yang salah.
    Volt::test('reports.compliance')
        ->assertSee('belum cukup data')
        ->assertDontSee('100% menang');
});

it('menampilkan angka setelah sampel mencukupi', function () {
    $alice = User::factory()->create();
    $this->actingAs($alice);
    $setup = TradingSetup::factory()->for($alice)->create();

    for ($i = 0; $i < 10; $i++) {
        tradeHasil($alice, $setup, 100);
    }

    Volt::test('reports.compliance')->assertSee('100% menang');
});

it('menghitung trade yang masih terbuka secara terpisah', function () {
    $alice = User::factory()->create();
    $this->actingAs($alice);
    $setup = TradingSetup::factory()->for($alice)->create();

    tradeHasil($alice, $setup, 100);
    Trade::factory()->for($alice)->for($setup, 'setup')->create();

    Volt::test('reports.compliance')->assertSee('1 trade');
});

it('tidak menampilkan data pengguna lain pada laporan', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    $setupAlice = TradingSetup::factory()->for($alice)->create(['name' => 'SETUP RAHASIA ALICE']);
    tradeHasil($alice, $setupAlice, 500);

    $this->actingAs($bob);

    Volt::test('reports.compliance')
        ->assertDontSee('SETUP RAHASIA ALICE')
        ->assertSee('Belum ada trade tertutup');
});

it('tidak memakai bahasa sebab-akibat', function () {
    $alice = User::factory()->create();
    $this->actingAs($alice);
    $setup = TradingSetup::factory()->for($alice)->create();
    tradeHasil($alice, $setup, 100);

    // PRD menilai salah tafsir korelasi sebagai sebab-akibat sebagai risiko
    // Tinggi: pengguna bisa membuang strategi yang sebenarnya valid.
    Volt::test('reports.compliance')
        ->assertSee('menunjukkan kaitan, bukan sebab-akibat')
        ->assertDontSee('menyebabkan');
});
