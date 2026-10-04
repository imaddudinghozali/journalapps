<?php

use App\Models\Trade;
use App\Models\TradeRuleCheck;
use App\Models\TradingSetup;
use App\Models\User;
use App\Support\DashboardMetrics;
use App\Support\TradeStatistics;

/*
|--------------------------------------------------------------------------
| Angka dashboard
|--------------------------------------------------------------------------
|
| Dashboard adalah layar pertama. Kalau salah di sini, pengguna salah menilai
| dirinya sendiri sejak detik pertama membuka aplikasi.
|
| Yang diuji terutama kasus tepi, karena itulah yang akan ditemui pengguna
| baru: nol trade, kurang dari sepuluh, semua patuh, semua melanggar. Jalur
| bahagia justru yang paling kecil kemungkinannya salah.
|
*/

function metrik(int $ambang = 80): DashboardMetrics
{
    return DashboardMetrics::from(
        Trade::with(['setup', 'ruleChecks'])->get(),
        $ambang,
    );
}

function tradeR(User $u, TradingSetup $s, float $r, ?int $skor, string $tgl, array $checks = []): Trade
{
    $t = Trade::factory()->for($u)->for($s, 'setup')->create([
        'risk_amount' => 100,
        'pnl_amount' => $r * 100,
        'opened_at' => $tgl.' 09:00:00',
        'closed_at' => $tgl.' 14:00:00',
    ]);

    $t->compliance_score = $skor;
    $t->saveQuietly();

    foreach ($checks as $posisi => [$label, $bobot, $wajib, $terpenuhi]) {
        $c = new TradeRuleCheck([
            'is_met' => $terpenuhi,
            'rule_label' => $label,
            'rule_weight' => $bobot,
            'rule_required' => $wajib,
            'position' => $posisi,
        ]);
        $c->user_id = $u->id;
        $c->trade_id = $t->id;
        $c->saveQuietly();
    }

    return $t;
}

function penggunaUji(): array
{
    $u = User::factory()->create();
    test()->actingAs($u);

    return [$u, TradingSetup::factory()->for($u)->create()];
}

it('tidak pecah ketika belum ada satu trade pun', function () {
    penggunaUji();

    $m = metrik();

    expect($m->isEmpty())->toBeTrue()
        ->and($m->expectancyR)->toBeNull()
        ->and($m->avgWinR)->toBeNull()
        ->and($m->avgLossR)->toBeNull()
        ->and($m->avgCompliance)->toBeNull()
        ->and($m->complianceDelta)->toBeNull()
        ->and($m->complianceStreak)->toBe(0)
        ->and($m->requiredViolations)->toBe(0)
        ->and($m->cumulativeR)->toBe([])
        ->and($m->drawdownR)->toBe([])
        ->and($m->topViolations)->toBe([])
        ->and($m->recentTrades)->toBe([]);
});

it('menahan kalimat insight sampai tiap kelompok punya sepuluh trade', function () {
    [$u, $s] = penggunaUji();

    foreach (range(1, 9) as $i) {
        tradeR($u, $s, 1.0, 95, '2026-09-'.str_pad((string) $i, 2, '0', STR_PAD_LEFT));
        tradeR($u, $s, -1.0, 40, '2026-09-'.str_pad((string) ($i + 10), 2, '0', STR_PAD_LEFT));
    }

    $m = metrik();

    // Sembilan di tiap kelompok: belum cukup, jadi angkanya ditahan dan yang
    // ditampilkan kemajuannya menuju ambang sampel.
    expect($m->insightSiap())->toBeFalse()
        ->and($m->insightKemajuan())->toBe([9, TradeStatistics::MIN_SAMPLE]);
});

it('membuka kalimat insight begitu kedua kelompok cukup', function () {
    [$u, $s] = penggunaUji();

    foreach (range(1, 10) as $i) {
        tradeR($u, $s, 1.2, 95, '2026-09-'.str_pad((string) $i, 2, '0', STR_PAD_LEFT));
        tradeR($u, $s, -0.8, 40, '2026-10-'.str_pad((string) $i, 2, '0', STR_PAD_LEFT));
    }

    $m = metrik();

    expect($m->insightSiap())->toBeTrue()
        ->and($m->compliance->compliant->expectancy)->toEqualWithDelta(1.2, 0.01)
        ->and($m->compliance->nonCompliant->expectancy)->toEqualWithDelta(-0.8, 0.01);
});

it('tetap aman ketika semua trade patuh', function () {
    [$u, $s] = penggunaUji();

    foreach (range(1, 12) as $i) {
        tradeR($u, $s, 1.0, 95, '2026-09-'.str_pad((string) $i, 2, '0', STR_PAD_LEFT));
    }

    $m = metrik();

    // Kelompok pelanggar kosong: perbandingannya tidak bisa dibuat, dan itu
    // harus dinyatakan sebagai belum bisa dinilai, bukan sebagai nol.
    expect($m->insightSiap())->toBeFalse()
        ->and($m->compliance->nonCompliant->hasEnoughSample())->toBeFalse()
        ->and($m->expectancyR)->toEqualWithDelta(1.0, 0.01)
        ->and($m->avgLossR)->toBeNull()
        ->and($m->profitFactor)->toBeNull();
});

it('tetap aman ketika semua trade melanggar', function () {
    [$u, $s] = penggunaUji();

    foreach (range(1, 12) as $i) {
        tradeR($u, $s, -1.0, 20, '2026-09-'.str_pad((string) $i, 2, '0', STR_PAD_LEFT));
    }

    $m = metrik();

    expect($m->insightSiap())->toBeFalse()
        ->and($m->expectancyR)->toEqualWithDelta(-1.0, 0.01)
        ->and($m->avgWinR)->toBeNull()
        ->and($m->complianceStreak)->toBe(0);
});

