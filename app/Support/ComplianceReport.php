<?php

namespace App\Support;

use App\Models\Trade;
use App\Models\TradeRuleCheck;
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
        $perSetup = [];

        foreach ($closedTrades as $trade) {
            $r = $trade->rMultipleExact();

            if ($r === null) {
                continue;
            }

            $semua[] = $r;
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
            self::ringkasSetup($perSetup),
            self::ringkasRule($closedTrades),
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

    /**
     * Dikelompokkan lewat setup_rule_id, BUKAN rule_label.
     *
     * Label disalin sebagai snapshot dan boleh berbeda antar trade kalau
     * pengguna merevisi rule-nya. Mengelompokkan lewat label akan memecah satu
     * rule menjadi beberapa baris palsu, dan angkanya jadi menyesatkan.
     *
     * @param  Collection<int, Trade>  $closedTrades
     * @return array<int, array{rule_id: int|null, label: string, violations: int, met: TradeStatistics, unmet: TradeStatistics}>
     */
    private static function ringkasRule(Collection $closedTrades): array
    {
        $mentah = [];

        foreach ($closedTrades as $trade) {
            $r = $trade->rMultipleExact();

            if ($r === null) {
                continue;
            }

            foreach ($trade->ruleChecks as $check) {
                $kunci = self::kunciRule($check);

                $mentah[$kunci]['rule_id'] ??= $check->setup_rule_id;
                // Label terakhir yang terlihat dipakai sebagai nama tampilan.
                $mentah[$kunci]['label'] = $check->rule_label;
                $mentah[$kunci]['met'] ??= [];
                $mentah[$kunci]['unmet'] ??= [];

                if ($check->is_met) {
                    $mentah[$kunci]['met'][] = $r;
                } else {
                    $mentah[$kunci]['unmet'][] = $r;
                }
            }
        }

        $hasil = [];

        foreach ($mentah as $baris) {
            $hasil[] = [
                'rule_id' => $baris['rule_id'],
                'label' => $baris['label'],
                'violations' => count($baris['unmet']),
                'met' => TradeStatistics::from($baris['met']),
                'unmet' => TradeStatistics::from($baris['unmet']),
            ];
        }

        // Paling sering dilanggar lebih dulu: itu yang paling berguna dilihat.
        usort($hasil, fn (array $a, array $b) => $b['violations'] <=> $a['violations']);

        return $hasil;
    }

    private static function kunciRule(TradeRuleCheck $check): string
    {
        // Rule yang sudah dihapus kehilangan setup_rule_id-nya. Dikelompokkan
        // lewat label snapshot supaya riwayatnya tetap terbaca.
        return $check->setup_rule_id !== null
            ? 'id:'.$check->setup_rule_id
            : 'hapus:'.$check->rule_label;
    }
}
