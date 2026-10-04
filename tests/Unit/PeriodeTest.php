<?php

use App\Support\Periode;
use Illuminate\Support\Carbon;

/*
|--------------------------------------------------------------------------
| Rentang waktu dashboard
|--------------------------------------------------------------------------
|
| Kunci periode datang dari query string, jadi ia masukan yang tidak
| dipercaya. Kelas ini satu-satunya tempat kunci itu diterjemahkan menjadi
| tanggal, dan ia WAJIB jatuh ke "Semua" untuk kunci apa pun yang tidak
| dikenal - bukan melempar, dan bukan menghasilkan rentang kosong yang akan
| terbaca sebagai "kamu tidak punya trade".
|
*/

// Tes unit tidak mewarisi TestCase Laravel, jadi tidak ada travelTo() di
// sini. Carbon::setTestNow lebih tepat untuk berkas ini: ia menguncikan waktu
// tanpa membangun framework sama sekali - tapi ia global, jadi WAJIB dilepas.
afterEach(fn () => Carbon::setTestNow());

it('mengenali keempat kunci yang sah', function () {
    expect(array_keys(Periode::daftar()))
        ->toBe([Periode::HARI, Periode::MINGGU, Periode::BULAN, Periode::SEMUA]);
});

it('jatuh ke seluruh riwayat untuk kunci yang tidak dikenal', function (?string $kunci) {
    expect(Periode::dari($kunci)->kunci)->toBe(Periode::SEMUA);
})->with([null, '', 'tahun', 'HARI', '1 OR 1=1', 'hari ']);

it('tidak membatasi tanggal pada seluruh riwayat', function () {
    $p = Periode::dari(Periode::SEMUA);

    expect($p->mulai())->toBeNull()
        ->and($p->sampai())->toBeNull()
        ->and($p->seluruhRiwayat())->toBeTrue();
});

it('membatasi hari ini pada satu hari kalender', function () {
    Carbon::setTestNow('2026-10-04 15:30:00');

    $p = Periode::dari(Periode::HARI);

    expect($p->mulai()->toDateTimeString())->toBe('2026-10-04 00:00:00')
        ->and($p->sampai()->toDateTimeString())->toBe('2026-10-04 23:59:59');
});

it('memulai minggu pada hari Minggu, sama seperti kalender di bawahnya', function () {
    // 4 Oktober 2026 adalah hari Minggu, jadi minggu yang memuat 7 Oktober
    // dimulai pada tanggal 4.
    Carbon::setTestNow('2026-10-07 10:00:00');

    $p = Periode::dari(Periode::MINGGU);

    expect($p->mulai()->toDateString())->toBe('2026-10-04')
        ->and($p->sampai()->toDateString())->toBe('2026-10-10');
});

it('membatasi bulan ini pada bulan kalender', function () {
    Carbon::setTestNow('2026-10-20 10:00:00');

    $p = Periode::dari(Periode::BULAN);

    expect($p->mulai()->toDateString())->toBe('2026-10-01')
        ->and($p->sampai()->toDateString())->toBe('2026-10-31');
});

it('memberi label bahasa Indonesia untuk setiap kunci', function () {
    expect(Periode::dari(Periode::HARI)->label())->toBe('Hari ini')
        ->and(Periode::dari(Periode::MINGGU)->label())->toBe('Minggu ini')
        ->and(Periode::dari(Periode::BULAN)->label())->toBe('Bulan ini')
        ->and(Periode::dari(Periode::SEMUA)->label())->toBe('Semua');
});
