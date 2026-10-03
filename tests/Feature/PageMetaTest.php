<?php

use App\Models\User;

/*
|--------------------------------------------------------------------------
| Judul halaman, meta, dan jalan buntu
|--------------------------------------------------------------------------
|
| Temuan 9 dan 10 di diagnosis UI: tidak ada halaman 404 kustom, dan tidak
| ada meta description maupun og:image. Ditambah satu hal yang tidak masuk
| daftar tapi sama mendasarnya: setiap halaman memakai judul yang sama persis.
|
| Judul yang sama di semua halaman bukan soal estetika. Ia memecah riwayat
| peramban, bookmark, dan daftar tab menjadi tujuh baris "JournalApps" yang
| tidak bisa dibedakan, dan pembaca layar mengumumkan judul itu setiap kali
| halaman berpindah.
|
*/

function halamanAplikasi(): array
{
    return [
        '/dashboard' => 'Dashboard',
        '/trades' => 'Jurnal Trade',
        '/setups' => 'Setup & Rules',
        '/instruments' => 'Instrumen',
        '/reports' => 'Laporan',
        '/profile' => 'Profil',
    ];
}

it('memberi judul sendiri pada tiap halaman', function (string $jalur, string $judul) {
    $this->actingAs(User::factory()->create());

    $this->get($jalur)
        ->assertOk()
        ->assertSee('<title>'.e($judul).' — JournalApps</title>', escape: false);
})->with(
    collect(halamanAplikasi())->map(fn ($judul, $jalur) => [$jalur, $judul])->values()->all()
);

it('tidak memakai judul yang sama di dua halaman', function () {
    $this->actingAs(User::factory()->create());

    $judul = collect(array_keys(halamanAplikasi()))
        ->map(function (string $jalur) {
            preg_match('/<title>(.*?)<\/title>/s', $this->get($jalur)->getContent(), $cocok);

            return trim($cocok[1] ?? '');
        });

    expect($judul->unique())->toHaveCount($judul->count())
        ->and($judul->filter())->toHaveCount($judul->count());
});

it('menyertakan meta description di halaman aplikasi', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/dashboard')->assertSee('name="description"', escape: false);
});

it('menyediakan skip-link dan landmark utama di halaman aplikasi', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/dashboard')
        ->assertSee('href="#konten"', escape: false)
        ->assertSee('<main id="konten"', escape: false);
});

it('menampilkan halaman 404 sendiri, bukan bawaan Laravel', function () {
    $this->actingAs(User::factory()->create());

    $respons = $this->get('/jalur-yang-tidak-pernah-ada');

    $respons->assertNotFound()
        // Bawaan Laravel berbunyi "Not Found" dan tidak menawarkan jalan keluar.
        ->assertSee('Halaman ini tidak ada')
        ->assertSee(route('dashboard'), escape: false);
});

it('menampilkan halaman 404 yang sama untuk tamu, tanpa menawarkan tautan terlindungi', function () {
    $respons = $this->get('/jalur-yang-tidak-pernah-ada');

    $respons->assertNotFound()
        ->assertSee('Halaman ini tidak ada')
        ->assertDontSee(route('dashboard'), escape: false);
});
