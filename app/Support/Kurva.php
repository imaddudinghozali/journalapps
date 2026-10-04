<?php

namespace App\Support;

/**
 * Pemetaan deret angka ke koordinat SVG.
 *
 * Ada supaya Blade tidak perlu berhitung. Geometri grafik memang urusan
 * tampilan, tapi ia tetap perhitungan - dan perhitungan di dalam template
 * tidak bisa diuji, sehingga grafik yang salah skala baru ketahuan lewat mata.
 *
 * Tanpa pustaka chart. Empat bentuk yang dibutuhkan dashboard ini - garis,
 * area, batang, dan jaring - semuanya SVG sederhana. Menambah 60 sampai 200 kB
 * dependensi untuk itu, lalu memindahkan datanya ke JavaScript, justru
 * melanggar batasan bahwa perhitungan harus hidup di service yang bisa dites.
 */
final class Kurva
{
    /**
     * Koordinat tiap titik, disebar merata pada sumbu x.
     *
     * @param  array<int, float>  $nilai
     * @return array<int, array{0:float, 1:float}>
     */
    public static function titik(array $nilai, float $lebar, float $tinggi, float $pad = 8.0): array
    {
        if ($nilai === []) {
            return [];
        }

        [$min, $rentang] = self::skala($nilai);

        $jarak = max(count($nilai) - 1, 1);
        $koordinat = [];

        foreach (array_values($nilai) as $i => $v) {
            $x = $pad + ($i / $jarak) * ($lebar - 2 * $pad);
            $y = $pad + (1 - (($v - $min) / $rentang)) * ($tinggi - 2 * $pad);

            $koordinat[] = [round($x, 2), round($y, 2)];
        }

        return $koordinat;
    }

    /**
     * Posisi y untuk sebuah nilai pada skala deret yang sama.
     *
     * @param  array<int, float>  $nilai
     */
    public static function y(array $nilai, float $pada, float $tinggi, float $pad = 8.0): float
    {
        if ($nilai === []) {
            return round($tinggi / 2, 2);
        }

        [$min, $rentang] = self::skala($nilai);

        return round($pad + (1 - (($pada - $min) / $rentang)) * ($tinggi - 2 * $pad), 2);
    }

    /** @param  array<int, array{0:float, 1:float}>  $koordinat */
    public static function garis(array $koordinat): string
    {
        return implode(' ', array_map(fn (array $p) => $p[0].','.$p[1], $koordinat));
    }

    /**
     * Area di bawah garis, ditutup ke sebuah garis dasar.
     *
     * Ditutup ke garis impas, bukan ke dasar kanvas: kalau ditutup ke dasar,
     * bagian yang merugi ikut terisi dan terbaca seolah tetap ada hasilnya.
     *
     * @param  array<int, array{0:float, 1:float}>  $koordinat
     */
    public static function area(array $koordinat, float $dasar): string
    {
        if ($koordinat === []) {
            return '';
        }

        $awal = $koordinat[0];
        $akhir = $koordinat[count($koordinat) - 1];

        return 'M '.$awal[0].','.$dasar
            .' L '.implode(' L ', array_map(fn (array $p) => $p[0].','.$p[1], $koordinat))
            .' L '.$akhir[0].','.$dasar.' Z';
    }

    /**
     * Titik-titik poligon untuk grafik jaring (radar).
     *
     * @param  array<int, float>  $nilai  masing-masing 0..100
     * @return array<int, array{0:float, 1:float}>
     */
    public static function jaring(array $nilai, float $pusat, float $jari): array
    {
        $jumlah = count($nilai);

        if ($jumlah === 0) {
            return [];
        }

        $titik = [];

        foreach (array_values($nilai) as $i => $v) {
            // Dimulai dari atas, searah jarum jam.
            $sudut = -M_PI / 2 + ($i / $jumlah) * 2 * M_PI;
            $panjang = $jari * max(0.0, min(100.0, $v)) / 100;

            $titik[] = [
                round($pusat + cos($sudut) * $panjang, 2),
                round($pusat + sin($sudut) * $panjang, 2),
            ];
        }

        return $titik;
    }

    /**
     * @param  array<int, float>  $nilai
     * @return array{0:float, 1:float} minimum dan rentang
     */
    private static function skala(array $nilai): array
    {
        // Nol selalu masuk rentang. Grafik hasil yang tidak memuat garis impas
        // membuat rentetan rugi terlihat seperti rentetan untung yang menurun.
        $min = min(min($nilai), 0.0);
        $max = max(max($nilai), 0.0);

        return [$min, ($max - $min) ?: 1.0];
    }
}
