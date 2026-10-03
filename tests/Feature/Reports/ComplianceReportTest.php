<?php

use App\Models\SetupRule;
use App\Models\Trade;
use App\Models\TradeRuleCheck;
use App\Models\TradingSetup;
use App\Models\User;
use App\Support\ComplianceReport;
use App\Support\TradeStatistics;

/*
|--------------------------------------------------------------------------
| Agregasi laporan kepatuhan
|--------------------------------------------------------------------------
|
| Inilah angka yang dipakai pengguna untuk memutuskan setup mana yang dibuang.
| Dua hal yang paling penting dijaga di sini:
|
| 1. Angka dari sampel kecil TIDAK ditampilkan. PRD menilai ini risiko Tinggi.
| 2. Agregasi per rule memakai setup_rule_id, bukan rule_label — label bisa
|    berubah antar trade, dan mengelompokkan lewat label akan memecah satu
|    rule menjadi beberapa baris palsu.
|
*/

function tradeTertutup(User $user, TradingSetup $setup, float $risk, float $pnl, ?int $score): Trade
{
    $trade = Trade::factory()->for($user)->for($setup, 'setup')->create([
        'risk_amount' => $risk,
        'pnl_amount' => $pnl,
        'opened_at' => now()->subDays(2),
        'closed_at' => now()->subDay(),
    ]);

    $trade->compliance_score = $score;
    $trade->saveQuietly();

    return $trade->fresh();
}

function muatTrade(): Illuminate\Support\Collection
{
    // ComplianceReport mensyaratkan setup dan ruleChecks sudah termuat.
    return Trade::with(['setup', 'ruleChecks'])->closed()->get();
}

it('memisahkan trade patuh dari yang tidak patuh memakai ambang pengguna', function () {
    $alice = User::factory()->create(['compliance_threshold' => 80]);
    $this->actingAs($alice);
    $setup = TradingSetup::factory()->for($alice)->create();

    foreach ([90, 85, 100] as $skor) {
        tradeTertutup($alice, $setup, 100, 200, $skor);
    }
    foreach ([40, 20] as $skor) {
        tradeTertutup($alice, $setup, 100, -100, $skor);
    }

    $laporan = ComplianceReport::from(muatTrade(), 80);

    expect($laporan->compliant->sampleSize)->toBe(3)
        ->and($laporan->nonCompliant->sampleSize)->toBe(2);
});

it('mengeluarkan trade tanpa skor dari perbandingan kepatuhan', function () {
    $alice = User::factory()->create();
    $this->actingAs($alice);
    $setup = TradingSetup::factory()->for($alice)->create();

    tradeTertutup($alice, $setup, 100, 200, 90);
    tradeTertutup($alice, $setup, 100, -100, null);
    tradeTertutup($alice, $setup, 100, 50, null);

    $laporan = ComplianceReport::from(muatTrade(), 80);

    // Trade tanpa skor bukan patuh dan bukan tidak patuh. Memasukkannya ke
    // salah satu kelompok akan mencemari perbandingannya.
    expect($laporan->compliant->sampleSize)->toBe(1)
        ->and($laporan->nonCompliant->sampleSize)->toBe(0)
        ->and($laporan->unscoredCount)->toBe(2)
        ->and($laporan->overall->sampleSize)->toBe(3);
});

it('menyembunyikan angka kelompok yang sampelnya di bawah ambang', function () {
    $alice = User::factory()->create();
    $this->actingAs($alice);
    $setup = TradingSetup::factory()->for($alice)->create();

    for ($i = 0; $i < 9; $i++) {
        tradeTertutup($alice, $setup, 100, 100, 90);
    }

    $laporan = ComplianceReport::from(muatTrade(), 80);

    expect($laporan->compliant->sampleSize)->toBe(9)
        ->and($laporan->compliant->hasEnoughSample())->toBeFalse();
});

it('menampilkan angka begitu sampel mencukupi', function () {
    $alice = User::factory()->create();
    $this->actingAs($alice);
    $setup = TradingSetup::factory()->for($alice)->create();

    for ($i = 0; $i < 10; $i++) {
        tradeTertutup($alice, $setup, 100, 100, 90);
    }

    $laporan = ComplianceReport::from(muatTrade(), 80);

    expect($laporan->compliant->hasEnoughSample())->toBeTrue()
        ->and($laporan->compliant->winRate)->toBe(100.0)
        ->and($laporan->compliant->expectancy)->toBe(1.0);
});

