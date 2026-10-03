<?php

use App\Models\SetupRule;
use App\Models\Trade;
use App\Models\TradeRuleCheck;
use App\Models\TradingSetup;
use App\Models\User;
use App\Support\ComplianceScore;
use Livewire\Volt\Volt;

function setupDenganRules(User $user, array $bobot = [1, 3], bool $adaWajib = false): TradingSetup
{
    $setup = TradingSetup::factory()->for($user)->create();

    foreach ($bobot as $i => $w) {
        SetupRule::factory()->for($user)->for($setup, 'setup')->create([
            'label' => 'Kriteria '.($i + 1),
            'weight' => $w,
            'is_required' => $adaWajib && $i === 0,
            'position' => $i,
        ]);
    }

    return $setup;
}

it('menampilkan halaman jurnal bagi pengguna terverifikasi', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/trades')
        ->assertOk()
        ->assertSeeVolt('trades.record')
        ->assertSeeVolt('trades.index');
});

it('menolak tamu membuka jurnal', function () {
    $this->get('/trades')->assertRedirect('/login');
});

it('menolak pengguna belum terverifikasi membuka jurnal', function () {
    $this->actingAs(User::factory()->unverified()->create());

    $this->get('/trades')->assertRedirect(route('verification.notice'));
});

it('mencatat trade beserta jawaban checklist', function () {
    $alice = User::factory()->create();
    $this->actingAs($alice);
    $setup = setupDenganRules($alice, [1, 3]);
    [$r1, $r2] = $setup->rules()->orderBy('position')->get()->all();

    Volt::test('trades.record')
        ->set('tradingSetupId', $setup->id)
        ->set('checks', [$r1->id => true, $r2->id => false])
        ->set('symbol', 'xauusd')
        ->set('direction', Trade::DIRECTION_LONG)
        ->set('riskAmount', '50')
        ->set('pnlAmount', '125.5')
        ->set('openedAt', now()->subHour()->format('Y-m-d\TH:i'))
        ->set('closedAt', now()->format('Y-m-d\TH:i'))
        ->call('save')
        ->assertHasNoErrors();

    $trade = Trade::first();

    expect($trade->symbol)->toBe('XAUUSD')
        ->and($trade->user_id)->toBe($alice->id)
        ->and($trade->trading_setup_id)->toBe($setup->id)
        ->and((float) $trade->pnl_amount)->toBe(125.5)
        ->and($trade->ruleChecks()->count())->toBe(2);
});

it('menghitung skor kepatuhan berbobot saat menyimpan', function () {
    $alice = User::factory()->create();
    $this->actingAs($alice);
    $setup = setupDenganRules($alice, [1, 3]);
    [$r1, $r2] = $setup->rules()->orderBy('position')->get()->all();

    Volt::test('trades.record')
        ->set('tradingSetupId', $setup->id)
        ->set('checks', [$r1->id => true, $r2->id => false])
        ->set('symbol', 'XAUUSD')
        ->set('riskAmount', '50')
        ->set('openedAt', now()->format('Y-m-d\TH:i'))
        ->call('save');

    // Bobot 1 dari total 4 = 25%.
    expect(Trade::first()->compliance_score)->toBe(25);
});

it('menyimpan skor yang selalu sama dengan hasil hitung ulang dari checklist', function () {
    $alice = User::factory()->create();
    $this->actingAs($alice);
    $setup = setupDenganRules($alice, [2, 3, 5]);
    $rules = $setup->rules()->orderBy('position')->get();

    Volt::test('trades.record')
        ->set('tradingSetupId', $setup->id)
        ->set('checks', [
            $rules[0]->id => true,
            $rules[1]->id => false,
            $rules[2]->id => true,
        ])
        ->set('symbol', 'EURUSD')
        ->set('riskAmount', '20')
        ->set('openedAt', now()->format('Y-m-d\TH:i'))
        ->call('save');

    $trade = Trade::with('ruleChecks')->first();

    $hitungUlang = ComplianceScore::from(
        $trade->ruleChecks->map(fn (TradeRuleCheck $c) => $c->toScoreInput())->all()
    );

    expect($trade->compliance_score)->toBe($hitungUlang->value);
});

it('menyimpan trade tanpa skor ketika setup belum punya rule', function () {
    $alice = User::factory()->create();
    $this->actingAs($alice);
    $setup = TradingSetup::factory()->for($alice)->create();

    Volt::test('trades.record')
        ->set('tradingSetupId', $setup->id)
        ->set('symbol', 'BTCUSD')
        ->set('riskAmount', '100')
        ->set('openedAt', now()->format('Y-m-d\TH:i'))
        ->call('save')
        ->assertHasNoErrors();

    // null, bukan 0 maupun 100: tidak ada yang bisa dinilai.
    expect(Trade::first()->compliance_score)->toBeNull();
});

