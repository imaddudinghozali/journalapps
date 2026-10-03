<?php

namespace Database\Factories;

use App\Models\Trade;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Trade>
 *
 * Tanpa default user_id maupun trading_setup_id: pemanggil wajib menyebut
 * keduanya. Lihat TradingSetupFactory soal alasannya.
 */
class TradeFactory extends Factory
{
    protected $model = Trade::class;

    public function definition(): array
    {
        return [
            'symbol' => fake()->randomElement(['XAUUSD', 'EURUSD', 'BTCUSD', 'GBPJPY']),
            'direction' => fake()->randomElement(Trade::directions()),
            'risk_amount' => fake()->randomFloat(2, 10, 200),
            'pnl_amount' => fake()->randomFloat(2, -200, 400),
            'compliance_score' => null,
            'opened_at' => now()->subDays(fake()->numberBetween(1, 60)),
            'closed_at' => null,
            'notes' => null,
        ];
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'closed_at' => $attributes['opened_at'],
        ]);
    }

    public function scored(int $score): static
    {
        return $this->state(fn () => ['compliance_score' => $score]);
    }
}