it('memecah statistik per setup', function () {
    $alice = User::factory()->create();
    $this->actingAs($alice);

    $bos = TradingSetup::factory()->for($alice)->create(['name' => 'Break of Structure']);
    $ob = TradingSetup::factory()->for($alice)->create(['name' => 'Order Block']);

    tradeTertutup($alice, $bos, 100, 200, 90);
    tradeTertutup($alice, $bos, 100, 100, 90);
    tradeTertutup($alice, $ob, 100, -100, 50);

    $laporan = ComplianceReport::from(muatTrade(), 80);
    $perSetup = collect($laporan->perSetup)->keyBy('name');

    expect($perSetup['Break of Structure']['stats']->sampleSize)->toBe(2)
        ->and($perSetup['Break of Structure']['stats']->expectancy)->toBe(1.5)
        ->and($perSetup['Order Block']['stats']->sampleSize)->toBe(1)
        ->and($perSetup['Order Block']['stats']->expectancy)->toBe(-1.0);
});

it('mengelompokkan dampak rule lewat setup_rule_id meski labelnya berubah', function () {
    $alice = User::factory()->create();
    $this->actingAs($alice);
    $setup = TradingSetup::factory()->for($alice)->create();
    $rule = SetupRule::factory()->for($alice)->for($setup, 'setup')->create([
        'label' => 'Nama lama',
        'weight' => 3,
    ]);

    $t1 = tradeTertutup($alice, $setup, 100, 200, 100);
    TradeRuleCheck::factory()->for($alice)->for($t1)->create([
        'setup_rule_id' => $rule->id,
        'is_met' => true,
        'rule_label' => 'Nama lama',
        'rule_weight' => 3,
    ]);

    $t2 = tradeTertutup($alice, $setup, 100, -100, 0);
    TradeRuleCheck::factory()->for($alice)->for($t2)->create([
        'setup_rule_id' => $rule->id,
        'is_met' => false,
        'rule_label' => 'Nama baru',
        'rule_weight' => 3,
    ]);

    $laporan = ComplianceReport::from(muatTrade(), 80);

    // Satu rule, satu baris — bukan dua baris karena labelnya berbeda.
    expect($laporan->perRule)->toHaveCount(1);

    $baris = $laporan->perRule[0];

    expect($baris['rule_id'])->toBe($rule->id)
        ->and($baris['violations'])->toBe(1)
        ->and($baris['met']->expectancy)->toBe(2.0)
        ->and($baris['unmet']->expectancy)->toBe(-1.0);
});

it('memakai label snapshot terakhir untuk rule yang sudah dihapus', function () {
    $alice = User::factory()->create();
    $this->actingAs($alice);
    $setup = TradingSetup::factory()->for($alice)->create();

    $trade = tradeTertutup($alice, $setup, 100, 100, 100);
    TradeRuleCheck::factory()->for($alice)->for($trade)->create([
        'setup_rule_id' => null,
        'is_met' => false,
        'rule_label' => 'Rule yang sudah dihapus',
        'rule_weight' => 2,
    ]);

    $laporan = ComplianceReport::from(muatTrade(), 80);

    expect($laporan->perRule[0]['label'])->toBe('Rule yang sudah dihapus')
        ->and($laporan->perRule[0]['rule_id'])->toBeNull();
});

it('mengurutkan rule dari yang paling sering dilanggar', function () {
    $alice = User::factory()->create();
    $this->actingAs($alice);
    $setup = TradingSetup::factory()->for($alice)->create();

    $jarang = SetupRule::factory()->for($alice)->for($setup, 'setup')->create(['label' => 'Jarang dilanggar']);
    $sering = SetupRule::factory()->for($alice)->for($setup, 'setup')->create(['label' => 'Sering dilanggar']);

    foreach ([0, 1, 2] as $i) {
        $t = tradeTertutup($alice, $setup, 100, 100, 50);
        TradeRuleCheck::factory()->for($alice)->for($t)->create([
            'setup_rule_id' => $jarang->id,
            'is_met' => $i === 0,
            'rule_label' => 'Jarang dilanggar',
        ]);
        TradeRuleCheck::factory()->for($alice)->for($t)->create([
            'setup_rule_id' => $sering->id,
            'is_met' => false,
            'rule_label' => 'Sering dilanggar',
        ]);
    }

    $laporan = ComplianceReport::from(muatTrade(), 80);

    expect($laporan->perRule[0]['label'])->toBe('Sering dilanggar')
        ->and($laporan->perRule[0]['violations'])->toBe(3);
});

