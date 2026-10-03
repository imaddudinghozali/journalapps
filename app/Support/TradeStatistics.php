<?php

namespace App\Support;

/**
 * Statistik sekumpulan trade tertutup, diukur dalam R-multiple.
 *
 * Nominal sengaja tidak dipakai sebagai dasar perbandingan: hasil pada
 * instrumen dengan ukuran berbeda tidak sebanding kalau diukur dengan uang.
 *
 * Angka di sini dipakai pengguna untuk memutuskan setup mana yang dibuang,
 * jadi perlakukan perubahan rumusnya sebagai perubahan yang berdampak mahal.
 */
final class TradeStatistics
{
    /**
     * Jumlah trade minimum sebelum sebuah angka layak ditampilkan.
     *
     * Win rate 100% dari dua trade bukan sekadar tidak berguna — ia mendorong
     * keputusan strategi yang salah. Angka ini tebakan beralasan, bukan hasil
     * statistik; tinjau ulang setelah ada data nyata.
     */
    public const MIN_SAMPLE = 10;

    private function __construct(
        public readonly int $sampleSize,
        public readonly ?float $winRate,
        public readonly ?float $expectancy,
    ) {}

    /**
     * @param  array<int, float>  $rMultiples
     */
    public static function from(array $rMultiples): self
    {
        $n = count($rMultiples);

        if ($n === 0) {
            return new self(0, null, null);
        }

        // Break even bukan kemenangan. Menghitung R = 0 sebagai menang
        // membuat win rate terlihat lebih baik daripada kenyataannya.
        $menang = count(array_filter($rMultiples, fn (float $r) => $r > 0));

        return new self(
            $n,
            round(100 * $menang / $n, 2),
            round(array_sum($rMultiples) / $n, 2),
        );
    }

    public function hasEnoughSample(int $min = self::MIN_SAMPLE): bool
    {
        return $this->sampleSize >= $min;
    }
}
