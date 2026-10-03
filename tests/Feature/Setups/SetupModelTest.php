<?php

use App\Models\Scopes\OwnedByUserScope;
use App\Models\SetupRule;
use App\Models\TradingSetup;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| Model domain dan factory
|--------------------------------------------------------------------------
|
| Factory berjalan di luar konteks request, tanpa pengguna terautentikasi.
| Plan milestone 1 mencatat ini sebagai risiko yang belum pernah diuji nyata:
| hook creating pada BelongsToUser melempar exception bila user_id tidak diisi
| eksplisit. Test di bawah mengunci jalur resmi itu.
|
*/

it('membuat setup lewat factory tanpa pengguna terautentikasi', function () {
    $alice = User::factory()->create();

    // Tidak ada actingAs() di sini — persis seperti seeder atau queue job.
    $setup = TradingSetup::factory()->for($alice)->create(['name' => 'Break of Structure']);

    expect($setup->user_id)->toBe($alice->id)
        ->and($setup->name)->toBe('Break of Structure');
});

it('membuat rule lewat factory tanpa pengguna terautentikasi', function () {
    $alice = User::factory()->create();
    $setup = TradingSetup::factory()->for($alice)->create();

    $rule = SetupRule::factory()
        ->for($alice)
        ->for($setup, 'setup')
        ->create(['label' => 'Ada BOS di H4', 'weight' => 3]);

    expect($rule->user_id)->toBe($alice->id)
        ->and($rule->trading_setup_id)->toBe($setup->id)
        ->and($rule->weight)->toBe(3);
});

it('menolak factory yang tidak menyebut pemilik', function () {
    // Kalau jaring ini longgar, seeder bisa diam-diam membuat data tanpa pemilik.
    expect(fn () => TradingSetup::factory()->create())
        ->toThrow(RuntimeException::class);
});

it('memisahkan setup aktif dari yang diarsipkan', function () {
    $alice = User::factory()->create();
    $this->actingAs($alice);

    TradingSetup::factory()->for($alice)->create(['name' => 'Aktif']);
    TradingSetup::factory()->for($alice)->create([
        'name' => 'Lama',
        'archived_at' => now(),
    ]);

    expect(TradingSetup::active()->get())->toHaveCount(1)
        ->and(TradingSetup::archived()->get())->toHaveCount(1)
        ->and(TradingSetup::active()->first()->name)->toBe('Aktif');
});

it('menandai setup yang sudah diarsipkan', function () {
    $alice = User::factory()->create();
    $this->actingAs($alice);

    $aktif = TradingSetup::factory()->for($alice)->create(['name' => 'Aktif']);
    $arsip = TradingSetup::factory()->for($alice)->create([
        'name' => 'Lama',
        'archived_at' => now(),
    ]);

    expect($aktif->isArchived())->toBeFalse()
        ->and($arsip->isArchived())->toBeTrue();
});

it('menyaring rule aktif dari rule yang diarsipkan', function () {
    $alice = User::factory()->create();
    $this->actingAs($alice);

    $setup = TradingSetup::factory()->for($alice)->create();

    SetupRule::factory()->for($alice)->for($setup, 'setup')->create(['label' => 'Masih dipakai']);
    $lama = SetupRule::factory()->for($alice)->for($setup, 'setup')->create([
        'label' => 'Tidak dipakai',
        'archived_at' => now(),
    ]);

    expect(SetupRule::active()->get())->toHaveCount(1)
        ->and($lama->isArchived())->toBeTrue();
});

it('mengembalikan rule milik setup lewat relasi', function () {
    $alice = User::factory()->create();
    $this->actingAs($alice);

    $setup = TradingSetup::factory()->for($alice)->create();
    SetupRule::factory()->count(3)->for($alice)->for($setup, 'setup')->create();

    // Relasi bersarang dimuat eksplisit: strict mode melarang lazy loading,
    // dan memang sengaja begitu.
    $setup->load('rules.setup');

    expect($setup->rules)->toHaveCount(3)
        ->and($setup->rules->first()->setup->id)->toBe($setup->id);
});

it('menyimpan tetapan batas sebagai konstanta bernama', function () {
    // Angka batas gampang berubah setelah dipakai sungguhan; jangan disebar
    // sebagai angka ajaib di komponen.
    expect(TradingSetup::MAX_ACTIVE_RULES)->toBeInt()
        ->and(SetupRule::MIN_WEIGHT)->toBe(1)
        ->and(SetupRule::MAX_WEIGHT)->toBe(5);
});

it('melepaskan scope secara eksplisit untuk konteks tanpa autentikasi', function () {
    $alice = User::factory()->create();
    TradingSetup::factory()->for($alice)->create();

    $semua = TradingSetup::withoutGlobalScope(OwnedByUserScope::class)->get();

    expect($semua)->toHaveCount(1);
});
