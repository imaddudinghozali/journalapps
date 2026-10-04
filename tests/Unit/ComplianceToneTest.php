<?php

use App\Support\ComplianceTone;

/*
|--------------------------------------------------------------------------
| Pemetaan skor kepatuhan ke warna
|--------------------------------------------------------------------------
|
| Dua hal berbeda yang gampang tertukar, dan dipisah di sini dengan sengaja:
|
|   zone()  - zona warna tetap: <50 rugi, 50-75 waspada, >75 untung. Ini
|             hanya soal TAMPILAN, supaya skor yang sama selalu berwarna
|             sama di seluruh aplikasi.
|   obeys() - patuh atau melanggar, diukur terhadap ambang milik pengguna
|             sendiri. Ini yang dipakai laporan untuk membagi kelompok.
|
| Keduanya tidak boleh saling menggantikan. Pengguna berambang 90 yang
| mencatat skor 80 tetap "melanggar" menurut ambangnya, walau warnanya hijau.
|
*/

it('memetakan skor ke tiga zona warna tetap', function (int $skor, string $zona) {
    expect(ComplianceTone::zone($skor))->toBe($zona);
})->with([
    [0, ComplianceTone::ZONE_LOSS],
    [49, ComplianceTone::ZONE_LOSS],
    [50, ComplianceTone::ZONE_WARN],
    [75, ComplianceTone::ZONE_WARN],
    [76, ComplianceTone::ZONE_PROFIT],
    [100, ComplianceTone::ZONE_PROFIT],
]);

it('memperlakukan skor yang belum ada sebagai netral, bukan nol', function () {
    // Setup tanpa rule menghasilkan skor null. Mewarnainya merah akan
    // menuduh pengguna melanggar sesuatu yang tidak pernah ia tetapkan.
    expect(ComplianceTone::zone(null))->toBe(ComplianceTone::ZONE_NEUTRAL);
});

it('memberi kelas teks yang sesuai untuk tiap zona', function (?int $skor, string $potonganTeks) {
    expect(ComplianceTone::textClass($skor))->toContain($potonganTeks);
})->with([
    [30, 'viz-negative'],
    [60, 'warn'],
    [90, 'viz-positive'],
    [null, 'ink-faint'],
]);

it('tidak pernah mengembalikan kelas kosong', function (?int $skor) {
    expect(ComplianceTone::textClass($skor))->not->toBeEmpty()
        ->and(ComplianceTone::surfaceClass($skor))->not->toBeEmpty()
        ->and(ComplianceTone::ringColor($skor))->not->toBeEmpty();
})->with([null, 0, 50, 75, 100]);

it('menilai kepatuhan terhadap ambang pengguna, bukan terhadap zona warna', function () {
    // Skor 80 berada di zona hijau, tapi bagi pengguna berambang 90 ia
    // tetap melanggar. Dua pertanyaan berbeda, dua jawaban berbeda.
    expect(ComplianceTone::zone(80))->toBe(ComplianceTone::ZONE_PROFIT)
        ->and(ComplianceTone::obeys(80, 90))->toBeFalse()
        ->and(ComplianceTone::obeys(80, 70))->toBeTrue()
        ->and(ComplianceTone::obeys(80, 80))->toBeTrue();
});

it('menyatakan trade tanpa skor sebagai bukan patuh maupun melanggar', function () {
    expect(ComplianceTone::obeys(null, 70))->toBeNull();
});
