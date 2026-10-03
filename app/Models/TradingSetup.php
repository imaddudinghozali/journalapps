<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Satu pola entry yang dikenali pengguna, mis. Break of Structure atau
 * Order Block, beserta daftar kriteria entry-nya.
 *
 * user_id sengaja tidak masuk $fillable: kepemilikan diisi trait, bukan oleh
 * request. Lihat CLAUDE.md.
 */
class TradingSetup extends Model
{
    use BelongsToUser, HasFactory;

    /**
     * Batas rule aktif per setup. Checklist yang terlalu panjang membuat
     * pengguna berhenti mencatat — itu risiko Tinggi di PRD.
     */
    public const MAX_ACTIVE_RULES = 15;

    protected $fillable = ['name', 'description'];

    protected function casts(): array
    {
        return ['archived_at' => 'datetime'];
    }

    public function rules(): HasMany
    {
        return $this->hasMany(SetupRule::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->whereNull('archived_at');
    }

    public function scopeArchived(Builder $query): void
    {
        $query->whereNotNull('archived_at');
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }
}
