<?php

use App\Support\Angka;

it('mencetak uang dengan simbol dan dua desimal', function () {
    expect(Angka::uang(1000.0))->toBe('$1,000.00')
        ->and(Angka::uang(-130.5))->toBe('-$130.50')
        ->and(Angka::uang(null))->toBe(Angka::BELUM);
});

it('mencetak tanda plus hanya ketika diminta', function () {
    expect(Angka::uang(250.0))->toBe('$250.00')
        ->and(Angka::uang(250.0, tanda: true))->toBe('+$250.00')
        // Nol tidak pernah dapat tanda: ia bukan untung dan bukan rugi.
        ->and(Angka::uang(0.0, tanda: true))->toBe('$0.00');
});

it('mencetak R dengan tanda secara bawaan', function () {
    expect(Angka::r(1.85))->toBe('+1.85R')
        ->and(Angka::r(-1.0))->toBe('-1.00R')
        ->and(Angka::r(null))->toBe(Angka::BELUM);
});

it('memberi kelas warna sesuai arah nilainya', function () {
    expect(Angka::nada(1.0))->toBe('text-viz-positive')
        ->and(Angka::nada(-1.0))->toBe('text-viz-negative')
        ->and(Angka::nada(0.0))->toBe('text-ink')
        ->and(Angka::nada(null))->toBe('text-ink-faint');
});

it('mencetak persen dan kelipatan', function () {
    expect(Angka::persen(58.333))->toBe('58%')
        ->and(Angka::persen(58.333, 1))->toBe('58.3%')
        ->and(Angka::kali(1.5))->toBe('1.50')
        ->and(Angka::kali(null))->toBe(Angka::BELUM);
});
