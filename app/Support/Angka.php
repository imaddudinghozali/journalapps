<?php

namespace App\Support;

/**
 * Pencetak angka untuk tampilan.
 *
 * Satu tempat, karena format angka paling gampang menyimpang diam-diam: satu
 * halaman mencetak 1,000.00, halaman lain 1000,00, dan tidak ada yang
 * menyadarinya sampai keduanya terlihat berdampingan.
 *
 * Aturan yang ditegakkan di sini, alasannya ada di catatan palet app.css:
 * setiap nilai yang diberi warna wajib mencetak tandanya juga. Palet
 * untung-rugi sekarang memakai pasangan emerald-merah yang sulit dipisahkan
 * sebagian pembaca buta warna, jadi warna tidak boleh jadi satu-satunya
 * pembawa arti.
 */
final class Angka
{
    /** Teks pengganti ketika nilainya memang belum ada. */
    public const BELUM = 'belum ada';

    public static function uang(?float $nilai, bool $tanda = false): string
    {
        if ($nilai === null) {
            return self::BELUM;
        }

        return self::awalan($nilai, $tanda).'$'.number_format(abs($nilai), 2);
    }

    public static function r(?float $nilai, bool $tanda = true): string
    {
        if ($nilai === null) {
            return self::BELUM;
        }

        return self::awalan($nilai, $tanda).number_format(abs($nilai), 2).'R';
    }

    public static function persen(?float $nilai, int $desimal = 0): string
    {
        return $nilai === null
            ? self::BELUM
            : number_format($nilai, $desimal).'%';
    }

    public static function kali(?float $nilai): string
    {
        return $nilai === null
            ? self::BELUM
            : number_format($nilai, 2);
    }

    /** Kelas warna berdasarkan arah nilainya. */
    public static function nada(?float $nilai): string
    {
        if ($nilai === null) {
            return 'text-ink-faint';
        }

        if ($nilai > 0) {
            return 'text-viz-positive';
        }

        return $nilai < 0 ? 'text-viz-negative' : 'text-ink';
    }

    private static function awalan(float $nilai, bool $tanda): string
    {
        if ($nilai < 0) {
            return '-';
        }

        return $tanda && $nilai > 0 ? '+' : '';
    }
}
