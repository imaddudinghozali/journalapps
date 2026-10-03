<?php

use App\Models\Scopes\OwnedByUserScope;
use App\Models\Trade;
use App\Models\TradeRuleCheck;
use App\Models\TradingSetup;
use App\Models\User;
use Illuminate\Database\QueryException;

/*
|--------------------------------------------------------------------------
| Isolasi data trade
|--------------------------------------------------------------------------
|
| Trade memuat P&L — data finansial paling sensitif di aplikasi ini. Pola
| pengujiannya mengikuti SetupIsolationTest.
|
*/

function tradeMilik(User $user, array $atribut = []): Trade
{
    $setup = TradingSetup::factory()->for($user)->create();

    return Trade::factory()
        ->for($user)
        ->for($setup, 'setup')
        ->create($atribut);
}

it('tidak menampilkan trade milik pengguna lain', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    tradeMilik($alice, ['symbol' => 'XAUUSD']);
    tradeMilik($bob, ['symbol' => 'EURUSD']);

    $this->actingAs($bob);

    expect(Trade::all())->toHaveCount(1)
        ->and(Trade::first()->symbol)->toBe('EURUSD');
});

it('tidak menemukan trade pengguna lain lewat ID langsung', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    $milikAlice = tradeMilik($alice);

    $this->actingAs($bob);

    expect(Trade::find($milikAlice->id))->toBeNull();
});

it('tidak menemukan checklist pengguna lain lewat ID langsung', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    $trade = tradeMilik($alice);
    $check = TradeRuleCheck::factory()->for($alice)->for($trade)->create();

    $this->actingAs($bob);

    expect(TradeRuleCheck::find($check->id))->toBeNull();
});

it('tidak menyentuh trade pengguna lain pada mass update dan delete', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    $milikAlice = tradeMilik($alice, ['symbol' => 'XAUUSD']);
    tradeMilik($bob);

    $this->actingAs($bob);

    $diubah = Trade::query()->update(['symbol' => 'DITIMPA']);
    $dihapus = Trade::query()->delete();

    $aliceMasihAda = Trade::withoutGlobalScope(OwnedByUserScope::class)->find($milikAlice->id);

    expect($diubah)->toBe(1)
        ->and($dihapus)->toBe(1)
        ->and($aliceMasihAda)->not->toBeNull()
        ->and($aliceMasihAda->symbol)->toBe('XAUUSD');
});

it('menolak pembuatan trade atas nama pengguna lain', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    $setupBob = TradingSetup::factory()->for($bob)->create();

    $this->actingAs($bob);

    $trade = new Trade([
        'symbol' => 'XAUUSD',
        'direction' => Trade::DIRECTION_LONG,
        'lot_size' => 0.1,
        'entry_price' => 2000,
        'stop_price' => 1990,
        'opened_at' => now(),
    ]);
    $trade->trading_setup_id = $setupBob->id;
    $trade->user_id = $alice->id;

    expect(fn () => $trade->save())->toThrow(RuntimeException::class);
});

it('menolak perpindahan kepemilikan trade', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    $trade = tradeMilik($alice);
    $this->actingAs($alice);

    $trade->user_id = $bob->id;

    expect(fn () => $trade->save())->toThrow(RuntimeException::class);
});

it('menolak trade yang menunjuk setup milik pengguna lain', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    $setupAlice = TradingSetup::factory()->for($alice)->create();

    $this->actingAs($bob);

    $trade = new Trade([
        'symbol' => 'XAUUSD',
        'direction' => Trade::DIRECTION_LONG,
        'lot_size' => 0.1,
        'entry_price' => 2000,
        'stop_price' => 1990,
        'opened_at' => now(),
    ]);
    $trade->trading_setup_id = $setupAlice->id;

    // FK komposit (trading_setup_id, user_id): database yang menolak.
    expect(fn () => $trade->save())->toThrow(QueryException::class);
});

it('mendaftarkan policy trade di aplikasi', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    $trade = tradeMilik($alice);

    expect($alice->can('view', $trade))->toBeTrue()
        ->and($alice->can('update', $trade))->toBeTrue()
        ->and($bob->can('view', $trade))->toBeFalse()
        ->and($bob->can('delete', $trade))->toBeFalse();
});

it('menolak menghapus setup yang sudah dipakai trade', function () {
    $alice = User::factory()->create();
    $setup = TradingSetup::factory()->for($alice)->create();

    Trade::factory()->for($alice)->for($setup, 'setup')->create();

    $this->actingAs($alice);

    // restrictOnDelete: riwayat trade tidak boleh ikut musnah hanya karena
    // setup-nya dihapus. Jalur yang benar adalah mengarsipkan setup.
    expect(fn () => $setup->delete())->toThrow(QueryException::class);
});

it('ikut menghapus checklist ketika trade dihapus', function () {
    $alice = User::factory()->create();
    $trade = tradeMilik($alice);
    TradeRuleCheck::factory()->count(3)->for($alice)->for($trade)->create();

    $this->actingAs($alice);

    $trade->delete();

    expect(TradeRuleCheck::count())->toBe(0);
});
