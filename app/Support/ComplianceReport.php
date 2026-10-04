<?php

namespace App\Support;

use App\Models\Trade;
use Illuminate\Support\Collection;

/**
 * Agregasi laporan kepatuhan terhadap hasil.
 *
 * Menerima koleksi trade TERTUTUP yang sudah terscope ke pemiliknya, lalu
 * menyusun perbandingan yang menjawab pertanyaan inti produk: apakah kerugian
 * berasal dari strategi yang buruk, atau dari melanggar aturan sendiri.
 *
 * Kelas ini sengaja tidak melakukan query sendiri — pemanggil yang menyiapkan
 * koleksinya, supaya isolasi data tetap ditegakkan global scope di satu tempat.
 */
final class ComplianceReport
{
    public const MIN_SAMPLE = TradeStatistics::MIN_SAMPLE;

    private function __construct(
        public readonly TradeStatistics $overall,
        public readonly TradeStatistics $compliant,
        public readonly TradeStatistics $nonCompliant,
        public readonly int $unscoredCount,
        public readonly int $revisedCount,
        /** @var array<int, array{name: string, stats: TradeStatistics}> */
        public readonly array $perSetup,
        /** @var array<int, array{rule_id: int|null, label: string, violations: int, met: TradeStatistics, unmet: TradeStatistics}> */
        public readonly array $perRule,
    ) {}

    /**
     * Koleksi wajib sudah memuat relasi `setup` dan `ruleChecks`
     * (`Trade::with(['setup','ruleChecks'])->closed()->get()`). Strict mode
     * akan melempar exception kalau tidak.
     *
     * @param  Collection<int, Trade>  $closedTrades
     */
    public static function from(Collection $closedTrades, int $threshold): self
    {
        $semua = [];
        $patuh = [];
        $tidakPatuh = [];
        $tanpaSkor = 0;
        $direvisi = 0;
        $perSetup = [];

        foreach ($closedTrades as $trade) {
            $r = $trade->rMultipleExact();

            if ($r === null) {
                continue;
            }

            $semua[] = $r;

            if ($trade->checklistWasRevised()) {
                $direvisi++;
            }
            $perSetup[$trade->trading_setup_id]['name'] ??= $trade->setup->name;
            $perSetup[$trade->trading_setup_id]['r'][] = $r;

            // Trade tanpa skor tidak masuk kelompok mana pun. Setup tanpa rule
            // tidak punya kepatuhan untuk dinilai; memaksanya ke salah satu
            // kelompok akan mencemari seluruh perbandingan.
            if ($trade->compliance_score === null) {
                $tanpaSkor++;

                continue;
            }

            if ($trade->compliance_score >= $threshold) {
                $patuh[] = $r;
            } else {
                $tidakPatuh[] = $r;
            }
        }

        return new self(
            TradeStatistics::from($semua),
            TradeStatistics::from($patuh),
            TradeStatistics::from($tidakPatuh),
            $tanpaSkor,
            $direvisi,
            self::ringkasSetup($perSetup),
            RuleViolations::from($closedTrades),
        );
    }

    public function isEmpty(): bool
    {
        return $this->overall->sampleSize === 0;
    }

    /**
     * @param  array<int, array{name: string, r: array<int, float>}>  $mentah
     * @return array<int, array{name: string, stats: TradeStatistics}>
     */
    private static function ringkasSetup(array $mentah): array
    {
        $hasil = [];

        foreach ($mentah as $baris) {
            $hasil[] = ['name' => $baris['name'], 'stats' => TradeStatistics::from($baris['r'])];
        }

        usort($hasil, fn (array $a, array $b) => $b['stats']->sampleSize <=> $a['stats']->sampleSize);

        return $hasil;
    }


}
