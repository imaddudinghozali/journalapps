<?php

use App\Support\ComplianceScore;

/*
|--------------------------------------------------------------------------
| Perhitungan skor kepatuhan
|--------------------------------------------------------------------------
|
| Murni perhitungan, tanpa database. Skor adalah angka yang dipakai pengguna
| untuk menilai dirinya sendiri, jadi aturannya harus tepat dan tidak boleh
| berubah diam-diam.
|
| Rumus: round(100 * jumlah bobot rule terpenuhi / jumlah bobot semua rule).
|
*/

function cek(int $weight, bool $met, bool $required = false): array
{
    return ['weight' => $weight, 'met' => $met, 'required' => $required];
}

it('memberi skor 100 ketika semua rule terpenuhi', function () {
    $skor = ComplianceScore::from([
        cek(1, true),
        cek(3, true),
        cek(5, true),
    ]);

    expect($skor->value)->toBe(100);
});

it('memberi skor 0 ketika tidak ada rule yang terpenuhi', function () {
    $skor = ComplianceScore::from([
        cek(2, false),
        cek(4, false),
    ]);

    expect($skor->value)->toBe(0);
});

it('menimbang rule sesuai bobotnya, bukan sekadar menghitung jumlahnya', function () {
    // Dua dari tiga rule terpenuhi, tapi yang terpenuhi berbobot kecil.
    $skor = ComplianceScore::from([
        cek(1, true),
        cek(1, true),
        cek(5, false),
    ]);

    // 2 dari total 7 bobot.
    expect($skor->value)->toBe(29);
});

it('membulatkan skor ke bilangan bulat terdekat', function () {
    $skor = ComplianceScore::from([
        cek(1, true),
        cek(2, false),
    ]);

    // 1/3 = 33,33 -> 33
    expect($skor->value)->toBe(33);
});

it('mengembalikan null ketika tidak ada rule sama sekali', function () {
    $skor = ComplianceScore::from([]);

    // Bukan 0 dan bukan 100: setup tanpa rule tidak punya apa pun untuk
    // dinilai. Menyebutnya 100 adalah kebohongan yang akan merusak laporan.
    expect($skor->value)->toBeNull()
        ->and($skor->isUnscored())->toBeTrue();
});

it('mengembalikan null ketika total bobot nol', function () {
    $skor = ComplianceScore::from([
        cek(0, true),
        cek(0, false),
    ]);

    expect($skor->value)->toBeNull();
});

it('menghitung rule wajib yang tidak terpenuhi', function () {
    $skor = ComplianceScore::from([
        cek(5, true),
        cek(5, true),
        cek(1, false, required: true),
    ]);

    expect($skor->unmetRequiredCount)->toBe(1)
        ->and($skor->hasUnmetRequired())->toBeTrue();
});

it('menandai pelanggaran rule wajib meski skor tinggi', function () {
    // Inilah alasan pelanggaran wajib dilaporkan terpisah dari skor: bobotnya
    // kecil, jadi skor tetap tinggi padahal syarat mutlak dilanggar.
    $skor = ComplianceScore::from([
        cek(5, true),
        cek(5, true),
        cek(1, false, required: true),
    ]);

    expect($skor->value)->toBe(91)
        ->and($skor->hasUnmetRequired())->toBeTrue();
});

it('tidak melaporkan pelanggaran ketika semua rule wajib terpenuhi', function () {
    $skor = ComplianceScore::from([
        cek(3, true, required: true),
        cek(2, false),
    ]);

    expect($skor->unmetRequiredCount)->toBe(0)
        ->and($skor->hasUnmetRequired())->toBeFalse();
});

it('menyatakan skor di bawah ambang', function () {
    $skor = ComplianceScore::from([
        cek(1, true),
        cek(3, false),
    ]);

    expect($skor->value)->toBe(25)
        ->and($skor->isBelow(80))->toBeTrue()
        ->and($skor->isBelow(20))->toBeFalse();
});

it('tidak menganggap skor yang belum dinilai berada di bawah ambang', function () {
    $skor = ComplianceScore::from([]);

    // Tanpa rule, tidak ada dasar untuk memperingatkan apa pun.
    expect($skor->isBelow(80))->toBeFalse();
});

it('memperingatkan ketika ada rule wajib tak terpenuhi walau skor di atas ambang', function () {
    $skor = ComplianceScore::from([
        cek(9, true),
        cek(1, false, required: true),
    ]);

    expect($skor->value)->toBe(90)
        ->and($skor->isBelow(80))->toBeFalse()
        ->and($skor->needsWarning(80))->toBeTrue();
});

it('tidak memperingatkan ketika skor memadai dan tidak ada pelanggaran wajib', function () {
    $skor = ComplianceScore::from([
        cek(9, true, required: true),
        cek(1, false),
    ]);

    expect($skor->needsWarning(80))->toBeFalse();
});
