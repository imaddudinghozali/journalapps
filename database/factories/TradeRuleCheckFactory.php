<?php

namespace Database\Factories;

use App\Models\SetupRule;
use App\Models\TradeRuleCheck;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TradeRuleCheck>
 *
 * Snapshot diisi dengan nilai sendiri, bukan diambil dari relasi rule: itulah
 * inti jaminan versioning yang diuji RuleSnapshotTest.
 */
class TradeRuleCheckFactory extends Factory
{
    protected $model = TradeRuleCheck::class;

    public function definition(): array
    {
        return [
            'is_met' => true,
            'rule_label' => fake()->sentence(4),
            'rule_weight' => fake()->numberBetween(SetupRule::MIN_WEIGHT, SetupRule::MAX_WEIGHT),
            'rule_required' => false,
            'position' => 0,
        ];
    }

    public function unmet(): static
    {
        return $this->state(fn () => ['is_met' => false]);
    }

    public function required(): static
    {
        return $this->state(fn () => ['rule_required' => true]);
    }
}