it('tetap menyimpan trade yang kepatuhannya rendah', function () {
    $alice = User::factory()->create(['compliance_threshold' => 80]);
    $this->actingAs($alice);
    $setup = setupDenganRules($alice, [1, 3]);
    $rules = $setup->rules()->orderBy('position')->get();

    // Semua rule dilanggar. Justru trade seperti ini yang paling perlu
    // tercatat — memblokirnya akan menghapus data yang mau dipelajari.
    Volt::test('trades.record')
        ->set('tradingSetupId', $setup->id)
        ->set('checks', [$rules[0]->id => false, $rules[1]->id => false])
        ->set('symbol', 'XAUUSD')
        ->set('riskAmount', '50')
        ->set('openedAt', now()->format('Y-m-d\TH:i'))
        ->call('save')
        ->assertHasNoErrors();

    expect(Trade::count())->toBe(1)
        ->and(Trade::first()->compliance_score)->toBe(0);
});

it('tetap menyimpan trade yang melanggar rule wajib', function () {
    $alice = User::factory()->create();
    $this->actingAs($alice);
    $setup = setupDenganRules($alice, [1, 9], adaWajib: true);
    $rules = $setup->rules()->orderBy('position')->get();

    Volt::test('trades.record')
        ->set('tradingSetupId', $setup->id)
        ->set('checks', [$rules[0]->id => false, $rules[1]->id => true])
        ->set('symbol', 'XAUUSD')
        ->set('riskAmount', '50')
        ->set('openedAt', now()->format('Y-m-d\TH:i'))
        ->call('save')
        ->assertHasNoErrors();

    expect(Trade::count())->toBe(1);
});

it('menolak menyimpan tanpa memilih setup', function () {
    $this->actingAs(User::factory()->create());

    Volt::test('trades.record')
        ->set('symbol', 'XAUUSD')
        ->set('riskAmount', '50')
        ->set('openedAt', now()->format('Y-m-d\TH:i'))
        ->call('save')
        ->assertHasErrors('tradingSetupId');
});

it('menolak risiko nol atau negatif', function () {
    $alice = User::factory()->create();
    $this->actingAs($alice);
    $setup = setupDenganRules($alice);

    Volt::test('trades.record')
        ->set('tradingSetupId', $setup->id)
        ->set('symbol', 'XAUUSD')
        ->set('riskAmount', '0')
        ->set('openedAt', now()->format('Y-m-d\TH:i'))
        ->call('save')
        ->assertHasErrors('riskAmount');
});

it('menolak waktu tutup yang mendahului waktu buka', function () {
    $alice = User::factory()->create();
    $this->actingAs($alice);
    $setup = setupDenganRules($alice);

    Volt::test('trades.record')
        ->set('tradingSetupId', $setup->id)
        ->set('symbol', 'XAUUSD')
        ->set('riskAmount', '50')
        ->set('openedAt', now()->format('Y-m-d\TH:i'))
        ->set('closedAt', now()->subDay()->format('Y-m-d\TH:i'))
        ->call('save')
        ->assertHasErrors('closedAt');
});

it('tidak mencatat trade pada setup milik pengguna lain', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    $setupAlice = setupDenganRules($alice);

    $this->actingAs($bob);

    Volt::test('trades.record')
        ->set('tradingSetupId', $setupAlice->id)
        ->set('symbol', 'XAUUSD')
        ->set('riskAmount', '50')
        ->set('openedAt', now()->format('Y-m-d\TH:i'))
        ->call('save')
        ->assertHasErrors('tradingSetupId');

    expect(Trade::withoutGlobalScope(App\Models\Scopes\OwnedByUserScope::class)->count())->toBe(0);
});

it('mengosongkan checklist ketika setup diganti', function () {
    $alice = User::factory()->create();
    $this->actingAs($alice);
    $setupA = setupDenganRules($alice, [1]);
    $setupB = setupDenganRules($alice, [2]);

    Volt::test('trades.record')
        ->set('tradingSetupId', $setupA->id)
        ->set('checks', [$setupA->rules()->first()->id => true])
        ->set('tradingSetupId', $setupB->id)
        ->assertSet('checks', []);
});

it('menampilkan trade pada daftar setelah dicatat', function () {
    $alice = User::factory()->create();
    $this->actingAs($alice);
    $setup = setupDenganRules($alice);

    Trade::factory()->for($alice)->for($setup, 'setup')->create(['symbol' => 'GBPJPY']);

    Volt::test('trades.index')->assertSee('GBPJPY');
});

it('tidak menampilkan trade pengguna lain pada daftar', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    $setupAlice = setupDenganRules($alice);
    Trade::factory()->for($alice)->for($setupAlice, 'setup')->create(['symbol' => 'RAHASIA']);

    $this->actingAs($bob);

    Volt::test('trades.index')->assertDontSee('RAHASIA');
});
