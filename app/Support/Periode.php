<?php

namespace App\Support;

use App\Models\Trade;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Rentang waktu yang dipilih di dashboard.
 *
 * Satu-satunya tempat kunci periode diterjemahkan menjadi tanggal. Kuncinya
 * datang dari query string, jadi ia masukan yang tidak dipercaya: kunci asing
 * JATUH ke seluruh riwayat, bukan melempar dan bukan menghasilkan rentang
 * kosong. Rentang kosong akan terbaca sebagai "kamu tidak punya trade", yang
 * jauh lebih buruk daripada memperlihatkan lebih banyak dari yang diminta.
 *
 * Batasnya kalender, bukan jendela berjalan: "Bulan ini" berarti tanggal 1
 * sampai akhir bulan, bukan 30 hari terakhir. Itu satuan yang dipakai orang
 * saat meninjau, dan satuan yang sama dengan kalender di bawah dashboard -
 * termasuk awal minggu pada hari Minggu.
 *
 * Batas atas dipasang walau trade bertanggal masa depan seharusnya tidak ada.
 * "Bulan ini" yang ikut memuat bulan depan bukan salah sedikit, ia salah.
 */
final class Periode
{
    public const HARI = 'hari';

    public const MINGGU = 'minggu';

    public const BULAN = 'bulan';

    public const SEMUA = 'semua';

    /** Yang dipakai kalau tidak ada yang dipilih, dan kalau kuncinya asing. */
    public const BAWAAN = self::SEMUA;

    private const LABEL = [
        self::HARI => 'Hari ini',
        self::MINGGU => 'Minggu ini',
        self::BULAN => 'Bulan ini',
        self::SEMUA => 'Semua',
    ];

    private function __construct(public readonly string $kunci) {}

    public static function dari(?string $kunci): self
    {
        return new self(
            $kunci !== null && array_key_exists($kunci, self::LABEL) ? $kunci : self::BAWAAN,
        );
    }

    /** @return array<string, string> kunci => label */
    public static function daftar(): array
    {
        return self::LABEL;
    }

    public function label(): string
    {
        return self::LABEL[$this->kunci];
    }

    public function seluruhRiwayat(): bool
    {
        return $this->kunci === self::SEMUA;
    }

    /** null berarti tidak dibatasi. */
    public function mulai(): ?Carbon
    {
        return match ($this->kunci) {
            self::HARI => now()->startOfDay(),
            self::MINGGU => now()->startOfWeek(Carbon::SUNDAY),
            self::BULAN => now()->startOfMonth(),
            default => null,
        };
    }

    /** null berarti tidak dibatasi. */
    public function sampai(): ?Carbon
    {
        return match ($this->kunci) {
            self::HARI => now()->endOfDay(),
            self::MINGGU => now()->endOfWeek(Carbon::SATURDAY),
            self::BULAN => now()->endOfMonth(),
            default => null,
        };
    }

    /**
     * Trade yang HASILNYA jatuh di dalam periode ini.
     *
     * Posisi terbuka selalu tersingkir, bukan cuma di periode sempit: ia belum
     * punya hasil, jadi ia bukan milik periode mana pun. Bagian "Posisi
     * terbuka" di dashboard mengambil datanya sendiri dan tidak lewat sini,
     * supaya posisi menggantung tidak pernah hilang dari layar karena pilihan
     * rentang waktu.
     *
     * @param  Collection<int, Trade>  $trades
     * @return Collection<int, Trade>
     */
    public function saring(Collection $trades): Collection
    {
        if ($this->seluruhRiwayat()) {
            return $trades;
        }

        $mulai = $this->mulai();
        $sampai = $this->sampai();

        return $trades
            ->filter(fn (Trade $t) => $t->isClosed()
                && $t->closed_at->gte($mulai)
                && $t->closed_at->lte($sampai))
            ->values();
    }
}