it('menghitung expectancy dan rata-rata menang kalah dalam R', function () {
    [$u, $s] = penggunaUji();

    tradeR($u, $s, 2.0, 90, '2026-09-01');
    tradeR($u, $s, 1.0, 90, '2026-09-02');
    tradeR($u, $s, -1.0, 90, '2026-09-03');
    tradeR($u, $s, -1.0, 90, '2026-09-04');

    $m = metrik();

    expect($m->expectancyR)->toEqualWithDelta(0.25, 0.01)
        ->and($m->avgWinR)->toEqualWithDelta(1.5, 0.01)
        ->and($m->avgLossR)->toEqualWithDelta(-1.0, 0.01)
        ->and($m->profitFactor)->toEqualWithDelta(1.5, 0.01);
});

it('menghitung rentetan trade patuh dari yang paling baru', function () {
    [$u, $s] = penggunaUji();

    tradeR($u, $s, 1.0, 95, '2026-09-01');
    tradeR($u, $s, 1.0, 40, '2026-09-02');
    tradeR($u, $s, 1.0, 95, '2026-09-03');
    tradeR($u, $s, 1.0, 88, '2026-09-04');

    // Dihitung mundur dari trade terakhir dan berhenti di pelanggaran
    // pertama: dua, bukan tiga.
    expect(metrik()->complianceStreak)->toBe(2);
});

it('menghitung selisih kepatuhan terhadap tujuh hari sebelumnya', function () {
    [$u, $s] = penggunaUji();

    tradeR($u, $s, 1.0, 60, now()->subDays(10)->toDateString());
    tradeR($u, $s, 1.0, 90, now()->subDays(2)->toDateString());

    expect(metrik()->complianceDelta)->toBe(30);
});

it('tidak memberi selisih ketika salah satu jendela kosong', function () {
    [$u, $s] = penggunaUji();

    tradeR($u, $s, 1.0, 90, now()->subDays(2)->toDateString());

    expect(metrik()->complianceDelta)->toBeNull();
});

it('menghitung pelanggaran rule wajib saja', function () {
    [$u, $s] = penggunaUji();

    tradeR($u, $s, 1.0, 70, '2026-09-01', [
        ['Wajib dilanggar', 3, true, false],
        ['Wajib dipatuhi', 3, true, true],
        ['Opsional dilanggar', 1, false, false],
    ]);

    // Rule opsional yang tidak tercentang bukan pelanggaran: pengguna sendiri
    // yang menandainya tidak wajib.
    expect(metrik()->requiredViolations)->toBe(1);
});

it('membangun kurva R kumulatif beserta kepatuhan tiap titiknya', function () {
    [$u, $s] = penggunaUji();

    tradeR($u, $s, 1.0, 95, '2026-09-01');
    tradeR($u, $s, -0.5, 40, '2026-09-02');

    $titik = metrik()->cumulativeR;

    expect($titik)->toHaveCount(2)
        ->and($titik[0]['cumulative'])->toEqualWithDelta(1.0, 0.01)
        ->and($titik[0]['compliant'])->toBeTrue()
        ->and($titik[1]['cumulative'])->toEqualWithDelta(0.5, 0.01)
        ->and($titik[1]['compliant'])->toBeFalse();
});

it('menghitung drawdown dari puncak tertinggi, dan nol saat sedang di puncak', function () {
    [$u, $s] = penggunaUji();

    tradeR($u, $s, 2.0, 90, '2026-09-01');
    tradeR($u, $s, -1.0, 90, '2026-09-02');
    tradeR($u, $s, -0.5, 90, '2026-09-03');
    tradeR($u, $s, 3.0, 90, '2026-09-04');

    $m = metrik();
    $dd = array_column($m->drawdownR, 'drawdown');

    expect($dd[0])->toEqualWithDelta(0.0, 0.01)
        ->and($dd[1])->toEqualWithDelta(-1.0, 0.01)
        ->and($dd[2])->toEqualWithDelta(-1.5, 0.01)
        ->and($dd[3])->toEqualWithDelta(0.0, 0.01)
        ->and($m->maxDrawdownR)->toEqualWithDelta(-1.5, 0.01);
});

it('mengambil lima trade terakhir saja, yang terbaru lebih dulu', function () {
    [$u, $s] = penggunaUji();

    foreach (range(1, 8) as $i) {
        tradeR($u, $s, 1.0, 90, '2026-09-0'.$i);
    }

    $terakhir = metrik()->recentTrades;

    expect($terakhir)->toHaveCount(5)
        ->and($terakhir[0]['trade']->closed_at->day)->toBe(8)
        ->and($terakhir[4]['trade']->closed_at->day)->toBe(4);
});

it('tidak membocorkan angka pengguna lain', function () {
    $alice = User::factory()->create();
    $setupAlice = TradingSetup::factory()->for($alice)->create();
    tradeR($alice, $setupAlice, 5.0, 100, '2026-09-01');

    [$bob, $setupBob] = penggunaUji();
    tradeR($bob, $setupBob, -1.0, 30, '2026-09-02');

    $m = metrik();

    // Global scope yang menyaring, bukan filter manual di kelas ini.
    expect($m->expectancyR)->toEqualWithDelta(-1.0, 0.01)
        ->and($m->summary->closedCount)->toBe(1);
});
