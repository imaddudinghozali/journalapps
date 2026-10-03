<?php

namespace App\Support;

/**
 * Skor kepatuhan sebuah trade terhadap rules setup-nya.
 *
 * Dipisah dari model dan komponen supaya aturannya bisa diuji tanpa database,
 * dan supaya hanya ada satu tempat yang menentukan arti angka ini.
 *
 * Angka ini dipakai pengguna untuk menilai dirinya sendiri, jadi perlakukan
 * perubahan rumusnya sebagai perubahan yang merusak data historis.
 */
final class ComplianceScore
{
    /**
     * @param  int|null  $value  0-100, atau null bila tidak ada yang bisa dinilai
     */
    private function __construct(
        public readonly ?int $value,
        public readonly int $unmetRequiredCount,
    ) {}

    /**
     * @param  array<int, array{weight: int, met: bool, required?: bool}>  $checks
     */
    public static function from(array $checks): self
    {
        $totalWeight = 0;
        $metWeight = 0;
        $unmetRequired = 0;

        foreach ($checks as $check) {
            $totalWeight += $check['weight'];

            if ($check['met']) {
                $metWeight += $check['weight'];
            } elseif ($check['required'] ?? false) {
                $unmetRequired++;
            }
        }

        // Tanpa rule — atau tanpa bobot sama sekali — tidak ada yang bisa
        // dinilai. Menyebutnya 100 adalah kebohongan yang akan merusak laporan;
        // menyebutnya 0 menghukum pengguna atas setup yang memang belum punya
        // kriteria.
        $value = $totalWeight > 0
            ? (int) round(100 * $metWeight / $totalWeight)
            : null;

        return new self($value, $unmetRequired);
    }

    public function isUnscored(): bool
    {
        return $this->value === null;
    }

    public function hasUnmetRequired(): bool
    {
        return $this->unmetRequiredCount > 0;
    }

    public function isBelow(int $threshold): bool
    {
        if ($this->isUnscored()) {
            return false;
        }

        return $this->value < $threshold;
    }

    /**
     * Pelanggaran rule wajib diperiksa terpisah dari skor: bobotnya bisa kecil
     * sehingga skor tetap tinggi padahal syarat mutlak dilanggar.
     */
    public function needsWarning(int $threshold): bool
    {
        return $this->isBelow($threshold) || $this->hasUnmetRequired();
    }
}
