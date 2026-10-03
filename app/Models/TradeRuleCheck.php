<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Jawaban satu baris checklist pada satu trade.
 *
 * Menyimpan SALINAN label, bobot, dan status wajib rule saat trade dicatat.
 * Jangan membaca nilai itu dari relasi rule() untuk keperluan skor atau
 * laporan historis — rule-nya boleh berubah, snapshot ini tidak.
 *
 * rule() hanya untuk mengelompokkan: "rule mana yang paling sering dilanggar"
 * harus memakai setup_rule_id, bukan rule_label, karena label bisa diedit.
 */
class TradeRuleCheck extends Model
{
    use BelongsToUser, HasFactory;

    protected $fillable = [
        'is_met',
        'rule_label',
        'rule_weight',
        'rule_required',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'is_met' => 'boolean',
            'rule_weight' => 'integer',
            'rule_required' => 'boolean',
            'position' => 'integer',
        ];
    }

    public function trade(): BelongsTo
    {
        return $this->belongsTo(Trade::class);
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(SetupRule::class, 'setup_rule_id');
    }

    /** Bentuk yang diterima ComplianceScore::from(). */
    public function toScoreInput(): array
    {
        return [
            'weight' => $this->rule_weight,
            'met' => $this->is_met,
            'required' => $this->rule_required,
        ];
    }
}
