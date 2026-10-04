<?php

use App\Models\Trade;
use App\Models\TradingSetup;
use App\Models\User;
use App\Support\Periode;
use Livewire\Volt\Volt;

/*
|--------------------------------------------------------------------------
| Rentang waktu dan total P&L di dashboard
|--------------------------------------------------------------------------
|
| Dua hal yang dijaga di sini, dan keduanya gampang rusak bersamaan.
|
| 1. Total P&L dalam dolar memang tampil. R adalah satuan pokok halaman ini,
|    tapi dolar adalah satuan yang dirasakan - menghilangkannya sama sekali
|    membuat pengguna harus menghitung sendiri.
|
| 2. Penyaringan periode hanya menyentuh angka ringkasan. Kalender di
|    bawahnya punya navigasi bulan sendiri dan TIDAK boleh ikut tersaring;
|    kalender yang disaring ke "Hari ini" cuma menyisakan satu sel berwarna
|    dan kehilangan seluruh gunanya.
|
| Rentetan patuh juga diuji di sini karena ia perkecualian: rentetan adalah
| hitungan mundur dari trade terbaru, jadi menyaringnya ke periode akan
| memotongnya jadi angka yang lebih kecil dari kenyataan.
|
*/

function tradePeriode(User $u, TradingSetup $s, float $pnl, string $tutup, ?int $skor = null): Trade
{
    return Trade::factory()->for($u)->for($s, 'setup')->create([
        'risk_amount' => 100,
        'pnl_amount' => $pnl,
        'compliance_score' => $skor,
        'opened_at' => $tutup,
        'closed_at' => $tutup,
    ]);
}

/** @return array{0:User, 1:TradingSetup} */
function penggunaDasbor(): array
{
    $u = User::factory()->create(['compliance_threshold' => 80]);
    test()->actingAs($u);

    return [$u, TradingSetup::factory()->for($u)->create()];
}

beforeEach(function () {
    // 4 Oktober 2026 adalah hari Minggu, jadi "Minggu ini" dan "Hari ini"
    // dimulai pada tanggal yang sama - dipilih justru supaya kedua kasus
    // tidak bisa lolos karena kebetulan batasnya berbeda.
    $this->travelTo('2026-10-04 12:00:00');
});

it('menampilkan total P&L dalam dolar', function () {
    [$u, $s] = penggunaDasbor();

    tradePeriode($u, $s, 250.50, '2026-10-04 09:00:00');
    tradePeriode($u, $s, 749.50, '2026-08-25 09:00:00');

    Volt::test('dashboard.overview')
        ->assertSee('Total P&L')
        ->assertSee('+$1,000.00');
});

it('memulai dari seluruh riwayat', function () {
    [$u, $s] = penggunaDasbor();
    tradePeriode($u, $s, 100.0, '2026-10-04 09:00:00');

    Volt::test('dashboard.overview')
        ->assertSet('periode', Periode::SEMUA)
        ->assertSee('Hari ini')
        ->assertSee('Minggu ini')
        ->assertSee('Bulan ini');
});

it('menyempitkan total ke hari ini', function () {
    [$u, $s] = penggunaDasbor();

    tradePeriode($u, $s, 250.50, '2026-10-04 09:00:00');
    tradePeriode($u, $s, 749.50, '2026-08-25 09:00:00');

    /*
    | Diperiksa lewat urutan, bukan lewat ketiadaan angka riwayat.
    |
    | Versi pertama test ini memastikan +$1,000.00 TIDAK muncul sama sekali.
    | Itu proksi yang salah: kartu Total P&L memang sengaja ikut mencetak
    | angka seluruh riwayat di bawah angka pokoknya, supaya menyempitkan
    | rentang tidak pernah terasa seperti kehilangan uang.
    |
    | Maksud yang dijaga tetap sama dan sekarang diperiksa apa adanya: angka
    | POKOK kartu adalah angka periode, dan angka riwayat ada di bawahnya
    | dengan label yang menyebut dirinya.
    */
    Volt::test('dashboard.overview')
        ->call('pilihPeriode', Periode::HARI)
        ->assertSeeInOrder(['Total P&L', '+$250.50', 'Sepanjang riwayat', '+$1,000.00']);
});

