<?php

use App\Models\Scopes\OwnedByUserScope;
use App\Models\SetupRule;
use App\Models\TradingSetup;
use App\Models\User;
use Illuminate\Database\QueryException;

/*
|--------------------------------------------------------------------------
| Isolasi pada model domain nyata
|--------------------------------------------------------------------------
|
| DataIsolationTest menguji mekanismenya memakai model fixture. File ini
| menguji hal yang sama pada model yang benar-benar dipakai produk, plus dua
| hal yang hanya muncul di sini: penautan rule lintas pemilik, dan apakah
| policy sungguh terdaftar di aplikasi.
|
| Tidak ada Gate::policy() di file ini — itu memang disengaja. Kalau policy
| hanya terdaftar di dalam test, otorisasi di produk tidak terbukti apa pun.
|
*/

function setupMilik(User $user, string $nama = 'Break of Structure'): TradingSetup
{
    return TradingSetup::factory()->for($user)->create(['name' => $nama]);
}

it('tidak menampilkan setup milik pengguna lain', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    setupMilik($alice, 'Punya Alice');
    setupMilik($bob, 'Punya Bob');

    $this->actingAs($bob);

    expect(TradingSetup::all())->toHaveCount(1)
        ->and(TradingSetup::first()->name)->toBe('Punya Bob');
});

it('tidak menemukan setup pengguna lain lewat ID langsung', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    $milikAlice = setupMilik($alice);

    $this->actingAs($bob);

    expect(TradingSetup::find($milikAlice->id))->toBeNull();
});

it('tidak menemukan rule pengguna lain lewat ID langsung', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    $setup = setupMilik($alice);
    $rule = SetupRule::factory()->for($alice)->for($setup, 'setup')->create();

    $this->actingAs($bob);

    // Inilah alasan setup_rules membawa user_id sendiri. Tanpa itu, query ini
    // tidak terscope dan menjadi lubang IDOR.
    expect(SetupRule::find($rule->id))->toBeNull();
});

it('tidak menyentuh setup pengguna lain pada mass update dan delete', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    $milikAlice = setupMilik($alice, 'Punya Alice');
    setupMilik($bob, 'Punya Bob');

    $this->actingAs($bob);

    $diubah = TradingSetup::query()->update(['description' => 'Ditimpa Bob']);
    $dihapus = TradingSetup::query()->delete();

    $alicePunyaMasihAda = TradingSetup::withoutGlobalScope(OwnedByUserScope::class)
        ->find($milikAlice->id);

    expect($diubah)->toBe(1)
        ->and($dihapus)->toBe(1)
        ->and($alicePunyaMasihAda)->not->toBeNull()
        ->and($alicePunyaMasihAda->description)->not->toBe('Ditimpa Bob');
});

it('menolak pembuatan setup atas nama pengguna lain', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    $this->actingAs($bob);

    expect(fn () => TradingSetup::create(['name' => 'Disusupkan', 'user_id' => $alice->id]))
        ->toThrow(RuntimeException::class);
});

it('menolak perpindahan kepemilikan setup', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    $setup = setupMilik($alice);
    $this->actingAs($alice);

    $setup->user_id = $bob->id;

    expect(fn () => $setup->save())->toThrow(RuntimeException::class);
});

it('tidak bisa menautkan rule ke setup milik pengguna lain', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    $setupAlice = setupMilik($alice);

    $this->actingAs($bob);

    // Bob tidak bisa menjangkau setup Alice lewat jalur terscope sama sekali.
    expect(TradingSetup::find($setupAlice->id))->toBeNull();

    // Dan kalaupun ID-nya ditebak lalu dipaksakan menembus Eloquent, FK komposit
    // (trading_setup_id, user_id) membuat database sendiri yang menolak.
    $rule = new SetupRule(['label' => 'Disusupkan', 'weight' => 1]);
    $rule->trading_setup_id = $setupAlice->id;

    expect(fn () => $rule->save())->toThrow(QueryException::class);

    $this->actingAs($alice);

    expect($setupAlice->load('rules')->rules)->toHaveCount(0);
});

it('membatasi relasi rules pada setup ke pemiliknya', function () {
    $alice = User::factory()->create();
    $setup = setupMilik($alice);

    SetupRule::factory()->count(2)->for($alice)->for($setup, 'setup')->create();

    $this->actingAs($alice);

    expect($setup->load('rules')->rules)->toHaveCount(2);
});

it('mendaftarkan policy setup di aplikasi, bukan hanya di test', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    $setup = setupMilik($alice);

    expect($alice->can('view', $setup))->toBeTrue()
        ->and($alice->can('update', $setup))->toBeTrue()
        ->and($alice->can('delete', $setup))->toBeTrue()
        ->and($bob->can('view', $setup))->toBeFalse()
        ->and($bob->can('update', $setup))->toBeFalse()
        ->and($bob->can('delete', $setup))->toBeFalse();
});

it('mendaftarkan policy rule di aplikasi, bukan hanya di test', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    $setup = setupMilik($alice);
    $rule = SetupRule::factory()->for($alice)->for($setup, 'setup')->create();

    expect($alice->can('update', $rule))->toBeTrue()
        ->and($bob->can('update', $rule))->toBeFalse()
        ->and($bob->can('delete', $rule))->toBeFalse();
});
