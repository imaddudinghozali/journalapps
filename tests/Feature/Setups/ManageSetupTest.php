<?php

use App\Models\Scopes\OwnedByUserScope;
use App\Models\SetupRule;
use App\Models\TradingSetup;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Volt\Volt;

function penggunaTerverifikasi(): User
{
    return User::factory()->create();
}

it('menampilkan halaman katalog setup bagi pengguna terverifikasi', function () {
    $this->actingAs(penggunaTerverifikasi());

    $this->get('/setups')
        ->assertOk()
        ->assertSeeVolt('setups.manage');
});

it('menolak tamu membuka katalog setup', function () {
    $this->get('/setups')->assertRedirect('/login');
});

it('menolak pengguna belum terverifikasi membuka katalog setup', function () {
    $this->actingAs(User::factory()->unverified()->create());

    $this->get('/setups')->assertRedirect(route('verification.notice'));
});

it('membuat setup baru', function () {
    $alice = penggunaTerverifikasi();
    $this->actingAs($alice);

    Volt::test('setups.manage')
        ->set('name', 'Break of Structure')
        ->set('description', 'Entry setelah struktur patah')
        ->call('createSetup')
        ->assertHasNoErrors();

    $setup = TradingSetup::first();

    expect($setup->name)->toBe('Break of Structure')
        ->and($setup->user_id)->toBe($alice->id);
});

it('menolak nama setup kosong', function () {
    $this->actingAs(penggunaTerverifikasi());

    Volt::test('setups.manage')
        ->set('name', '')
        ->call('createSetup')
        ->assertHasErrors('name');
});

it('menolak nama setup yang sudah dipakai pengguna yang sama', function () {
    $alice = penggunaTerverifikasi();
    TradingSetup::factory()->for($alice)->create(['name' => 'Order Block']);

    $this->actingAs($alice);

    Volt::test('setups.manage')
        ->set('name', 'Order Block')
        ->call('createSetup')
        ->assertHasErrors('name');
});

it('mengizinkan nama setup yang sama milik pengguna berbeda', function () {
    $alice = penggunaTerverifikasi();
    $bob = penggunaTerverifikasi();

    TradingSetup::factory()->for($alice)->create(['name' => 'Order Block']);

    $this->actingAs($bob);

    // Nama Alice tidak boleh bocor sebagai "sudah dipakai" ke Bob.
    Volt::test('setups.manage')
        ->set('name', 'Order Block')
        ->call('createSetup')
        ->assertHasNoErrors();
});

it('menambah rule ke setup terpilih', function () {
    $alice = penggunaTerverifikasi();
    $setup = TradingSetup::factory()->for($alice)->create();

    $this->actingAs($alice);

    Volt::test('setups.manage')
        ->call('selectSetup', $setup->id)
        ->set('ruleLabel', 'Ada BOS di timeframe H4')
        ->set('ruleWeight', 4)
        ->set('ruleRequired', true)
        ->call('addRule')
        ->assertHasNoErrors();

    $rule = SetupRule::first();

    expect($rule->label)->toBe('Ada BOS di timeframe H4')
        ->and($rule->weight)->toBe(4)
        ->and($rule->is_required)->toBeTrue()
        ->and($rule->user_id)->toBe($alice->id)
        ->and($rule->trading_setup_id)->toBe($setup->id);
});

it('menolak rule tanpa label', function () {
    $alice = penggunaTerverifikasi();
    $setup = TradingSetup::factory()->for($alice)->create();

    $this->actingAs($alice);

    Volt::test('setups.manage')
        ->call('selectSetup', $setup->id)
        ->set('ruleLabel', '')
        ->call('addRule')
        ->assertHasErrors('ruleLabel');
});

it('menolak bobot di luar skala yang diizinkan', function () {
    $alice = penggunaTerverifikasi();
    $setup = TradingSetup::factory()->for($alice)->create();

    $this->actingAs($alice);

    Volt::test('setups.manage')
        ->call('selectSetup', $setup->id)
        ->set('ruleLabel', 'Bobot ngawur')
        ->set('ruleWeight', SetupRule::MAX_WEIGHT + 1)
        ->call('addRule')
        ->assertHasErrors('ruleWeight');
});

