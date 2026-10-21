<?php

namespace Database\Factories;

use App\Models\TrKomponen;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrKomponen>
 */
class TrKomponenFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'entry_date' => fake()->date(),
            'out_date' => null,
            'jenis_komponen' => fake()->randomElement(['Chain', 'Clasp', 'Bail']),
            'name' => fake()->words(2, true),
            'item_code' => strtoupper(fake()->bothify('???###')),
            'supplier' => fake()->company(),
            'length' => fake()->randomFloat(2, 30, 50),
            'color' => fake()->randomElement(['Rose Gold', 'White Gold', 'Yellow Gold']),
            'weight' => fake()->randomFloat(2, 0.5, 3),
            'spk_id' => null,
            'is_used' => 0,
            'is_reserved' => 0,
            'is_deleted' => 0,
            'created_date' => now(),
            'created_by' => 'system',
            'modified_date' => now(),
            'modified_by' => 'system',
            'deleted_date' => null,
            'deleted_by' => null,
        ];
    }

    /**
     * Indicate that the komponen row is soft-deleted.
     */
    public function deleted(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_deleted' => 1,
            'deleted_date' => now(),
            'deleted_by' => 'system',
        ]);
    }
}
