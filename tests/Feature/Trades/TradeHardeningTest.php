<?php

use App\Models\Scopes\OwnedByUserScope;
use App\Models\SetupRule;
use App\Models\Trade;
use App\Models\TradeRuleCheck;
use App\Models\TradingSetup;
use App\Models\User;
use App\Support\ComplianceScore;
use Livewire\Volt\Volt;



/*
|--------------------------------------------------------------------------
| Pengerasan hasil review milestone 3
|--------------------------------------------------------------------------
|
| Perilaku di bawah sudah benar sebelum test ini ditulis, tapi belum terjaga.
| Tanpa test, keamanannya hanya kebetulan dari implementasi saat ini.
|
*/

function setupDenganDuaRule(User $user): array
{
    $setup = TradingSetup::factory()->for($user)->create();
    $r1 = SetupRule::factory()->for($user)->for($setup, 'setup')->create(['weight' => 1, 'position' => 0]);
    $r2 = SetupRule::factory()->for($user)->for($setup, 'setup')->create(['weight' => 3, 'position' => 1]);

    return [$setup, $r1, $r2];
}

it('mengabaikan key checks milik rule pengguna lain', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    // Data Bob dibuat lebih dulu: hook kepemilikan menolak pembuatan atas
    // nama pengguna lain saat ada sesi aktif, dan itu memang benar.
    $setupBob = TradingSetup::factory()->for($bob)->create();
    $ruleBob = SetupRule::factory()->for($bob)->for($setupBob, 'setup')->create(['weight' => 5]);

    $this->actingAs($alice);
    $ins = instrumenUji($alice);
    [$setupAlice, $r1] = setupDenganDuaRule($alice);

    Volt::test('trades.record')
        ->set('tradingSetupId', $setupAlice->id)
        ->set('checks', [$r1->id => true, $ruleBob->id => true])
        ->set('instrumentId', $ins->id)
        ->set('lotSize', '0.05')->set('entryPrice', '2000')->set('stopPrice', '1990')
        ->set('openedAt', now()->format('Y-m-d\TH:i'))
        ->call('save')
        ->assertHasNoErrors();

    $trade = Trade::first();

    // Rule Bob tidak ikut tercatat maupun terhitung: 1 dari 4 bobot = 25%.
    expect($trade->ruleChecks()->count())->toBe(2)
        ->and($trade->compliance_score)->toBe(25)
        ->and($trade->ruleChecks()->pluck('setup_rule_id'))->not->toContain($ruleBob->id);
});

it('mengabaikan rule yang sudah diarsipkan saat mencatat', function () {
    $alice = User::factory()->create();
    $this->actingAs($alice);
    $ins = instrumenUji($alice);

    [$setup, $r1, $r2] = setupDenganDuaRule($alice);

    $r2->archived_at = now();
    $r2->save();

    Volt::test('trades.record')
        ->set('tradingSetupId', $setup->id)
        ->set('checks', [$r1->id => true, $r2->id => true])
        ->set('instrumentId', $ins->id)
        ->set('lotSize', '0.05')->set('entryPrice', '2000')->set('stopPrice', '1990')
        ->set('openedAt', now()->format('Y-m-d\TH:i'))
        ->call('save')
        ->assertHasNoErrors();

    $trade = Trade::first();

    expect($trade->ruleChecks()->count())->toBe(1)
        ->and($trade->compliance_score)->toBe(100);
});

it('menjaga skor tetap sama dengan snapshot meski rule berubah sebelum simpan', function () {
    $alice = User::factory()->create();
    $this->actingAs($alice);
    $ins = instrumenUji($alice);

    [$setup, $r1, $r2] = setupDenganDuaRule($alice);

    $komponen = Volt::test('trades.record')
        ->set('tradingSetupId', $setup->id)
        ->set('checks', [$r1->id => true, $r2->id => false]);

    // Tab lain merevisi bobot setelah form dirender, sebelum tombol ditekan.
    $r1->update(['weight' => 9]);

    $komponen->set('instrumentId', $ins->id)
        ->set('lotSize', '0.05')->set('entryPrice', '2000')->set('stopPrice', '1990')
        ->set('openedAt', now()->format('Y-m-d\TH:i'))
        ->call('save')
        ->assertHasNoErrors();

    $trade = Trade::with('ruleChecks')->first();

    $hitungUlang = ComplianceScore::from(
        $trade->ruleChecks->map(fn (TradeRuleCheck $c) => $c->toScoreInput())->all()
    );

    // Inti invariannya: apa pun yang terjadi, kolom skor selalu sama dengan
    // hasil hitung ulang dari checklist yang tersimpan.
    expect($trade->compliance_score)->toBe($hitungUlang->value);
});

