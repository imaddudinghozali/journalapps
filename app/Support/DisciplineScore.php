<?php

namespace App\Support;

/**
 * Skor Disiplin 0-100.
 *
 * Bobotnya disetujui pemilik produk: kepatuhan 40, profit factor 25,
 * konsistensi 20, win rate 15.
 *
 * Alasan bobotnya, karena angka seperti ini akan dipercaya begitu saja kalau
 * tidak dijelaskan:
 *
 *  - Kepatuhan 40. Satu-satunya sumbu yang sepenuhnya dikendalikan pengguna.
 *    Tiga sumbu lain adalah hasil, dan hasil ikut ditentukan pasar.
 *  - Profit factor 25. Ukuran hasil yang paling banyak memuat informasi: ia
 *    menimbang besaran menang dan kalah sekaligus, bukan cuma jumlahnya.
 *  - Konsistensi 20. Disiplin itu keterulangan. Rata-rata 80 yang stabil lebih
 *    disiplin daripada rata-rata 80 yang berayun antara 30 dan 100.
 *  - Win rate 15, paling kecil, karena paling mudah dipermainkan: potong cepat
 *    yang menang dan win rate naik sementara expectancy justru turun.
 *
 * KEBERATAN YANG TETAP BERLAKU, dicatat supaya tidak hilang: skor ini
 * mencampur apa yang pengguna LAKUKAN dengan apa yang TERJADI. Memisahkan
 * keduanya justru tesis produk ini - angka tunggal 73 tidak bisa menjawab
 * "strateginya atau saya?". Karena itu radarnya wajib menampilkan keempat
 * sumbunya, dan angka tunggalnya tidak pernah berdiri sendiri.
 *
 * Tidak ada perayaan atas skor tinggi, tidak ada lencana, tidak ada warna
 * hadiah. Skor ini sebagian diisi sendiri oleh pengguna.
 */
final class DisciplineScore
{
    public const BOBOT = [
        'kepatuhan' => 40,
        'profitFactor' => 25,
        'konsistensi' => 20,
        'winRate' => 15,
    ];

    public const LABEL = [
        'kepatuhan' => 'Kepatuhan',
        'profitFactor' => 'Profit factor',
        'konsistensi' => 'Konsistensi',
        'winRate' => 'Win rate',
    ];

    /** Simpangan baku kepatuhan di atas ini dianggap tidak konsisten sama sekali. */
    private const SD_TERBURUK = 50.0;

    /** Profit factor di atas ini tidak menambah nilai sumbu lagi. */
    private const PF_TERBAIK = 2.5;

    /** Win rate di atas ini tidak menambah nilai sumbu lagi. */
    private const WR_TERBAIK = 70.0;

    /** @param array<string, float> $axes masing-masing 0..100 */
    private function __construct(
        public readonly ?int $value,
        public readonly array $axes,
        public readonly int $sampleSize,
    ) {}

    /**
     * @param  array<int, int>  $complianceScores  skor trade tertutup yang dinilai
     */
    public static function from(array $complianceScores, ?float $profitFactor, ?float $winRate): self
    {
        $sumbu = [
            'kepatuhan' => self::rata($complianceScores),
            'profitFactor' => self::dariProfitFactor($profitFactor),
            'konsistensi' => self::dariSebaran($complianceScores),
            'winRate' => self::dariWinRate($winRate),
        ];

        $jumlah = count($complianceScores);

        // Di bawah ambang sampel angkanya ditahan - tapi sumbunya tetap
        // dikembalikan supaya radar bisa digambar pucat sebagai pratinjau.
        $nilai = $jumlah >= TradeStatistics::MIN_SAMPLE
            ? (int) round(array_sum(array_map(
                fn (string $k) => $sumbu[$k] * self::BOBOT[$k] / 100,
                array_keys(self::BOBOT),
            )))
            : null;

        return new self($nilai, $sumbu, $jumlah);
    }

    public function hasEnoughSample(): bool
    {
        return $this->sampleSize >= TradeStatistics::MIN_SAMPLE;
    }

    /** @param array<int, int> $skor */
    private static function rata(array $skor): float
    {
        return $skor === [] ? 0.0 : round(array_sum($skor) / count($skor), 2);
    }

    /**
     * Konsistensi dari simpangan baku kepatuhan.
     *
     * Satu trade tidak punya sebaran, jadi konsistensinya tidak diketahui -
     * bukan sempurna. Dikembalikan 0 supaya tidak ada yang mendapat nilai
     * penuh hanya karena baru mencatat sekali.
     *
     * @param  array<int, int>  $skor
     */
    private static function dariSebaran(array $skor): float
    {
        if (count($skor) < 2) {
            return 0.0;
        }

        $rata = array_sum($skor) / count($skor);
        $ragam = array_sum(array_map(fn (int $s) => ($s - $rata) ** 2, $skor)) / count($skor);

        return round(100 - min(sqrt($ragam), self::SD_TERBURUK) / self::SD_TERBURUK * 100, 2);
    }

    /**
     * Profit factor dipetakan dengan titik impas di tengah.
     *
     * PF 1,0 berarti untung dan rugi berimbang, jadi 50 adalah tempat yang
     * jujur untuknya. Pemetaan linear dari nol akan memberi 40 pada trader
     * yang sebenarnya impas.
     */
    private static function dariProfitFactor(?float $pf): float
    {
        if ($pf === null) {
            return 0.0;
        }

        if ($pf <= 1.0) {
            return round(max(0.0, $pf) * 50, 2);
        }

        return round(50 + min($pf - 1.0, self::PF_TERBAIK - 1.0) / (self::PF_TERBAIK - 1.0) * 50, 2);
    }

    private static function dariWinRate(?float $wr): float
    {
        if ($wr === null) {
            return 0.0;
        }

        return round(min(max($wr, 0.0), self::WR_TERBAIK) / self::WR_TERBAIK * 100, 2);
    }
}
