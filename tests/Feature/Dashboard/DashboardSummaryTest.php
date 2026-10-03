<?php

use App\Models\Trade;
use App\Models\TradingSetup;
use App\Models\User;
use App\Support\DashboardSummary;

/*
|--------------------------------------------------------------------------
| Ringkasan dashboard
|--------------------------------------------------------------------------
|
| Angka di layar pertama. Kalau salah di sini, pengguna salah menilai dirinya
| sendiri sejak detik pertama membuka aplikasi.
|
| Berbeda dari halaman Laporan, dashboard bersifat DESKRIPTIF: ia melaporkan
| apa yang sudah terjadi pada data pengguna sendiri. Karena itu tidak ada
| ambang sampel di sini. Yang dilarang adalah klaim inferensial, dan ringkasan
| ini tidak membuat satu pun.
|
*/

function tradeDgn(User $u, TradingSetup $s, float $risk, ?float $pnl, string $tgl, ?int $skor = null): Trade
{
    $t = Trade::factory()->for($u)->for($s, 'setup')->create([
        'risk_amount' => $risk,
        'pnl_amount' => $pnl,
        'opened_at' => $tgl.' 09:00:00',
        'closed_at' => $pnl === null ? null : $tgl.' 15:00:00',
    ]);
    $t->compliance_score = $skor;
    $t->saveQuietly();

    return $t;
}

function ringkasan(): DashboardSummary
{
    return DashboardSummary::from(Trade::with('setup')->get());
}

it('menjumlahkan P&L bersih dari trade tertutup saja', function () {
    $u = User::factory()->create();
    $this->actingAs($u);
    $s = TradingSetup::factory()->for($u)->create();

    tradeDgn($u, $s, 100, 250, '2026-04-01');
    tradeDgn($u, $s, 100, -100, '2026-04-02');
    tradeDgn($u, $s, 100, null, '2026-04-03');

    $r = ringkasan();

    expect($r->netPnl)->toBe(150.0)
        ->and($r->closedCount)->toBe(2)
        ->and($r->openCount)->toBe(1);
});

it('memisahkan rata-rata trade menang dan kalah', function () {
    $u = User::factory()->create();
    $this->actingAs($u);
    $s = TradingSetup::factory()->for($u)->create();

    tradeDgn($u, $s, 100, 300, '2026-04-01');
    tradeDgn($u, $s, 100, 100, '2026-04-02');
    tradeDgn($u, $s, 100, -50, '2026-04-03');

    $r = ringkasan();

    expect($r->avgWin)->toBe(200.0)
        ->and($r->avgLoss)->toBe(-50.0)
        ->and($r->avgTrade)->toBe(116.67);
});

it('menghitung profit factor sebagai laba kotor dibagi rugi kotor', function () {
    $u = User::factory()->create();
    $this->actingAs($u);
    $s = TradingSetup::factory()->for($u)->create();

    tradeDgn($u, $s, 100, 300, '2026-04-01');
    tradeDgn($u, $s, 100, -100, '2026-04-02');
    tradeDgn($u, $s, 100, -50, '2026-04-03');

    expect(ringkasan()->profitFactor)->toBe(2.0);
});

it('mengembalikan profit factor null ketika belum ada trade rugi', function () {
    $u = User::factory()->create();
    $this->actingAs($u);
    $s = TradingSetup::factory()->for($u)->create();

    tradeDgn($u, $s, 100, 300, '2026-04-01');

    // Pembagian dengan nol bukan "tak terhingga" yang layak ditampilkan
    // sebagai angka. Null, lalu antarmuka menjelaskan kenapa.
    expect(ringkasan()->profitFactor)->toBeNull();
});

it('menghitung win rate tanpa menganggap break even sebagai menang', function () {
    $u = User::factory()->create();
    $this->actingAs($u);
    $s = TradingSetup::factory()->for($u)->create();

    tradeDgn($u, $s, 100, 100, '2026-04-01');
    tradeDgn($u, $s, 100, 0, '2026-04-02');
    tradeDgn($u, $s, 100, -100, '2026-04-03');
    tradeDgn($u, $s, 100, 50, '2026-04-04');

    expect(ringkasan()->winRate)->toBe(50.0);
});

it('merata-ratakan skor kepatuhan hanya dari trade yang punya skor', function () {
    $u = User::factory()->create();
    $this->actingAs($u);
    $s = TradingSetup::factory()->for($u)->create();

    tradeDgn($u, $s, 100, 100, '2026-04-01', 90);
    tradeDgn($u, $s, 100, -100, '2026-04-02', 50);
    tradeDgn($u, $s, 100, 100, '2026-04-03', null);

    expect(ringkasan()->avgCompliance)->toBe(70.0);
});

it('menyusun P&L kumulatif berurutan waktu', function () {
    $u = User::factory()->create();
    $this->actingAs($u);
    $s = TradingSetup::factory()->for($u)->create();

    tradeDgn($u, $s, 100, 100, '2026-04-03');
    tradeDgn($u, $s, 100, -40, '2026-04-01');
    tradeDgn($u, $s, 100, 60, '2026-04-02');

    $k = ringkasan()->cumulative;

    expect($k)->toHaveCount(3)
        ->and($k[0]['value'])->toBe(-40.0)
        ->and($k[1]['value'])->toBe(20.0)
        ->and($k[2]['value'])->toBe(120.0);
});

it('menjumlahkan P&L per hari untuk kalender', function () {
    $u = User::factory()->create();
    $this->actingAs($u);
    $s = TradingSetup::factory()->for($u)->create();

    tradeDgn($u, $s, 100, 100, '2026-04-07');
    tradeDgn($u, $s, 100, -30, '2026-04-07');
    tradeDgn($u, $s, 100, 250, '2026-04-09');

    $h = ringkasan()->dailyPnl;

    expect($h['2026-04-07']['pnl'])->toBe(70.0)
        ->and($h['2026-04-07']['count'])->toBe(2)
        ->and($h['2026-04-09']['pnl'])->toBe(250.0)
        ->and($h)->not->toHaveKey('2026-04-08');
});

it('memakai tanggal tutup untuk pengelompokan harian, bukan tanggal buka', function () {
    $u = User::factory()->create();
    $this->actingAs($u);
    $s = TradingSetup::factory()->for($u)->create();

    $t = tradeDgn($u, $s, 100, 100, '2026-04-07');
    $t->closed_at = '2026-04-09 10:00:00';
    $t->saveQuietly();

    // P&L baru ada saat trade ditutup. Mengelompokkannya ke tanggal buka
    // menaruh hasil pada hari yang hasilnya belum diketahui.
    $h = ringkasan()->dailyPnl;

    expect($h)->toHaveKey('2026-04-09')
        ->and($h)->not->toHaveKey('2026-04-07');
});

it('menghasilkan ringkasan kosong tanpa error', function () {
    $u = User::factory()->create();
    $this->actingAs($u);

    $r = ringkasan();

    expect($r->netPnl)->toBe(0.0)
        ->and($r->closedCount)->toBe(0)
        ->and($r->winRate)->toBeNull()
        ->and($r->avgWin)->toBeNull()
        ->and($r->avgLoss)->toBeNull()
        ->and($r->profitFactor)->toBeNull()
        ->and($r->avgCompliance)->toBeNull()
        ->and($r->cumulative)->toBe([])
        ->and($r->dailyPnl)->toBe([])
        ->and($r->isEmpty())->toBeTrue();
});
