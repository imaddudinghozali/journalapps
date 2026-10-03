<?php

use App\Support\TradeMath;

/*
|--------------------------------------------------------------------------
| Perhitungan P&L dan risiko
|--------------------------------------------------------------------------
|
| Angka uang. Salah di sini berarti pengguna menilai performanya sendiri
| dengan angka yang keliru, dan tidak akan sadar.
|
| P&L   = (exit - entry) * lot * ukuran kontrak, dibalik tandanya untuk short
| Risiko = |entry - stop| * lot * ukuran kontrak
|
| Ukuran kontrak itulah yang membedakan instrumen: pergerakan 1,00 pada
| XAUUSD (100 oz per lot) bernilai 100, sedangkan pada BTCUSD (1 per lot)
| bernilai 1.
|
*/

it('menghitung profit posisi long', function () {
    // XAUUSD: 1 lot = 100 oz. Naik 5,00 dengan 0,5 lot = 250.
    expect(TradeMath::pnl('long', lot: 0.5, entry: 2000.0, exit: 2005.0, contractSize: 100))
        ->toBe(250.0);
});

it('menghitung kerugian posisi long', function () {
    expect(TradeMath::pnl('long', lot: 1.0, entry: 2000.0, exit: 1997.5, contractSize: 100))
        ->toBe(-250.0);
});

it('membalik tanda untuk posisi short', function () {
    // Harga turun berarti short untung.
    expect(TradeMath::pnl('short', lot: 1.0, entry: 2000.0, exit: 1995.0, contractSize: 100))
        ->toBe(500.0);

    expect(TradeMath::pnl('short', lot: 1.0, entry: 2000.0, exit: 2005.0, contractSize: 100))
        ->toBe(-500.0);
});

it('menghormati ukuran kontrak tiap instrumen', function () {
    // Pergerakan yang sama, instrumen berbeda, hasil berbeda.
    $gerak = ['long', 'lot' => 1.0, 'entry' => 100.0, 'exit' => 101.0];

    expect(TradeMath::pnl('long', 1.0, 100.0, 101.0, 1))->toBe(1.0)
        ->and(TradeMath::pnl('long', 1.0, 100.0, 101.0, 100))->toBe(100.0)
        ->and(TradeMath::pnl('long', 1.0, 100.0, 101.0, 100000))->toBe(100000.0);
});

it('mengembalikan null ketika posisi belum ditutup', function () {
    expect(TradeMath::pnl('long', 1.0, 2000.0, null, 100))->toBeNull();
});

it('menghasilkan nol ketika exit sama dengan entry', function () {
    expect(TradeMath::pnl('long', 1.0, 2000.0, 2000.0, 100))->toBe(0.0);
});

it('menghitung risiko dari jarak entry ke stop', function () {
    // Stop 10,00 di bawah entry, 0,5 lot, 100 oz = 500.
    expect(TradeMath::risk(lot: 0.5, entry: 2000.0, stop: 1990.0, contractSize: 100))
        ->toBe(500.0);
});

it('menghitung risiko yang sama untuk stop di atas maupun di bawah entry', function () {
    // Arah tidak mengubah besarnya risiko; yang dihitung jaraknya.
    expect(TradeMath::risk(1.0, 2000.0, 1990.0, 100))->toBe(1000.0)
        ->and(TradeMath::risk(1.0, 2000.0, 2010.0, 100))->toBe(1000.0);
});

it('mengembalikan risiko null ketika stop berimpit dengan entry', function () {
    // Jarak nol berarti tidak ada risiko terdefinisi, bukan risiko nol.
    // Membiarkannya nol akan membuat R-multiple membagi dengan nol.
    expect(TradeMath::risk(1.0, 2000.0, 2000.0, 100))->toBeNull();
});

it('membulatkan hasil ke dua desimal', function () {
    expect(TradeMath::pnl('long', 0.33, 1.2345, 1.2399, 100000))->toBe(178.2);
});

it('menangani lot pecahan kecil', function () {
    expect(TradeMath::pnl('long', 0.01, 2000.0, 2100.0, 100))->toBe(100.0);
});
