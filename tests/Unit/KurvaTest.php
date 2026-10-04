<?php

use App\Support\Kurva;

it('mengembalikan kosong untuk deret kosong', function () {
    expect(Kurva::titik([], 100, 50))->toBe([])
        ->and(Kurva::garis([]))->toBe('')
        ->and(Kurva::area([], 25))->toBe('')
        ->and(Kurva::jaring([], 50, 40))->toBe([]);
});

it('menyebar titik merata pada sumbu x', function () {
    $t = Kurva::titik([0.0, 1.0, 2.0], 100, 50, 10);

    expect($t[0][0])->toBe(10.0)
        ->and($t[1][0])->toBe(50.0)
        ->and($t[2][0])->toBe(90.0);
});

it('membalik sumbu y sehingga nilai tertinggi berada di atas', function () {
    $t = Kurva::titik([0.0, 10.0], 100, 50, 10);

    // y kecil berarti di atas layar.
    expect($t[1][1])->toBeLessThan($t[0][1]);
});

it('selalu memasukkan nol ke dalam rentang', function () {
    // Deret yang seluruhnya negatif tanpa nol di dalam rentangnya akan
    // tergambar seolah menurun dari puncak yang menguntungkan.
    $deret = [-1.0, -2.0, -3.0];
    $t = Kurva::titik($deret, 100, 50, 0);

    expect(Kurva::y($deret, 0.0, 50, 0))->toBe(0.0)
        ->and($t[0][1])->toBeGreaterThan(0.0);
});

it('menutup area ke garis dasar yang diberikan, bukan ke dasar kanvas', function () {
    $area = Kurva::area([[10.0, 20.0], [90.0, 5.0]], 40.0);

    expect($area)->toStartWith('M 10,40')
        ->and($area)->toEndWith('L 90,40 Z');
});

it('menempatkan titik jaring pertama tepat di atas pusat', function () {
    $j = Kurva::jaring([100.0, 100.0, 100.0, 100.0], 50, 40);

    expect($j[0][0])->toBe(50.0)
        ->and($j[0][1])->toBe(10.0)
        ->and($j)->toHaveCount(4);
});

it('memendekkan jari-jari sesuai nilai, dan membatasi di luar 0 sampai 100', function () {
    $j = Kurva::jaring([50.0, 0.0, 140.0, -20.0], 50, 40);

    expect($j[0][1])->toBe(30.0)
        ->and($j[1][0])->toBe(50.0)
        ->and($j[2][1])->toBe(90.0)
        ->and($j[3][0])->toBe(50.0);
});
