<?php

namespace App\Support;

/**
 * Pemetaan skor kepatuhan ke warna, dan hanya ke warna.
 *
 * Satu-satunya tempat ambang zona ditulis. Tanpa ini, tiap halaman memilih
 * sendiri kapan sebuah skor "cukup tinggi", dan skor 72 bisa tampil kuning di
 * dashboard tapi hijau di laporan.
 *
 * Dua pertanyaan yang sering tertukar dan sengaja dipisah di sini:
 *
 *   zone()  - sebatas tampilan. Ambangnya TETAP dan sama untuk semua
 *             pengguna, supaya warna bisa dibandingkan antar layar.
 *   obeys() - patuh atau melanggar, diukur terhadap ambang milik pengguna
 *             sendiri (users.compliance_threshold). Ini yang membagi
 *             kelompok di laporan.
 *
 * Keduanya tidak boleh saling menggantikan. Pengguna berambang 90 yang
 * mencatat skor 80 tetap melanggar menurut ambangnya sendiri, walau warnanya
 * hijau. Memakai warna untuk menjawab pertanyaan kedua akan diam-diam
 * mengganti ambang pengguna dengan ambang aplikasi.
 */
final class ComplianceTone
{
    public const ZONE_LOSS = 'loss';

    public const ZONE_WARN = 'warn';

    public const ZONE_PROFIT = 'profit';

    /** Belum dinilai. Bukan nol, dan bukan kegagalan. */
    public const ZONE_NEUTRAL = 'neutral';

    /** Di bawah angka ini, skor masuk zona rugi. */
    public const BATAS_RUGI = 50;

    /** Di atas angka ini, skor masuk zona untung. */
    public const BATAS_UNTUNG = 75;

    public static function zone(?int $score): string
    {
        if ($score === null) {
            return self::ZONE_NEUTRAL;
        }

        if ($score < self::BATAS_RUGI) {
            return self::ZONE_LOSS;
        }

        return $score > self::BATAS_UNTUNG ? self::ZONE_PROFIT : self::ZONE_WARN;
    }

    /** Kelas warna teks untuk angka skornya sendiri. */
    public static function textClass(?int $score): string
    {
        return match (self::zone($score)) {
            self::ZONE_LOSS => 'text-viz-negative',
            self::ZONE_WARN => 'text-warn',
            self::ZONE_PROFIT => 'text-viz-positive',
            default => 'text-ink-faint',
        };
    }

    /** Latar lembut plus garis tepi, untuk lencana dan sel kalender. */
    public static function surfaceClass(?int $score): string
    {
        return match (self::zone($score)) {
            self::ZONE_LOSS => 'bg-viz-negative/10 border-viz-negative/30',
            self::ZONE_WARN => 'bg-warn/10 border-warn/30',
            self::ZONE_PROFIT => 'bg-viz-positive/10 border-viz-positive/30',
            default => 'bg-surface-sunken border-line',
        };
    }

    /**
     * Warna untuk atribut SVG, yang tidak bisa memakai kelas Tailwind.
     *
     * Mengembalikan rujukan ke token yang sama dengan kelas di atas, bukan
     * hex, supaya cincin di SVG tidak pernah menyimpang dari teks di
     * sampingnya saat palet berubah.
     */
    public static function ringColor(?int $score): string
    {
        return match (self::zone($score)) {
            self::ZONE_LOSS => 'rgb(var(--viz-negative))',
            self::ZONE_WARN => 'rgb(var(--warn))',
            self::ZONE_PROFIT => 'rgb(var(--viz-positive))',
            default => 'rgb(var(--line-strong))',
        };
    }

    /**
     * Patuh terhadap ambang pengguna sendiri.
     *
     * null berarti pertanyaannya tidak berlaku: trade tanpa skor bukan patuh
     * dan bukan melanggar, dan harus dikeluarkan dari perbandingan - bukan
     * dihitung sebagai salah satunya.
     */
    public static function obeys(?int $score, int $threshold): ?bool
    {
        return $score === null ? null : $score >= $threshold;
    }
}
