<?php

namespace Database\Factories;

use App\Models\SetupRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SetupRule>
 *
 * Seperti TradingSetupFactory, tidak memberi default user_id maupun
 * trading_setup_id: keduanya harus disebut eksplisit oleh pemanggil.
 */
class SetupRuleFactory extends Factory
{
    protected $model = SetupRule::class;

    public function definition(): array
    {
        return [
            'label' => fake()->sentence(4),
            'weight' => fake()->numberBetween(SetupRule::MIN_WEIGHT, SetupRule::MAX_WEIGHT),
            'is_required' => false,
            'position' => 0,
            'archived_at' => null,
        ];
    }

    public function required(): static
    {
        return $this->state(fn () => ['is_required' => true]);
    }

    public function archived(): static
    {
        return $this->state(fn () => ['archived_at' => now()]);
    }
}
