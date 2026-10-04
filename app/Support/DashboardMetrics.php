<?php

namespace App\Support;

use App\Models\Trade;
use Illuminate\Support\Collection;

/**
 * Seluruh angka dashboard, dihitung sekali.
 *
 * Kelas ini MENYUSUN, bukan menghitung ulang. DashboardSummary tetap pemilik
 * angka dolar, ComplianceReport tetap pemilik perbandingan patuh vs melanggar,
 * dan RuleViolations tetap pemilik agregasi per rule. Yang ditambahkan di sini
 * hanya yang memang khas dashboard: angka dalam R, drawdown, rentetan, dan
 * ringkasan yang butuh melihat ketiganya sekaligus.
 *
 * Menghitung ulang salah satunya di sini berarti dashboard dan halaman Laporan
 * bisa menampilkan dua jawaban berbeda untuk pertanyaan yang sama.
 *
 * R dipakai sebagai satuan pokok, bukan dolar. Trade +200 dolar tidak bisa
 * dibandingkan dengan trade +200 dolar lain kalau risikonya 50 dan 500; R
 * menyamakan keduanya. Dolar tetap ada sebagai keterangan.
 */
final class DashboardMetrics
{
    /** Jendela pembanding untuk selisih kepatuhan. */
    private const HARI_JENDELA = 7;

    private const JUMLAH_TRADE_TERAKHIR = 5;

    /**
     * @param  array<int, array<string, mixed>>  $cumulativeR
     * @param  array<int, array{date:string, drawdown:float}>  $drawdownR
     * @param  array<int, array{trade:Trade, r:?float, met:int, total:int}>  $recentTrades
     * @param  array<int, array<string, mixed>>  $topViolations
     */
    private function __construct(
        public readonly DashboardSummary $summary,
        public readonly ComplianceReport $compliance,
        public readonly int $threshold,
        public readonly ?float $expectancyR,
        public readonly ?float $avgWinR,
        public readonly ?float $avgLossR,
        public readonly ?float $profitFactor,
        public readonly ?float $winRate,
        public readonly ?int $avgCompliance,
        public readonly ?int $complianceDelta,
        public readonly int $complianceStreak,
        public readonly int $requiredViolations,
        public readonly array $cumulativeR,
        public readonly array $drawdownR,
        public readonly ?float $maxDrawdownR,
        public readonly array $recentTrades,
        public readonly array $topViolations,
    ) {}

    /**
     * Koleksi wajib sudah memuat relasi `setup` dan `ruleChecks`.
     *
     * Menerima SELURUH trade, bukan yang tertutup saja: jumlah posisi terbuka
     * ikut ditampilkan, dan menyaringnya di pemanggil berarti tiap pemanggil
     * harus ingat melakukannya.
     *
     * @param  Collection<int, Trade>  $trades
     */
    public static function from(Collection $trades, int $threshold): self
    {
        $tertutup = $trades
            ->filter(fn (Trade $t) => $t->isClosed())
            ->sortBy([['closed_at', 'asc'], ['id', 'asc']])
            ->values();

        $r = $tertutup
            ->map(fn (Trade $t) => $t->rMultipleExact())
            ->filter(fn (?float $x) => $x !== null)
            ->values()
            ->all();

        $statistik = TradeStatistics::from($r);
        [$kumulatif, $drawdown, $maksimum] = self::kurva($tertutup, $threshold);

        return new self(
            DashboardSummary::from($trades),
            ComplianceReport::from($tertutup, $threshold),
            $threshold,
            $statistik->expectancy,
            self::rata(array_filter($r, fn (float $x) => $x > 0)),
            self::rata(array_filter($r, fn (float $x) => $x < 0)),
            self::profitFactor($r),
            $statistik->winRate,
            self::rataKepatuhan($tertutup),
            self::selisihKepatuhan($tertutup),
            self::rentetan($tertutup, $threshold),
            self::pelanggaranWajib($tertutup),
            $kumulatif,
            $drawdown,
            $maksimum,
            self::tradeTerakhir($tertutup),
            RuleViolations::top($tertutup),
        );
    }