it('menolak rule melebihi batas maksimum per setup', function () {
    $alice = penggunaTerverifikasi();
    $setup = TradingSetup::factory()->for($alice)->create();

    SetupRule::factory()
        ->count(TradingSetup::MAX_ACTIVE_RULES)
        ->for($alice)
        ->for($setup, 'setup')
        ->create();

    $this->actingAs($alice);

    Volt::test('setups.manage')
        ->call('selectSetup', $setup->id)
        ->set('ruleLabel', 'Rule kelebihan')
        ->call('addRule')
        ->assertHasErrors('ruleLabel');

    expect(SetupRule::count())->toBe(TradingSetup::MAX_ACTIVE_RULES);
});

it('tidak menghitung rule yang diarsipkan terhadap batas maksimum', function () {
    $alice = penggunaTerverifikasi();
    $setup = TradingSetup::factory()->for($alice)->create();

    SetupRule::factory()
        ->count(TradingSetup::MAX_ACTIVE_RULES)
        ->for($alice)
        ->for($setup, 'setup')
        ->archived()
        ->create();

    $this->actingAs($alice);

    Volt::test('setups.manage')
        ->call('selectSetup', $setup->id)
        ->set('ruleLabel', 'Rule baru setelah arsip')
        ->call('addRule')
        ->assertHasNoErrors();
});

it('mengarsipkan dan memulihkan setup', function () {
    $alice = penggunaTerverifikasi();
    $setup = TradingSetup::factory()->for($alice)->create();

    $this->actingAs($alice);

    $komponen = Volt::test('setups.manage')->call('archiveSetup', $setup->id);
    expect($setup->fresh()->isArchived())->toBeTrue();

    $komponen->call('restoreSetup', $setup->id);
    expect($setup->fresh()->isArchived())->toBeFalse();
});

it('mengarsipkan rule tanpa menghapusnya', function () {
    $alice = penggunaTerverifikasi();
    $setup = TradingSetup::factory()->for($alice)->create();
    $rule = SetupRule::factory()->for($alice)->for($setup, 'setup')->create();

    $this->actingAs($alice);

    Volt::test('setups.manage')
        ->call('selectSetup', $setup->id)
        ->call('archiveRule', $rule->id);

    expect($rule->fresh())->not->toBeNull()
        ->and($rule->fresh()->isArchived())->toBeTrue();
});

it('tidak memilih setup milik pengguna lain', function () {
    $alice = penggunaTerverifikasi();
    $bob = penggunaTerverifikasi();

    $setupAlice = TradingSetup::factory()->for($alice)->create();

    $this->actingAs($bob);

    Volt::test('setups.manage')
        ->call('selectSetup', $setupAlice->id)
        ->assertSet('selectedSetupId', null);
});

it('tidak membuat rule ketika selectedSetupId dipaksakan ke setup pengguna lain', function () {
    $alice = penggunaTerverifikasi();
    $bob = penggunaTerverifikasi();

    $setupAlice = TradingSetup::factory()->for($alice)->create();

    $this->actingAs($bob);

    // Properti publik bisa di-set langsung oleh klien, menembus selectSetup().
    Volt::test('setups.manage')
        ->set('selectedSetupId', $setupAlice->id)
        ->set('ruleLabel', 'Disusupkan lewat state')
        ->call('addRule');

    expect(SetupRule::withoutGlobalScope(OwnedByUserScope::class)->count())->toBe(0);
});

it('tidak memilih setup yang sudah diarsipkan', function () {
    $alice = penggunaTerverifikasi();
    $setup = TradingSetup::factory()->for($alice)->archived()->create();

    $this->actingAs($alice);

    Volt::test('setups.manage')
        ->call('selectSetup', $setup->id)
        ->assertSet('selectedSetupId', null);
});

it('tidak menambah rule ke setup yang sudah diarsipkan', function () {
    $alice = penggunaTerverifikasi();
    $setup = TradingSetup::factory()->for($alice)->archived()->create();

    $this->actingAs($alice);

    Volt::test('setups.manage')
        ->set('selectedSetupId', $setup->id)
        ->set('ruleLabel', 'Rule ke setup arsip')
        ->call('addRule');

    expect(SetupRule::count())->toBe(0);
});

it('mengosongkan pilihan ketika setup yang sedang terpilih diarsipkan', function () {
    $alice = penggunaTerverifikasi();
    $setup = TradingSetup::factory()->for($alice)->create();

    $this->actingAs($alice);

    Volt::test('setups.manage')
        ->call('selectSetup', $setup->id)
        ->assertSet('selectedSetupId', $setup->id)
        ->call('archiveSetup', $setup->id)
        ->assertSet('selectedSetupId', null);
});

