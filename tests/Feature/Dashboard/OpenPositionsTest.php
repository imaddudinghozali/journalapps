<?php

use App\Models\Trade;
use App\Models\TradingSetup;
use App\Models\User;
use Livewire\Volt\Volt;

/*
|--------------------------------------------------------------------------
| Posisi terbuka di dashboard
|--------------------------------------------------------------------------
|
| Temuan nomor 1 di diagnosis UI: satu-satunya hal di aplikasi ini yang
| menuntut tindakan - posisi yang belum ditutup - justru tidak muncul di layar
| pertama. Angka ringkasan hanya melaporkan masa lalu; posisi terbuka adalah
| sekarang.
|
| Penutupannya sendiri tetap dilakukan di halaman ubah trade, bukan di sini.
| Perhitungan P&L berjalan di dalam transaksi dengan baris instrumen dikunci;
| menyalin jalur itu ke dashboard berarti menduplikasi logika uang di dua
| tempat, dan itu cara paling pasti membuat keduanya berbeda suatu hari nanti.
|
*/

function posisi(User $u, TradingSetup $s, string $simbol, ?float $pnl, string $dibuka): Trade
{
    return Trade::factory()->for($u)->for($s, 'setup')->create([
        'symbol' => $simbol,
        'risk_amount' => 100,
        'pnl_amount' => $pnl,
        'opened_at' => $dibuka,
        'closed_at' => $pnl === null ? null : $dibuka,
    ]);
}

it('menampilkan posisi yang masih terbuka', function () {
    $u = User::factory()->create();
    $this->actingAs($u);
    $s = TradingSetup::factory()->for($u)->create(['name' => 'Break of Structure']);

    posisi($u, $s, 'XAUUSD', null, '2026-04-01 09:00:00');

    Volt::test('dashboard.overview')
        ->assertSee('Posisi terbuka')
        ->assertSee('XAUUSD')
        ->assertSee('Break of Structure');
});

it('tidak menampilkan bagian posisi terbuka ketika semuanya sudah tertutup', function () {
    $u = User::factory()->create();
    $this->actingAs($u);
    $s = TradingSetup::factory()->for($u)->create();

    posisi($u, $s, 'EURUSD', 120.0, '2026-04-01 09:00:00');

    Volt::test('dashboard.overview')->assertDontSee('Posisi terbuka');
});

it('tidak menampilkan posisi terbuka milik pengguna lain', function () {
    $alice = User::factory()->create();
    $setupAlice = TradingSetup::factory()->for($alice)->create();
    posisi($alice, $setupAlice, 'XAUUSD', null, '2026-04-01 09:00:00');

    $bob = User::factory()->create();
    $this->actingAs($bob);

    Volt::test('dashboard.overview')
        ->assertDontSee('XAUUSD')
        ->assertDontSee('Posisi terbuka');
});

it('mengurutkan posisi terbuka dari yang paling lama menggantung', function () {
    $u = User::factory()->create();
    $this->actingAs($u);
    $s = TradingSetup::factory()->for($u)->create();

    posisi($u, $s, 'EURUSD', null, '2026-04-05 09:00:00');
    posisi($u, $s, 'XAUUSD', null, '2026-04-01 09:00:00');

    // Yang paling lama terbuka paling butuh diperhatikan, jadi ia di atas.
    Volt::test('dashboard.overview')->assertSeeInOrder(['XAUUSD', 'EURUSD']);
});

it('menautkan tiap posisi terbuka ke halaman tempat ia bisa ditutup', function () {
    $u = User::factory()->create();
    $this->actingAs($u);
    $s = TradingSetup::factory()->for($u)->create();

    $trade = posisi($u, $s, 'XAUUSD', null, '2026-04-01 09:00:00');

    Volt::test('dashboard.overview')->assertSee(route('trades.edit', $trade), escape: false);
});

it('tidak mencampur trade tertutup ke dalam daftar posisi terbuka', function () {
    $u = User::factory()->create();
    $this->actingAs($u);
    $s = TradingSetup::factory()->for($u)->create();

    posisi($u, $s, 'XAUUSD', null, '2026-04-01 09:00:00');
    $tertutup = posisi($u, $s, 'BTCUSD', 300.0, '2026-04-02 09:00:00');

    Volt::test('dashboard.overview')
        ->assertDontSee(route('trades.edit', $tertutup), escape: false);
});
