<?php

use App\Models\User;

/*
|--------------------------------------------------------------------------
| Halaman kebijakan privasi
|--------------------------------------------------------------------------
|
| Diperlukan begitu aplikasi ini menyimpan data orang lain. Yang diuji di sini
| bukan isinya - itu urusan manusia membacanya - tapi tiga hal yang mudah
| rusak diam-diam: halamannya terbuka untuk tamu, tautannya ada di titik orang
| menyerahkan datanya, dan rutenya tidak hilang karena refactor.
|
*/

it('bisa dibuka tanpa masuk akun', function () {
    $this->get('/privasi')
        ->assertOk()
        ->assertSee('Kebijakan privasi');
});

it('tetap bisa dibuka setelah masuk', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('privasi'))->assertOk();
});

it('ditautkan dari halaman depan', function () {
    $this->get('/')->assertSee(route('privasi'), escape: false);
});

it('ditautkan dari halaman pendaftaran', function () {
    $this->get('/register')
        ->assertOk()
        ->assertSee(route('privasi'), escape: false);
});

it('menyebut cara menghapus data', function () {
    $this->get('/privasi')->assertSee('hapus akun');
});
