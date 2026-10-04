<?php

namespace App\Support;

use App\Models\Trade;
use App\Models\TradeRuleCheck;
use Illuminate\Support\Collection;

/**
 * Agregasi per rule: berapa kali dilanggar, dan apa bedanya terhadap hasil.
 *
 * Diekstrak dari ComplianceReport supaya dashboard dan halaman Laporan membaca
 * angka yang sama persis. Dua tempat yang menghitung "rule paling sering
 * dilanggar" dengan kodenya masing-masing akan menyimpang pada kasus pertama
 * yang tidak terpikir - biasanya rule yang sudah dihapus.
 *
 * Pengelompokan WAJIB lewat setup_rule_id, bukan rule_label. Label adalah
 * snapshot dan boleh berbeda antar trade; mengelompokkan lewat label memecah
 * satu rule jadi beberapa baris palsu begitu pengguna memperbaiki typo pada
 * namanya.
 */
final class RuleViolations
{
    /**
     * Koleksi wajib sudah memuat relasi `ruleChecks`; strict mode melempar
     * exception kalau tidak.
     *
     * Terurut dari yang paling sering dilanggar.
     *
     * @param  Collection<int, Trade>  $closedTrades
     * @return array<int, array{rule_id:?int, label:string, violations:int, met:TradeStatistics, unmet:TradeStatistics}>
     */
    public static function from(Collection $closedTrades): array
    {
        $mentah = [];

        foreach ($closedTrades as $trade) {
            $r = $trade->rMultipleExact();

            if ($r === null) {
                continue;
            }

            foreach ($trade->ruleChecks as $check) {
                $kunci = self::kunci($check);

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

    /**
     * Beberapa teratas, dan hanya yang benar-benar pernah dilanggar.
     *
     * Rule yang tidak pernah dilanggar sekali pun tidak layak muncul di daftar
     * pelanggaran: ia akan terbaca sebagai tuduhan terhadap sesuatu yang justru
     * selalu dipatuhi.
     *
     * @param  Collection<int, Trade>  $closedTrades
     * @return array<int, array{rule_id:?int, label:string, violations:int, met:TradeStatistics, unmet:TradeStatistics}>
     */
    public static function top(Collection $closedTrades, int $limit = 3): array
    {
        $pernahDilanggar = array_filter(
            self::from($closedTrades),
            fn (array $baris) => $baris['violations'] > 0,
        );

        return array_slice(array_values($pernahDilanggar), 0, $limit);
    }

    private static function kunci(TradeRuleCheck $check): string
    {
        // Rule yang sudah dihapus kehilangan setup_rule_id-nya. Dikelompokkan
        // lewat label snapshot supaya riwayatnya tetap terbaca.
        return $check->setup_rule_id !== null
            ? 'id:'.$check->setup_rule_id
            : 'hapus:'.$check->rule_label;
    }
}
