<?php

use App\Models\SetupRule;
use App\Models\Trade;
use App\Models\TradingSetup;
use App\Models\User;
use Livewire\Volt\Volt;

/*
|--------------------------------------------------------------------------
| Jaminan snapshot rule
|--------------------------------------------------------------------------
|
| Milestone 2 meninggalkan utang: mengedit bobot rule setelah ada trade akan
| membuat laporan historis tidak setara. Jawabannya bukan tabel versi, tapi
| menyalin label, bobot, dan status wajib ke dalam checklist saat trade
| dicatat.
|
| File ini adalah bukti utang itu lunas. Kalau salah satu test di sini gagal,
| seluruh laporan kepatuhan kehilangan dasarnya — perbaiki kodenya, jangan
| test-nya.
|
*/

function catatTrade(User $user, TradingSetup $setup, array $checks, string $symbol = 'XAUUSD'): Trade
{
    Volt::test('trades.record')
        ->set('tradingSetupId', $setup->id)
        ->set('checks', $checks)
        ->set('symbol', $symbol)
        ->set('riskAmount', '50')
        ->set('openedAt', now()->format('Y-m-d\TH:i'))
        ->call('save')
        ->assertHasNoErrors();

    return Trade::where('symbol', $symbol)->firstOrFail();
}

it('tidak mengubah skor trade lama ketika bobot rule diubah', function () {
    $alice = User::factory()->create();
    $this->actingAs($alice);

    $setup = TradingSetup::factory()->for($alice)->create();
    $r1 = SetupRule::factory()->for($alice)->for($setup, 'setup')->create(['weight' => 1, 'position' => 0]);
    $r2 = SetupRule::factory()->for($alice)->for($setup, 'setup')->create(['weight' => 3, 'position' => 1]);

    $trade = catatTrade($alice, $setup, [$r1->id => true, $r2->id => false]);

    expect($trade->compliance_score)->toBe(25);

    // Pengguna merevisi strateginya: bobot dibalik.
    $r1->update(['weight' => 5]);
    $r2->update(['weight' => 1]);

    expect($trade->fresh()->compliance_score)->toBe(25);
});

it('tidak mengubah label pada checklist trade lama ketika rule diganti namanya', function () {
    $alice = User::factory()->create();
    $this->actingAs($alice);

    $setup = TradingSetup::factory()->for($alice)->create();
    $rule = SetupRule::factory()->for($alice)->for($setup, 'setup')->create([
        'label' => 'Ada BOS di H4',
        'weight' => 2,
    ]);

    $trade = catatTrade($alice, $setup, [$rule->id => true]);

    $rule->update(['label' => 'Ada BOS di H1']);

    $check = $trade->ruleChecks()->first();

    expect($check->rule_label)->toBe('Ada BOS di H4')
        ->and($check->rule_weight)->toBe(2);
});

it('tidak mengubah trade lama ketika rule diarsipkan', function () {
    $alice = User::factory()->create();
    $this->actingAs($alice);

    $setup = TradingSetup::factory()->for($alice)->create();
    $r1 = SetupRule::factory()->for($alice)->for($setup, 'setup')->create(['weight' => 1, 'position' => 0]);
    $r2 = SetupRule::factory()->for($alice)->for($setup, 'setup')->create(['weight' => 1, 'position' => 1]);

    $trade = catatTrade($alice, $setup, [$r1->id => true, $r2->id => false]);

    // archived_at sengaja tidak fillable; arsip bukan operasi dari input.
    $r2->archived_at = now();
    $r2->save();

    expect($trade->fresh()->compliance_score)->toBe(50)
        ->and($trade->ruleChecks()->count())->toBe(2);
});

it('tidak mengubah status wajib pada trade lama', function () {
    $alice = User::factory()->create();
    $this->actingAs($alice);

    $setup = TradingSetup::factory()->for($alice)->create();
    $rule = SetupRule::factory()->for($alice)->for($setup, 'setup')->create([
        'weight' => 3,
        'is_required' => false,
    ]);

    $trade = catatTrade($alice, $setup, [$rule->id => false]);

    expect($trade->ruleChecks()->first()->rule_required)->toBeFalse();

    $rule->update(['is_required' => true]);

    // Trade itu dicatat ketika rule-nya belum wajib. Mengubahnya sekarang
    // akan menuduh pengguna melanggar aturan yang saat itu belum ada.
    expect($trade->fresh()->ruleChecks()->first()->rule_required)->toBeFalse();
});

it('hanya memengaruhi trade baru ketika rule direvisi', function () {
    $alice = User::factory()->create();
    $this->actingAs($alice);

    $setup = TradingSetup::factory()->for($alice)->create();
    $r1 = SetupRule::factory()->for($alice)->for($setup, 'setup')->create(['weight' => 1, 'position' => 0]);
    $r2 = SetupRule::factory()->for($alice)->for($setup, 'setup')->create(['weight' => 3, 'position' => 1]);

    $lama = catatTrade($alice, $setup, [$r1->id => true, $r2->id => false], 'LAMA');
    expect($lama->compliance_score)->toBe(25);

    $r1->update(['weight' => 3]);
    $r2->update(['weight' => 1]);

    $baru = catatTrade($alice, $setup, [$r1->id => true, $r2->id => false], 'BARU');

    expect($baru->compliance_score)->toBe(75)
        ->and($lama->fresh()->compliance_score)->toBe(25);
});

it('mempertahankan checklist meski rule aslinya dihapus', function () {
    $alice = User::factory()->create();
    $this->actingAs($alice);

    $setup = TradingSetup::factory()->for($alice)->create();
    $rule = SetupRule::factory()->for($alice)->for($setup, 'setup')->create([
        'label' => 'Kriteria yang kelak dihapus',
        'weight' => 4,
    ]);

    $trade = catatTrade($alice, $setup, [$rule->id => true]);

    $rule->delete();

    $check = $trade->fresh()->ruleChecks()->first();

    // setup_rule_id jadi null, tapi snapshot-nya tetap utuh. Inilah alasan
    // kolom itu nullOnDelete dan bukan cascade.
    expect($check)->not->toBeNull()
        ->and($check->setup_rule_id)->toBeNull()
        ->and($check->rule_label)->toBe('Kriteria yang kelak dihapus')
        ->and($check->rule_weight)->toBe(4)
        ->and($trade->fresh()->compliance_score)->toBe(100);
});
