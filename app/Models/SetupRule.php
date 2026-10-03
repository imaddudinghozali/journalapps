<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu kriteria entry di dalam sebuah setup, mis. "Ada BOS di timeframe H4".
 *
 * Membawa user_id sendiri, bukan hanya trading_setup_id, karena global scope
 * bekerja per-model. Tanpa kolom itu, SetupRule::find() tidak terscope.
 */
class SetupRule extends Model
{
    use BelongsToUser, HasFactory;

    /** Skala bobot yang diizinkan. */
    public const MIN_WEIGHT = 1;

    public const MAX_WEIGHT = 5;

    protected $fillable = ['label', 'weight', 'is_required', 'position'];

    protected function casts(): array
    {
        return [
            'weight' => 'integer',
            'is_required' => 'boolean',
            'position' => 'integer',
            'archived_at' => 'datetime',
        ];
    }

    public function setup(): BelongsTo
    {
        return $this->belongsTo(TradingSetup::class, 'trading_setup_id');
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