it('menghasilkan laporan kosong tanpa error ketika belum ada trade', function () {
    $alice = User::factory()->create();
    $this->actingAs($alice);

    $laporan = ComplianceReport::from(muatTrade(), 80);

    expect($laporan->overall->sampleSize)->toBe(0)
        ->and($laporan->overall->winRate)->toBeNull()
        ->and($laporan->perSetup)->toBe([])
        ->and($laporan->perRule)->toBe([])
        ->and($laporan->isEmpty())->toBeTrue();
});

it('memakai ambang sampel yang sama dengan TradeStatistics', function () {
    expect(ComplianceReport::MIN_SAMPLE)->toBe(TradeStatistics::MIN_SAMPLE);
});

it('menghitung skor tepat di ambang sebagai patuh', function () {
    $alice = User::factory()->create();
    $this->actingAs($alice);
    $setup = TradingSetup::factory()->for($alice)->create();

    // Tepat 80 dengan ambang 80. Kalau perbandingannya regresi dari >= jadi >,
    // trade ini pindah kelompok tanpa ada yang sadar.
    tradeTertutup($alice, $setup, 100, 100, 80);
    tradeTertutup($alice, $setup, 100, -100, 79);

    $laporan = ComplianceReport::from(muatTrade(), 80);

    expect($laporan->compliant->sampleSize)->toBe(1)
        ->and($laporan->nonCompliant->sampleSize)->toBe(1);
});

it('mengabaikan trade tertutup yang R-nya tidak bisa dihitung', function () {
    $alice = User::factory()->create();
    $this->actingAs($alice);
    $setup = TradingSetup::factory()->for($alice)->create();

    $rusak = tradeTertutup($alice, $setup, 100, 100, 90);
    $rusak->risk_amount = 0;
    $rusak->saveQuietly();

    tradeTertutup($alice, $setup, 100, 100, 90);

    $laporan = ComplianceReport::from(muatTrade(), 80);

    // Risiko nol membuat R mustahil dihitung. Form sudah menolaknya, jadi ini
    // hanya bisa datang dari impor atau data lama.
    expect($laporan->overall->sampleSize)->toBe(1);
});

it('memisahkan kelompok yang sampelnya cukup dari yang belum', function () {
    $alice = User::factory()->create();
    $this->actingAs($alice);
    $setup = TradingSetup::factory()->for($alice)->create();

    for ($i = 0; $i < 10; $i++) {
        tradeTertutup($alice, $setup, 100, 100, 90);
    }
    for ($i = 0; $i < 3; $i++) {
        tradeTertutup($alice, $setup, 100, -100, 20);
    }

    $laporan = ComplianceReport::from(muatTrade(), 80);

    // Satu kelompok cukup, satu belum. Kecukupan dinilai per kelompok, bukan
    // dari total keseluruhan.
    expect($laporan->compliant->hasEnoughSample())->toBeTrue()
        ->and($laporan->nonCompliant->hasEnoughSample())->toBeFalse()
        ->and($laporan->nonCompliant->sampleSize)->toBe(3);
});

it('menghitung trade yang checklist-nya pernah direvisi', function () {
    $alice = User::factory()->create();
    $this->actingAs($alice);
    $setup = TradingSetup::factory()->for($alice)->create();

    tradeTertutup($alice, $setup, 100, 100, 90);

    $direvisi = tradeTertutup($alice, $setup, 100, 200, 100);
    $direvisi->checklist_revised_at = now();
    $direvisi->original_compliance_score = 40;
    $direvisi->saveQuietly();

    $laporan = ComplianceReport::from(muatTrade(), 80);

    // Angka kepatuhan tidak boleh dibaca tanpa tahu berapa yang jawabannya
    // diubah setelah hasilnya kelihatan.
    expect($laporan->revisedCount)->toBe(1);
});
