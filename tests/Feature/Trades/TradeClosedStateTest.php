<?php

use App\Models\SetupRule;
use App\Models\Trade;
use App\Models\TradingSetup;
use App\Models\User;
use Livewire\Volt\Volt;



/*
|--------------------------------------------------------------------------
| Definisi trade tertutup
|--------------------------------------------------------------------------
|
| Sebelum milestone 5 ada dua penanda yang bisa berbeda: closed_at dan
| pnl_amount. Daftar trade memakai yang satu, model memakai yang lain. Laporan
| adalah tempat perbedaan itu terpeleset, jadi definisinya diseragamkan:
| tertutup berarti KEDUANYA terisi.
|
*/

function setupSiap(User $user): TradingSetup
{
    $setup = TradingSetup::factory()->for($user)->create();
    SetupRule::factory()->for($user)->for($setup, 'setup')->create(['weight' => 1]);

    return $setup;
}

function isiFormDasar($komponen, TradingSetup $setup, App\Models\Instrument $ins)
{
    return $komponen
        ->set('tradingSetupId', $setup->id)
        ->set('instrumentId', $ins->id)
        ->set('lotSize', '0.1')->set('entryPrice', '2000')->set('stopPrice', '1990')
        ->set('openedAt', now()->subHour()->format('Y-m-d\TH:i'));
}

it('menolak harga exit tanpa waktu tutup', function () {
    $alice = User::factory()->create();
    $this->actingAs($alice);
    $setup = setupSiap($alice);

    isiFormDasar(Volt::test('trades.record'), $setup, instrumenUji($alice))
        ->set('exitPrice', '2015')
        ->call('save')
        ->assertHasErrors('closedAt');

    expect(Trade::count())->toBe(0);
});

it('menolak waktu tutup tanpa harga exit', function () {
    $alice = User::factory()->create();
    $this->actingAs($alice);
    $setup = setupSiap($alice);

    isiFormDasar(Volt::test('trades.record'), $setup, instrumenUji($alice))
        ->set('closedAt', now()->format('Y-m-d\TH:i'))
        ->call('save')
        ->assertHasErrors('exitPrice');

    expect(Trade::count())->toBe(0);
});

it('menerima trade yang keduanya kosong sebagai trade terbuka', function () {
    $alice = User::factory()->create();
    $this->actingAs($alice);
    $setup = setupSiap($alice);

    isiFormDasar(Volt::test('trades.record'), $setup, instrumenUji($alice))
        ->call('save')
        ->assertHasNoErrors();

    expect(Trade::first()->isClosed())->toBeFalse();
});

it('menyatakan tertutup hanya ketika kedua penanda terisi', function () {
    $alice = User::factory()->create();
    $this->actingAs($alice);
    $setup = TradingSetup::factory()->for($alice)->create();

    $terbuka = Trade::factory()->for($alice)->for($setup, 'setup')->create();
    $tertutup = Trade::factory()->for($alice)->for($setup, 'setup')->closed(100)->create();

    $hanyaPnl = Trade::factory()->for($alice)->for($setup, 'setup')->create();
    $hanyaPnl->pnl_amount = 50;
    $hanyaPnl->saveQuietly();

    $hanyaWaktu = Trade::factory()->for($alice)->for($setup, 'setup')->create();
    $hanyaWaktu->closed_at = now();
    $hanyaWaktu->saveQuietly();

    expect($terbuka->isClosed())->toBeFalse()
        ->and($tertutup->isClosed())->toBeTrue()
        ->and($hanyaPnl->fresh()->isClosed())->toBeFalse()
        ->and($hanyaWaktu->fresh()->isClosed())->toBeFalse();
});

it('memisahkan scope closed dan stillOpen secara konsisten', function () {
    $alice = User::factory()->create();
    $this->actingAs($alice);
    $setup = TradingSetup::factory()->for($alice)->create();

    Trade::factory()->for($alice)->for($setup, 'setup')->closed(100)->create();
    Trade::factory()->for($alice)->for($setup, 'setup')->create();

    $setengah = Trade::factory()->for($alice)->for($setup, 'setup')->create();
    $setengah->pnl_amount = 10;
    $setengah->saveQuietly();

    // Trade setengah jadi harus masuk stillOpen, bukan closed.
    expect(Trade::closed()->count())->toBe(1)
        ->and(Trade::stillOpen()->count())->toBe(2);
});

it('menghitung R-multiple dari hasil dan risiko', function () {
    $alice = User::factory()->create();
    $this->actingAs($alice);
    $setup = TradingSetup::factory()->for($alice)->create();

    $trade = Trade::factory()->for($alice)->for($setup, 'setup')->create([
        'risk_amount' => 50,
        'pnl_amount' => 125,
        'closed_at' => now(),
    ]);

    expect($trade->rMultiple())->toBe(2.5);
});

it('mengembalikan R null ketika hasil belum ada', function () {
    $alice = User::factory()->create();
    $this->actingAs($alice);
    $setup = TradingSetup::factory()->for($alice)->create();

    $trade = Trade::factory()->for($alice)->for($setup, 'setup')->create();

    expect($trade->rMultiple())->toBeNull()
        ->and($trade->rMultipleExact())->toBeNull();
});

it('tidak membulatkan R saat dipakai agregasi', function () {
    $alice = User::factory()->create();
    $this->actingAs($alice);
    $setup = TradingSetup::factory()->for($alice)->create();

    $trade = Trade::factory()->for($alice)->for($setup, 'setup')->create([
        'risk_amount' => 100,
        'pnl_amount' => 0.30,
        'closed_at' => now(),
    ]);

    // rMultiple() membulatkan ke 0,00 untuk tampilan. Agregasi harus memakai
    // nilai eksak, kalau tidak trade yang untung dihitung bukan kemenangan.
    expect($trade->rMultiple())->toBe(0.0)
        ->and($trade->rMultipleExact())->toBeGreaterThan(0.0);
});
