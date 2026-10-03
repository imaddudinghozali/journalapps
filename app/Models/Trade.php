<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Satu trade yang sudah dicatat, beserta bukti kriteria apa yang terpenuhi
 * saat entry.
 *
 * compliance_score disimpan, bukan dihitung ulang saat laporan, supaya skor
 * selalu mencerminkan aturan yang berlaku saat trade itu dicatat. Nilainya
 * wajib konsisten dengan isi ruleChecks(); hanya ada satu jalur penulisan.
 */
class Trade extends Model
{
    use BelongsToUser, HasFactory;

    public const DIRECTION_LONG = 'long';

    public const DIRECTION_SHORT = 'short';

    /**
     * risk_amount dan pnl_amount sengaja TIDAK fillable: keduanya diturunkan
     * dari harga lewat TradeMath, bukan diketik pengguna. Menjadikannya
     * fillable membuka jalan angka yang tidak cocok dengan harganya sendiri.
     */
    protected $fillable = [
        'symbol',
        'direction',
        'lot_size',
        'entry_price',
        'stop_price',
        'exit_price',
        'opened_at',
        'closed_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'risk_amount' => 'decimal:2',
            'pnl_amount' => 'decimal:2',
            'lot_size' => 'decimal:4',
            'entry_price' => 'decimal:8',
            'stop_price' => 'decimal:8',
            'exit_price' => 'decimal:8',
            'contract_size' => 'decimal:8',
            'compliance_score' => 'integer',
            'original_compliance_score' => 'integer',
            'checklist_revised_at' => 'datetime',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function setup(): BelongsTo
    {
        return $this->belongsTo(TradingSetup::class, 'trading_setup_id');
    }

    public function ruleChecks(): HasMany
    {
        return $this->hasMany(TradeRuleCheck::class);
    }

    public function instrument(): BelongsTo
    {
        return $this->belongsTo(Instrument::class);
    }

    public static function directions(): array
    {
        return [self::DIRECTION_LONG, self::DIRECTION_SHORT];
    }

    /**
     * Tertutup berarti KEDUANYA terisi.
     *
     * Sebelumnya hanya closed_at yang diperiksa, sementara daftar trade
     * memakai pnl_amount — dua penanda yang bisa berbeda. Laporan kepatuhan
     * adalah tempat perbedaan itu akan terpeleset, jadi definisinya
     * diseragamkan di sini dan ditegakkan validasi form.
     */
    public function isClosed(): bool
    {
        return $this->closed_at !== null && $this->pnl_amount !== null;
    }

    public function scopeClosed(Builder $query): void
    {
        $query->whereNotNull('closed_at')->whereNotNull('pnl_amount');
    }

    public function scopeStillOpen(Builder $query): void
    {
        $query->where(fn (Builder $q) => $q->whereNull('closed_at')->orWhereNull('pnl_amount'));
    }

    /**
     * Hasil dalam satuan risiko. Inilah yang membuat trade pada instrumen
     * berbeda bisa dibandingkan; nominal saja tidak sebanding.
     */
    public function rMultiple(): ?float
    {
        $exact = $this->rMultipleExact();

        return $exact === null ? null : round($exact, 2);
    }

    /**
     * R tanpa pembulatan, untuk agregasi.
     *
     * Membulatkan lebih dulu membuat hasil kecil seperti R 0,003 menjadi 0,00,
     * sehingga trade yang untung dihitung bukan kemenangan. Pembulatan hanya
     * untuk tampilan.
     */
    public function rMultipleExact(): ?float
    {
        if ($this->pnl_amount === null || (float) $this->risk_amount == 0.0) {
            return null;
        }

        return (float) $this->pnl_amount / (float) $this->risk_amount;
    }

    /** Checklist pernah diubah setelah trade tercatat. */
    public function checklistWasRevised(): bool
    {
        return $this->checklist_revised_at !== null;
    }

    public function isUnscored(): bool
    {
        return $this->compliance_score === null;
    }
}
