<?php

namespace App\Support;

use App\Models\Trade;
use Illuminate\Support\Collection;

/**
 * Ringkasan untuk layar pertama.
 *
 * Berbeda dari ComplianceReport, kelas ini DESKRIPTIF: melaporkan apa yang
 * sudah terjadi pada data pengguna sendiri. Karena itu tidak ada ambang
 * sampel di sini. Ambang sampel ada di laporan, tempat perbandingan antar
 * kelompok bisa menyesatkan; "P&L bersihmu sejauh ini" tidak bisa.
 *
 * Koleksi wajib sudah memuat relasi setup bila namanya ikut dipakai.
 */
final class DashboardSummary
{
    private function __construct(
        public readonly float $netPnl,
        public readonly int $closedCount,
        public readonly int $openCount,
        public readonly ?float $avgTrade,
        public readonly ?float $avgWin,
        public readonly ?float $avgLoss,
        public readonly ?float $profitFactor,
        public readonly ?float $winRate,
        public readonly ?float $avgCompliance,
        /** @var array<int, array{date: string, value: float}> */
        public readonly array $cumulative,
        /** @var array<string, array{pnl: float, count: int}> */
        public readonly array $dailyPnl,
    ) {}

    /**
     * @param  Collection<int, Trade>  $trades  seluruh trade pengguna, terbuka maupun tertutup
     */
    public static function from(Collection $trades): self
    {
        $tertutup = $trades->filter(fn (Trade $t) => $t->isClosed())
            ->sortBy(fn (Trade $t) => $t->closed_at->timestamp)
            ->values();

        $terbuka = $trades->count() - $tertutup->count();

        $pnl = $tertutup->map(fn (Trade $t) => (float) $t->pnl_amount)->all();
        $menang = array_values(array_filter($pnl, fn (float $v) => $v > 0));
        $kalah = array_values(array_filter($pnl, fn (float $v) => $v < 0));

        $labaKotor = array_sum($menang);
        $rugiKotor = abs(array_sum($kalah));

        $skor = $tertutup
            ->filter(fn (Trade $t) => $t->compliance_score !== null)
            ->map(fn (Trade $t) => (float) $t->compliance_score)
            ->all();

        $jalan = 0.0;
        $kumulatif = [];
        foreach ($tertutup as $t) {
            $jalan += (float) $t->pnl_amount;
            $kumulatif[] = [
                'date' => $t->closed_at->toDateString(),
                'value' => round($jalan, 2),
            ];
        }

        $harian = [];
        foreach ($tertutup as $t) {
            // Dikelompokkan pada tanggal TUTUP: hasil baru ada saat trade
            // ditutup, jadi menaruhnya di tanggal buka berarti menaruh angka
            // pada hari yang hasilnya belum diketahui.
            $kunci = $t->closed_at->toDateString();
            $harian[$kunci]['pnl'] = round(($harian[$kunci]['pnl'] ?? 0) + (float) $t->pnl_amount, 2);
            $harian[$kunci]['count'] = ($harian[$kunci]['count'] ?? 0) + 1;
        }
        ksort($harian);

        return new self(
            round(array_sum($pnl), 2),
            $tertutup->count(),
            $terbuka,
            self::rata($pnl),
            self::rata($menang),
            self::rata($kalah),
            // Tanpa trade rugi, pembaginya nol. Itu bukan "tak terhingga" yang
            // layak dicetak sebagai angka; null, lalu antarmuka menjelaskan.
            $rugiKotor > 0 ? round($labaKotor / $rugiKotor, 2) : null,
            $pnl === [] ? null : round(100 * count($menang) / count($pnl), 2),
            self::rata($skor),
            $kumulatif,
            $harian,
        );
    }

    public function isEmpty(): bool
    {
        return $this->closedCount === 0 && $this->openCount === 0;
    }

    /** P&L harian untuk satu bulan, siap dipakai kalender. */
    public function monthlyPnl(int $year, int $month): array
    {
        $awalan = sprintf('%04d-%02d-', $year, $month);

        return array_filter(
            $this->dailyPnl,
            fn (string $tanggal) => str_starts_with($tanggal, $awalan),
            ARRAY_FILTER_USE_KEY
        );
    }

    /** @param array<int, float> $nilai */
    private static function rata(array $nilai): ?float
    {
        return $nilai === [] ? null : round(array_sum($nilai) / count($nilai), 2);
    }
}
