<?php

use App\Support\TradeStatistics;

/*
|--------------------------------------------------------------------------
| Statistik hasil trade
|--------------------------------------------------------------------------
|
| Angka-angka inilah yang dipakai pengguna untuk memutuskan setup mana yang
| dipertahankan dan mana yang dibuang. Satu kesalahan di sini membuat
| keputusan strategi yang mahal.
|
| Expectancy memakai R-multiple, bukan nominal: hasil pada instrumen berbeda
| tidak sebanding kalau diukur dengan uang.
|
*/

it('menghitung win rate dari daftar R', function () {
    $s = TradeStatistics::from([1.5, -1.0, 2.0, -1.0]);

    expect($s->sampleSize)->toBe(4)
        ->and($s->winRate)->toBe(50.0);
});

it('menghitung expectancy sebagai rata-rata R', function () {
    $s = TradeStatistics::from([2.0, -1.0, -1.0, 2.0]);

    expect($s->expectancy)->toBe(0.5);
});

it('tidak menghitung R nol sebagai kemenangan', function () {
    // Break even bukan menang. Menghitungnya sebagai menang akan membuat
    // win rate terlihat lebih baik daripada kenyataannya.
    $s = TradeStatistics::from([0.0, 0.0, 1.0, -1.0]);

    expect($s->winRate)->toBe(25.0);
});

it('menangani semua trade menang', function () {
    $s = TradeStatistics::from([1.0, 2.0, 3.0]);

    expect($s->winRate)->toBe(100.0)
        ->and($s->expectancy)->toBe(2.0);
});

it('menangani semua trade kalah', function () {
    $s = TradeStatistics::from([-1.0, -1.0]);

    expect($s->winRate)->toBe(0.0)
        ->and($s->expectancy)->toBe(-1.0);
});

it('mengembalikan null untuk sampel kosong', function () {
    $s = TradeStatistics::from([]);

    expect($s->sampleSize)->toBe(0)
        ->and($s->winRate)->toBeNull()
        ->and($s->expectancy)->toBeNull();
});

it('membulatkan angka ke dua desimal', function () {
    $s = TradeStatistics::from([1.0, -1.0, 1.0]);

    // 2 dari 3 = 66,666... -> 66,67
    expect($s->winRate)->toBe(66.67)
        ->and($s->expectancy)->toBe(0.33);
});

it('menyatakan sampel cukup atau tidak terhadap ambang', function () {
    $sedikit = TradeStatistics::from(array_fill(0, 9, 1.0));
    $cukup = TradeStatistics::from(array_fill(0, 10, 1.0));

    expect($sedikit->hasEnoughSample(10))->toBeFalse()
        ->and($cukup->hasEnoughSample(10))->toBeTrue();
});

it('menyatakan sampel kosong sebagai tidak cukup', function () {
    expect(TradeStatistics::from([])->hasEnoughSample(10))->toBeFalse();
});

it('menyediakan ambang sampel minimum sebagai konstanta', function () {
    // Angka ini tebakan beralasan, bukan hasil statistik. Ditaruh sebagai
    // konstanta supaya murah diubah setelah ada data nyata.
    expect(TradeStatistics::MIN_SAMPLE)->toBeInt()
        ->and(TradeStatistics::MIN_SAMPLE)->toBeGreaterThan(1);
});
