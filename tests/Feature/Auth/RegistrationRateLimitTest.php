<?php

use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Volt\Volt;

/*
|--------------------------------------------------------------------------
| Pembatasan laju pendaftaran
|--------------------------------------------------------------------------
|
| Login sudah dibatasi lima percobaan; pendaftaran sama sekali tidak. Padahal
| pendaftaran justru yang MEMBUAT baris baru di database dan mengirim email
| verifikasi, jadi penyalahgunaannya lebih mahal daripada menebak sandi.
|
| Pembatasnya harus hidup di dalam komponen, bukan sebagai middleware
| throttle pada rutenya. Livewire mengirim form ke /livewire/update, bukan ke
| POST /register, jadi middleware di rute pendaftaran hanya membatasi
| pemuatan halamannya - bukan pengirimannya.
|
*/

function daftarkan(string $email): \Livewire\Features\SupportTesting\Testable
{
    return Volt::test('pages.auth.register')
        ->set('name', 'Trader Baru')
        ->set('email', $email)
        ->set('password', 'kata-sandi-yang-panjang')
        ->set('password_confirmation', 'kata-sandi-yang-panjang')
        ->call('register');
}

it('masih membolehkan pendaftaran yang wajar', function () {
    daftarkan('orang.pertama@contoh.test')->assertHasNoErrors();

    expect(User::where('email', 'orang.pertama@contoh.test')->exists())->toBeTrue();
});

it('menghentikan pendaftaran beruntun dari satu alamat IP', function () {
    // Lima pendaftaran pertama lolos; yang keenam ditolak.
    foreach (range(1, 5) as $i) {
        daftarkan("orang{$i}@contoh.test")->assertHasNoErrors();
    }

    daftarkan('orang6@contoh.test')->assertHasErrors('email');

    expect(User::count())->toBe(5);
});

it('menyebut berapa lama harus menunggu', function () {
    foreach (range(1, 5) as $i) {
        daftarkan("orang{$i}@contoh.test");
    }

    $galat = daftarkan('orang6@contoh.test')->errors()->get('email');

    expect($galat[0] ?? '')->toContain('detik');
});

it('tidak menghitung percobaan yang gagal validasi sebagai pendaftaran', function () {
    // Email tidak valid: tidak ada akun yang dibuat, jadi tidak adil kalau
    // ikut memakan jatah. Salah ketik enam kali tidak boleh mengunci orang.
    foreach (range(1, 6) as $i) {
        Volt::test('pages.auth.register')
            ->set('name', 'Trader Baru')
            ->set('email', 'bukan-email')
            ->set('password', 'kata-sandi-yang-panjang')
            ->set('password_confirmation', 'kata-sandi-yang-panjang')
            ->call('register');
    }

    daftarkan('orang.sah@contoh.test')->assertHasNoErrors();
});

afterEach(function () {
    RateLimiter::clear('daftar|127.0.0.1');
});
