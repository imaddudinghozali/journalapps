<?php

use App\Models\User;
use Livewire\Volt\Volt;

it('menampilkan form ambang pada halaman profil', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/profile')
        ->assertOk()
        ->assertSeeVolt('profile.update-compliance-threshold-form');
});

it('mengubah ambang peringatan milik pengguna', function () {
    $alice = User::factory()->create(['compliance_threshold' => 80]);
    $this->actingAs($alice);

    Volt::test('profile.update-compliance-threshold-form')
        ->set('complianceThreshold', 60)
        ->call('updateComplianceThreshold')
        ->assertHasNoErrors();

    expect($alice->fresh()->compliance_threshold)->toBe(60);
});

it('menolak ambang di luar rentang 0 sampai 100', function () {
    $alice = User::factory()->create();
    $this->actingAs($alice);

    Volt::test('profile.update-compliance-threshold-form')
        ->set('complianceThreshold', 101)
        ->call('updateComplianceThreshold')
        ->assertHasErrors('complianceThreshold');

    expect($alice->fresh()->compliance_threshold)->toBe(80);
});

// Perilaku ambang terhadap peringatan diuji di TradeHardeningTest, yang
// benar-benar me-render komponen alih-alih menegaskan nilai factory.
