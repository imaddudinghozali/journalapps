<?php

namespace Database\Factories;

use App\Models\TradingSetup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TradingSetup>
 *
 * Sengaja tidak memberi default user_id. Factory berjalan di luar konteks
 * request, sehingga pemanggil wajib menyebut pemiliknya lewat ->for($user).
 * Memberi default di sini akan menyembunyikan kesalahan kepemilikan.
 */
class TradingSetupFactory extends Factory
{
    protected $model = TradingSetup::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(3, true),
            'description' => fake()->optional()->sentence(),
            'archived_at' => null,
        ];
    }

    public function archived(): static
    {
        return $this->state(fn () => ['archived_at' => now()]);
    }
}
