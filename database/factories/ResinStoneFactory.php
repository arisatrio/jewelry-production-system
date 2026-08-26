<?php

namespace Database\Factories;

use App\Models\Resin;
use App\Models\ResinStone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ResinStone>
 */
class ResinStoneFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'row_id' => Resin::factory(),
            'shape_id' => null,
            'pcs' => fake()->numberBetween(1, 20),
            'carat' => fake()->numberBetween(1, 50),
            'size' => number_format(fake()->randomFloat(2, 0.1, 10), 2, '.', ''),
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
     * Indicate that the stone is soft-deleted.
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