it('menghapus akun berikut setup, trade, dan checklist-nya', function () {
    $alice = User::factory()->create();
    $this->actingAs($alice);
    $ins = instrumenUji($alice);

    [$setup, $r1] = setupDenganDuaRule($alice);

    Volt::test('trades.record')
        ->set('tradingSetupId', $setup->id)
        ->set('checks', [$r1->id => true])
        ->set('instrumentId', $ins->id)
        ->set('lotSize', '0.05')->set('entryPrice', '2000')->set('stopPrice', '1990')
        ->set('openedAt', now()->format('Y-m-d\TH:i'))
        ->call('save');

    expect(Trade::count())->toBe(1);

    // restrictOnDelete pada trading_setup_id tidak boleh memblokir penghapusan
    // akun. Kalau test ini gagal, pengguna terjebak tidak bisa menghapus
    // akunnya sendiri.
    $alice->delete();

    expect(Trade::withoutGlobalScope(OwnedByUserScope::class)->count())->toBe(0)
        ->and(TradeRuleCheck::withoutGlobalScope(OwnedByUserScope::class)->count())->toBe(0)
        ->and(TradingSetup::withoutGlobalScope(OwnedByUserScope::class)->count())->toBe(0);
});

it('menolak risiko yang membulat menjadi nol', function () {
    $alice = User::factory()->create();
    $this->actingAs($alice);
    $ins = instrumenUji($alice);

    [$setup] = setupDenganDuaRule($alice);

    // 0.001 lolos "lebih besar dari nol" tapi tersimpan sebagai 0.00 pada
    // decimal(18,2), membuat R-multiple mustahil dihitung.
    Volt::test('trades.record')
        ->set('tradingSetupId', $setup->id)
        ->set('instrumentId', $ins->id)
        ->set('lotSize', '0.1')->set('entryPrice', '2000')->set('stopPrice', '2000')
        ->set('openedAt', now()->format('Y-m-d\TH:i'))
        ->call('save')
        ->assertHasErrors('stopPrice');
});

it('menolak nilai yang melampaui kapasitas kolom', function () {
    $alice = User::factory()->create();
    $this->actingAs($alice);
    $ins = instrumenUji($alice);

    [$setup] = setupDenganDuaRule($alice);

    Volt::test('trades.record')
        ->set('tradingSetupId', $setup->id)
        ->set('instrumentId', $ins->id)
        ->set('lotSize', '1e30')->set('entryPrice', '2000')->set('stopPrice', '1990')
        ->set('openedAt', now()->format('Y-m-d\TH:i'))
        ->call('save')
        ->assertHasErrors('lotSize');
});

it('menampilkan peringatan sesuai ambang milik pengguna', function () {
    $ketat = User::factory()->create(['compliance_threshold' => 95]);
    $this->actingAs($ketat);

    [$setup, $r1, $r2] = setupDenganDuaRule($ketat);

    // Skor 75: di bawah ambang 95, jadi harus diperingatkan.
    Volt::test('trades.record')
        ->set('tradingSetupId', $setup->id)
        ->set('checks', [$r1->id => false, $r2->id => true])
        ->assertSee('Skor di bawah ambangmu');
});

it('tidak memperingatkan ketika skor memenuhi ambang pengguna', function () {
    $longgar = User::factory()->create(['compliance_threshold' => 10]);
    $this->actingAs($longgar);

    [$setup, $r1, $r2] = setupDenganDuaRule($longgar);

    Volt::test('trades.record')
        ->set('tradingSetupId', $setup->id)
        ->set('checks', [$r1->id => false, $r2->id => true])
        ->assertDontSee('Skor di bawah ambangmu');
});