it('menyempitkan total ke bulan ini', function () {
    [$u, $s] = penggunaDasbor();

    tradePeriode($u, $s, 120.25, '2026-10-02 09:00:00');
    tradePeriode($u, $s, 880.00, '2026-09-20 09:00:00');

    Volt::test('dashboard.overview')
        ->call('pilihPeriode', Periode::BULAN)
        ->assertSeeInOrder(['Total P&L', '+$120.25', 'Sepanjang riwayat', '+$1,000.25']);
});

it('jatuh ke seluruh riwayat ketika kunci periode tidak dikenal', function () {
    [$u, $s] = penggunaDasbor();

    tradePeriode($u, $s, 250.50, '2026-10-04 09:00:00');
    tradePeriode($u, $s, 749.50, '2026-08-25 09:00:00');

    // Kuncinya datang dari query string, jadi nilai sembarang harus berakhir
    // sebagai "Semua" - bukan rentang kosong yang terbaca seolah datanya hilang.
    Volt::test('dashboard.overview')
        ->set('periode', 'tahun-lalu')
        ->assertSee('+$1,000.00');
});

it('mengatakan periodenya kosong, bukan mengajak mencatat trade pertama', function () {
    [$u, $s] = penggunaDasbor();
    tradePeriode($u, $s, 749.50, '2026-08-25 09:00:00');

    Volt::test('dashboard.overview')
        ->call('pilihPeriode', Periode::HARI)
        ->assertSee('Tidak ada trade tertutup')
        ->assertDontSee('trade pertamamu');
});

it('tidak ikut menyaring kalender', function () {
    [$u, $s] = penggunaDasbor();

    tradePeriode($u, $s, 900.00, '2026-10-01 09:00:00');
    tradePeriode($u, $s, 250.50, '2026-10-04 09:00:00');

    // Diperiksa pada data kalendernya sendiri, bukan lewat ketiadaan angka
    // lain di halaman: yang dijaga adalah sel 1 Oktober tetap berisi
    // hasilnya walau ringkasan di atas disempitkan ke hari ini.
    $komponen = Volt::test('dashboard.overview')->call('pilihPeriode', Periode::HARI);

    $sel = collect($komponen->instance()->calendarWeeks())
        ->flatMap(fn (array $minggu) => $minggu['days'])
        ->firstWhere('key', '2026-10-01');

    expect($sel['pnl'])->toBe(900.0)
        ->and($sel['count'])->toBe(1);

    $komponen->assertSee('+$900.00');
});

it('menghitung rentetan patuh dari seluruh riwayat, bukan dari periode terpilih', function () {
    [$u, $s] = penggunaDasbor();

    tradePeriode($u, $s, 100.0, '2026-09-28 09:00:00', 90);
    tradePeriode($u, $s, 100.0, '2026-09-29 09:00:00', 90);
    tradePeriode($u, $s, 100.0, '2026-09-30 09:00:00', 90);
    tradePeriode($u, $s, 100.0, '2026-10-04 09:00:00', 90);

    Volt::test('dashboard.overview')
        ->call('pilihPeriode', Periode::HARI)
        ->assertSee('4 trade patuh berturut-turut');
});

it('mengeluarkan posisi terbuka dari slice periode', function () {
    [$u, $s] = penggunaDasbor();

    $tertutup = tradePeriode($u, $s, 100.0, '2026-10-02 09:00:00');

    Trade::factory()->for($u)->for($s, 'setup')->create([
        'risk_amount' => 100,
        'pnl_amount' => null,
        'opened_at' => '2026-10-02 09:00:00',
        'closed_at' => null,
    ]);

    // Posisi terbuka belum punya hasil, jadi ia bukan milik periode mana pun.
    // Bagian "Posisi terbuka" di atas dashboard tetap tidak tersaring.
    $slice = Periode::dari(Periode::BULAN)->saring(Trade::all());

    expect($slice->pluck('id')->all())->toBe([$tertutup->id]);
});
