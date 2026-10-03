<?php

use App\Models\SetupRule;
use App\Models\Trade;
use App\Models\TradeRuleCheck;
use App\Models\TradingSetup;
use App\Models\User;
use Livewire\Volt\Volt;

/*
|--------------------------------------------------------------------------
| Mengubah dan menutup trade
|--------------------------------------------------------------------------
|
| Mencatat posisi yang masih terbuka tidak ada gunanya kalau tidak bisa
| ditutup belakangan.
|
| Satu hal yang dijaga keras di sini: checklist kepatuhan TIDAK bisa diubah
| setelah tersimpan. Kalau bisa, pengguna dapat mencentang ulang setelah
| melihat hasilnya supaya skornya bagus dan strateginya yang disalahkan.
| Itu persis kegagalan yang produk ini ada untuk mencegah.
|
*/

function tradeTerbuka(User $u, float $ukuran = 100): Trade
{
    $setup = TradingSetup::factory()->for($u)->create();
    $ins = instrumenUji($u, $ukuran);

    $t = Trade::factory()->for($u)->for($setup, 'setup')->create([
        'symbol' => $ins->symbol,
        'direction' => Trade::DIRECTION_LONG,
        'lot_size' => 0.5,
        'entry_price' => 2000,
        'stop_price' => 1990,
        'exit_price' => null,
        'pnl_amount' => null,
        'closed_at' => null,
        'risk_amount' => 500,
        'contract_size' => $ukuran,
        'instrument_id' => $ins->id,
        'compliance_score' => 50,
    ]);

    return $t->fresh();
}

it('membuka halaman ubah untuk trade sendiri', function () {
    $alice = User::factory()->create();
    $this->actingAs($alice);
    $trade = tradeTerbuka($alice);

    $this->get(route('trades.edit', $trade))
        ->assertOk()
        ->assertSee('Posisi masih terbuka');
});

it('menutup posisi dan menghitung hasilnya', function () {
    $alice = User::factory()->create();
    $this->actingAs($alice);
    $trade = tradeTerbuka($alice);

    Volt::test('trades.edit', ['trade' => $trade])
        ->set('exitPrice', '2005')
        ->set('closedAt', now()->format('Y-m-d\TH:i'))
        ->call('save')
        ->assertHasNoErrors();

    $segar = $trade->fresh();

    // (2005 - 2000) x 0,5 lot x 100 = 250
    expect((float) $segar->pnl_amount)->toBe(250.0)
        ->and((float) $segar->risk_amount)->toBe(500.0)
        ->and($segar->isClosed())->toBeTrue()
        ->and($segar->rMultiple())->toBe(0.5);
});

it('menghitung ulang hasil ketika harga exit dikoreksi', function () {
    $alice = User::factory()->create();
    $this->actingAs($alice);
    $trade = tradeTerbuka($alice);

    $komponen = Volt::test('trades.edit', ['trade' => $trade])
        ->set('exitPrice', '2005')
        ->set('closedAt', now()->format('Y-m-d\TH:i'))
        ->call('save');

    $komponen->set('exitPrice', '1995')->call('save')->assertHasNoErrors();

    expect((float) $trade->fresh()->pnl_amount)->toBe(-250.0);
});

it('tidak mengubah skor kepatuhan meski trade diedit', function () {
    $alice = User::factory()->create();
    $this->actingAs($alice);
    $trade = tradeTerbuka($alice);

    Volt::test('trades.edit', ['trade' => $trade])
        ->set('exitPrice', '2100')
        ->set('closedAt', now()->format('Y-m-d\TH:i'))
        ->call('save');

    // Trade jadi sangat untung, tapi skor kepatuhannya tetap 50.
    expect($trade->fresh()->compliance_score)->toBe(50);
});

it('mengizinkan koreksi checklist dan menghitung ulang skornya', function () {
    $alice = User::factory()->create();
    $this->actingAs($alice);
    $trade = tradeTerbuka($alice);

    $rule = SetupRule::factory()->for($alice)->for($trade->setup, 'setup')->create(['weight' => 3]);
    $check = TradeRuleCheck::factory()->for($alice)->for($trade)->create([
        'setup_rule_id' => $rule->id,
        'is_met' => false,
        'rule_label' => 'Kriteria yang salah centang',
        'rule_weight' => 3,
    ]);

    Volt::test('trades.edit', ['trade' => $trade->fresh()])
        ->set('checks.'.$check->id, true)
        ->call('save')
        ->assertHasNoErrors();

    expect($check->fresh()->is_met)->toBeTrue()
        ->and($trade->fresh()->compliance_score)->toBe(100);
});

