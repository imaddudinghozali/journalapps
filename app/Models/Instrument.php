<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Instrumen yang ditradingkan, beserta ukuran kontraknya.
 *
 * Ukuran kontrak adalah pengali yang mengubah pergerakan harga menjadi uang:
 * XAUUSD 100 oz per lot, EURUSD 100.000 unit per lot, BTCUSD 1. Tanpa angka
 * ini, P&L setiap pair forex dan emas akan salah beberapa orde besaran.
 */
class Instrument extends Model
{
    use BelongsToUser, HasFactory;

    /** Pengali lazim, dipakai sebagai saran saat membuat instrumen baru. */
    public const SARAN = [
        'XAUUSD' => 100,
        'XAGUSD' => 5000,
        'EURUSD' => 100000,
        'GBPUSD' => 100000,
        'USDJPY' => 100000,
        'GBPJPY' => 100000,
        'BTCUSD' => 1,
        'ETHUSD' => 1,
    ];

    protected $fillable = ['symbol', 'contract_size'];

    protected function casts(): array
    {
        return [
            'contract_size' => 'decimal:8',
            'archived_at' => 'datetime',
        ];
    }

    public function trades(): HasMany
    {
        return $this->hasMany(Trade::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->whereNull('archived_at');
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }
}
