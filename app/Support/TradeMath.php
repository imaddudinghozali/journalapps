<?php

namespace App\Support;

use App\Models\Trade;

/**
 * Perhitungan P&L dan risiko dari harga.
 *
 * Dipisah dari model dan komponen supaya bisa diuji tanpa database, dan
 * supaya hanya ada satu tempat yang menentukan arti angka-angka ini.
 *
 * Ukuran kontrak adalah yang membedakan instrumen. Tanpa itu, pergerakan
 * 1,00 pada XAUUSD akan terbaca sebagai 1, bukan 100, dan seluruh penilaian
 * performa pengguna meleset.
 */
final class TradeMath
{
    /**
     * @return float|null null berarti posisi belum ditutup
     */
    public static function pnl(
        string $direction,
        float $lot,
        float $entry,
        ?float $exit,
        float $contractSize,
    ): ?float {
        if ($exit === null) {
            return null;
        }

        $gerak = $exit - $entry;

        if ($direction === Trade::DIRECTION_SHORT) {
            $gerak = -$gerak;
        }

        return round($gerak * $lot * $contractSize, 2);
    }

    /**
     * @return float|null null berarti risiko tidak terdefinisi
     */
    public static function risk(
        float $lot,
        float $entry,
        float $stop,
        float $contractSize,
    ): ?float {
        $jarak = abs($entry - $stop);

        // Stop berimpit dengan entry berarti risiko tidak terdefinisi, bukan
        // risiko nol. Mengembalikan nol akan membuat R-multiple membagi
        // dengan nol di tempat lain.
        if ($jarak == 0.0) {
            return null;
        }

        return round($jarak * $lot * $contractSize, 2);
    }
}