    public function isEmpty(): bool
    {
        return $this->summary->closedCount === 0 && $this->summary->openCount === 0;
    }

    /**
     * Kalimat insight hanya dibuka kalau KEDUA kelompok cukup sampelnya.
     *
     * Membandingkan dua kelompok yang salah satunya cuma berisi tiga trade
     * bukan perbandingan, melainkan kebetulan yang dibacakan seolah temuan.
     */
    public function insightSiap(): bool
    {
        return $this->compliance->compliant->hasEnoughSample()
            && $this->compliance->nonCompliant->hasEnoughSample();
    }

    /**
     * Kemajuan menuju ambang sampel, memakai kelompok yang paling tertinggal.
     *
     * @return array{0:int, 1:int}
     */
    public function insightKemajuan(): array
    {
        return [
            min($this->compliance->compliant->sampleSize, $this->compliance->nonCompliant->sampleSize),
            TradeStatistics::MIN_SAMPLE,
        ];
    }

    /**
     * Skor Disiplin, disusun dari angka yang sudah dihitung di atas.
     *
     * Dihitung saat diminta, bukan di konstruktor, supaya halaman yang tidak
     * menampilkan kartunya tidak ikut membayar biayanya.
     */
    public function disiplin(): DisciplineScore
    {
        $skor = [];

        foreach ($this->cumulativeR as $titik) {
            if ($titik['score'] !== null) {
                $skor[] = (int) $titik['score'];
            }
        }

        return DisciplineScore::from($skor, $this->profitFactor, $this->winRate);
    }

    /**
     * Rata-rata kepatuhan per tanggal, untuk mewarnai sel kalender.
     *
     * Diturunkan dari cumulativeR yang sudah memuat tanggal dan skor tiap
     * trade, jadi tidak ada kueri tambahan dan tidak mungkin menyimpang dari
     * angka yang dipakai grafik.
     *
     * @return array<string, int>
     */
    public function kepatuhanHarian(): array
    {
        $per = [];

        foreach ($this->cumulativeR as $titik) {
            if ($titik['score'] !== null) {
                $per[$titik['date']][] = (int) $titik['score'];
            }
        }

        return array_map(
            fn (array $s) => (int) round(array_sum($s) / count($s)),
            $per,
        );
    }

    /** @param  Collection<int, Trade>  $tertutup */
    private static function rataKepatuhan(Collection $tertutup): ?int
    {
        $skor = $tertutup->pluck('compliance_score')->filter(fn ($s) => $s !== null);

        return $skor->isEmpty() ? null : (int) round($skor->avg());
    }

    /**
     * Selisih kepatuhan tujuh hari terakhir terhadap tujuh hari sebelumnya.
     *
     * null kalau salah satu jendela kosong. Memakai nol sebagai pengganti akan
     * menampilkan penurunan drastis padahal yang terjadi cuma pengguna sedang
     * tidak trading.
     *
     * @param  Collection<int, Trade>  $tertutup
     */
    private static function selisihKepatuhan(Collection $tertutup): ?int
    {
        $batasBaru = now()->subDays(self::HARI_JENDELA);
        $batasLama = now()->subDays(self::HARI_JENDELA * 2);

        $skor = fn (Collection $c) => $c->pluck('compliance_score')
            ->filter(fn ($s) => $s !== null);

        $baru = $skor($tertutup->filter(fn (Trade $t) => $t->closed_at->gte($batasBaru)));
        $lama = $skor($tertutup->filter(
            fn (Trade $t) => $t->closed_at->lt($batasBaru) && $t->closed_at->gte($batasLama),
        ));

        if ($baru->isEmpty() || $lama->isEmpty()) {
            return null;
        }

        return (int) round($baru->avg() - $lama->avg());
    }