it('menolak bobot di bawah skala yang diizinkan', function () {
    $alice = penggunaTerverifikasi();
    $setup = TradingSetup::factory()->for($alice)->create();

    $this->actingAs($alice);

    Volt::test('setups.manage')
        ->call('selectSetup', $setup->id)
        ->set('ruleLabel', 'Bobot terlalu kecil')
        ->set('ruleWeight', SetupRule::MIN_WEIGHT - 1)
        ->call('addRule')
        ->assertHasErrors('ruleWeight');
});

it('menolak nama setup melebihi batas panjang', function () {
    $this->actingAs(penggunaTerverifikasi());

    Volt::test('setups.manage')
        ->set('name', str_repeat('a', 81))
        ->call('createSetup')
        ->assertHasErrors('name');
});

it('menolak label rule melebihi batas panjang', function () {
    $alice = penggunaTerverifikasi();
    $setup = TradingSetup::factory()->for($alice)->create();

    $this->actingAs($alice);

    Volt::test('setups.manage')
        ->call('selectSetup', $setup->id)
        ->set('ruleLabel', str_repeat('a', 121))
        ->call('addRule')
        ->assertHasErrors('ruleLabel');
});

it('menganggap nama dengan spasi di ujung sebagai duplikat', function () {
    $alice = penggunaTerverifikasi();
    TradingSetup::factory()->for($alice)->create(['name' => 'Order Block']);

    $this->actingAs($alice);

    // Livewire tidak melewati middleware TrimStrings.
    Volt::test('setups.manage')
        ->set('name', '  Order Block  ')
        ->call('createSetup')
        ->assertHasErrors('name');
});

it('memberi posisi menaik pada rule baru meski ada yang diarsipkan', function () {
    $alice = penggunaTerverifikasi();
    $setup = TradingSetup::factory()->for($alice)->create();

    $this->actingAs($alice);

    $komponen = Volt::test('setups.manage')->call('selectSetup', $setup->id);

    $komponen->set('ruleLabel', 'Pertama')->call('addRule');
    $pertama = SetupRule::where('label', 'Pertama')->first();

    $komponen->call('archiveRule', $pertama->id);
    $komponen->set('ruleLabel', 'Kedua')->call('addRule');

    $kedua = SetupRule::where('label', 'Kedua')->first();

    // count() akan menghasilkan posisi kembar di sini; max+1 tidak.
    expect($kedua->position)->toBeGreaterThan($pertama->position);
});

it('menolak mengarsipkan rule milik pengguna lain', function () {
    $alice = penggunaTerverifikasi();
    $bob = penggunaTerverifikasi();

    $setup = TradingSetup::factory()->for($alice)->create();
    $rule = SetupRule::factory()->for($alice)->for($setup, 'setup')->create();

    $this->actingAs($bob);

    expect(fn () => Volt::test('setups.manage')->call('archiveRule', $rule->id))
        ->toThrow(ModelNotFoundException::class);

    expect($rule->fresh()->isArchived())->toBeFalse();
});

it('menolak memulihkan setup milik pengguna lain', function () {
    $alice = penggunaTerverifikasi();
    $bob = penggunaTerverifikasi();

    $setupAlice = TradingSetup::factory()->for($alice)->archived()->create();

    $this->actingAs($bob);

    expect(fn () => Volt::test('setups.manage')->call('restoreSetup', $setupAlice->id))
        ->toThrow(ModelNotFoundException::class);

    expect($setupAlice->fresh()->isArchived())->toBeTrue();
});

it('menolak mengarsipkan setup milik pengguna lain', function () {
    $alice = penggunaTerverifikasi();
    $bob = penggunaTerverifikasi();

    $setupAlice = TradingSetup::factory()->for($alice)->create();

    $this->actingAs($bob);

    // findOrFail terscope: setup Alice tidak terlihat oleh Bob sama sekali,
    // sehingga aksi gagal sebelum sempat menyentuh data. Pada request HTTP ini
    // muncul sebagai 404; di dalam komponen, exception-nya naik apa adanya.
    expect(fn () => Volt::test('setups.manage')->call('archiveSetup', $setupAlice->id))
        ->toThrow(ModelNotFoundException::class);

    expect($setupAlice->fresh()->isArchived())->toBeFalse();
});