it('menyimpan skor pertama dan tidak pernah menimpanya', function () {
    $alice = User::factory()->create();
    $this->actingAs($alice);
    $trade = tradeTerbuka($alice);

    $rule = SetupRule::factory()->for($alice)->for($trade->setup, 'setup')->create(['weight' => 3]);
    $check = TradeRuleCheck::factory()->for($alice)->for($trade)->create([
        'setup_rule_id' => $rule->id,
        'is_met' => false,
        'rule_weight' => 3,
    ]);

    Volt::test('trades.edit', ['trade' => $trade->fresh()])
        ->set('checks.'.$check->id, true)
        ->call('save');

    $segar = $trade->fresh();

    // Skor berubah, tapi yang pertama tetap tersimpan. Revisi tidak bisa
    // menghapus jejak jawaban awal.
    expect($segar->compliance_score)->toBe(100)
        ->and($segar->original_compliance_score)->toBe(50);
});

it('mencatat waktu revisi checklist', function () {
    $alice = User::factory()->create();
    $this->actingAs($alice);
    $trade = tradeTerbuka($alice);

    $rule = SetupRule::factory()->for($alice)->for($trade->setup, 'setup')->create();
    $check = TradeRuleCheck::factory()->for($alice)->for($trade)->create([
        'setup_rule_id' => $rule->id,
        'is_met' => false,
    ]);

    expect($trade->checklistWasRevised())->toBeFalse();

    Volt::test('trades.edit', ['trade' => $trade->fresh()])
        ->set('checks.'.$check->id, true)
        ->call('save');

    expect($trade->fresh()->checklistWasRevised())->toBeTrue();
});

it('tidak menandai revisi ketika checklist tidak diubah', function () {
    $alice = User::factory()->create();
    $this->actingAs($alice);
    $trade = tradeTerbuka($alice);

    $rule = SetupRule::factory()->for($alice)->for($trade->setup, 'setup')->create();
    TradeRuleCheck::factory()->for($alice)->for($trade)->create([
        'setup_rule_id' => $rule->id,
        'is_met' => true,
    ]);

    // Hanya menutup posisi, checklist tidak disentuh.
    Volt::test('trades.edit', ['trade' => $trade->fresh()])
        ->set('exitPrice', '2005')
        ->set('closedAt', now()->format('Y-m-d\TH:i'))
        ->call('save');

    $segar = $trade->fresh();

    expect($segar->checklistWasRevised())->toBeFalse()
        ->and($segar->compliance_score)->toBe(50);
});

it('memberitahu pengguna bahwa revisi tercatat', function () {
    $alice = User::factory()->create();
    $this->actingAs($alice);
    $trade = tradeTerbuka($alice);

    $rule = SetupRule::factory()->for($alice)->for($trade->setup, 'setup')->create();
    TradeRuleCheck::factory()->for($alice)->for($trade)->create(['setup_rule_id' => $rule->id]);

    Volt::test('trades.edit', ['trade' => $trade->fresh()])
        ->assertSee('ditandai di laporan');
});

it('menolak harga exit tanpa waktu tutup', function () {
    $alice = User::factory()->create();
    $this->actingAs($alice);
    $trade = tradeTerbuka($alice);

    Volt::test('trades.edit', ['trade' => $trade])
        ->set('exitPrice', '2005')
        ->set('closedAt', '')
        ->call('save')
        ->assertHasErrors('closedAt');
});

it('menolak stop yang berimpit dengan entry', function () {
    $alice = User::factory()->create();
    $this->actingAs($alice);
    $trade = tradeTerbuka($alice);

    Volt::test('trades.edit', ['trade' => $trade])
        ->set('stopPrice', '2000')
        ->call('save')
        ->assertHasErrors('stopPrice');
});

it('tidak menemukan trade milik pengguna lain', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    $this->actingAs($alice);
    $tradeAlice = tradeTerbuka($alice);

    $this->actingAs($bob);

    // Route model binding terscope: 404, bukan 403, supaya keberadaan trade
    // milik orang lain pun tidak terbaca.
    $this->get(route('trades.edit', $tradeAlice))->assertNotFound();
});

it('menolak tamu membuka halaman ubah', function () {
    $alice = User::factory()->create();
    $this->actingAs($alice);
    $trade = tradeTerbuka($alice);

    auth()->logout();

    $this->get(route('trades.edit', $trade))->assertRedirect('/login');
});