    /**
     * Rentetan trade patuh, dihitung mundur dari yang paling baru.
     *
     * @param  Collection<int, Trade>  $tertutup
     */
    private static function rentetan(Collection $tertutup, int $threshold): int
    {
        $jumlah = 0;

        foreach ($tertutup->reverse() as $trade) {
            // Trade tanpa skor memutus rentetan, bukan melanjutkannya:
            // kepatuhannya tidak diketahui, dan menebaknya patuh berarti
            // memberi nilai yang tidak pernah diisi siapa pun.
            if (ComplianceTone::obeys($trade->compliance_score, $threshold) !== true) {
                break;
            }

            $jumlah++;
        }

        return $jumlah;
    }

    /** @param  Collection<int, Trade>  $tertutup */
    private static function pelanggaranWajib(Collection $tertutup): int
    {
        return (int) $tertutup->sum(
            fn (Trade $t) => $t->ruleChecks
                ->filter(fn ($c) => $c->rule_required && ! $c->is_met)
                ->count(),
        );
    }

    /**
     * Kurva R kumulatif dan drawdown-nya, dibangun dalam satu lintasan.
     *
     * @param  Collection<int, Trade>  $tertutup
     * @return array{0:array<int,array<string,mixed>>, 1:array<int,array<string,mixed>>, 2:?float}
     */
    private static function kurva(Collection $tertutup, int $threshold): array
    {
        $kumulatif = [];
        $drawdown = [];
        $jalan = 0.0;
        $puncak = 0.0;
        $terdalam = null;

        foreach ($tertutup as $trade) {
            $r = $trade->rMultipleExact();

            if ($r === null) {
                continue;
            }

            $jalan += $r;
            $puncak = max($puncak, $jalan);

            // Drawdown diukur dari puncak tertinggi yang pernah dicapai, bukan
            // dari nol. Nol hanya berarti sedang berada di puncak itu sendiri.
            $turun = round($jalan - $puncak, 4);
            $terdalam = $terdalam === null ? $turun : min($terdalam, $turun);

            $tanggal = $trade->closed_at->toDateString();

            $kumulatif[] = [
                'date' => $tanggal,
                'r' => round($r, 2),
                'cumulative' => round($jalan, 2),
                'compliant' => ComplianceTone::obeys($trade->compliance_score, $threshold),
                'score' => $trade->compliance_score,
                'symbol' => $trade->symbol,
            ];

            $drawdown[] = ['date' => $tanggal, 'drawdown' => $turun];
        }

        return [$kumulatif, $drawdown, $terdalam];
    }

    /**
     * @param  Collection<int, Trade>  $tertutup
     * @return array<int, array{trade:Trade, r:?float, met:int, total:int}>
     */
    private static function tradeTerakhir(Collection $tertutup): array
    {
        return $tertutup
            ->reverse()
            ->take(self::JUMLAH_TRADE_TERAKHIR)
            ->map(fn (Trade $t) => [
                'trade' => $t,
                'r' => $t->rMultipleExact(),
                'met' => $t->ruleChecks->where('is_met', true)->count(),
                'total' => $t->ruleChecks->count(),
            ])
            ->values()
            ->all();
    }

    /** @param  array<int, float>  $r */
    private static function profitFactor(array $r): ?float
    {
        $untung = array_sum(array_filter($r, fn (float $x) => $x > 0));
        $rugi = abs(array_sum(array_filter($r, fn (float $x) => $x < 0)));

        // Tanpa satu pun kerugian, profit factor tidak terdefinisi - bukan tak
        // hingga, dan jelas bukan nol. Menampilkannya sebagai angka besar akan
        // terbaca sebagai prestasi padahal yang terjadi cuma belum pernah rugi.
        return $rugi > 0.0 ? round($untung / $rugi, 2) : null;
    }

    /** @param  array<int, float>  $nilai */
    private static function rata(array $nilai): ?float
    {
        return $nilai === [] ? null : round(array_sum($nilai) / count($nilai), 2);
    }
}
