<?php

namespace Database\Factories;

use App\Models\SpkStone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SpkStone>
 */
class SpkStoneFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'row_id' => fake()->numberBetween(1, 9999),
            'shape_id' => fake()->numberBetween(1, 5),
            'position_id' => null,
            'pcs' => fake()->numberBetween(1, 50),
            'carat' => fake()->randomFloat(3, 0.01, 2),
            'size' => number_format(fake()->randomFloat(2, 0.5, 5), 2, '.', ''),
            'is_deleted' => 0,
            'created_date' => now(),
            'created_by' => 'tester',
            'modified_date' => null,
            'modified_by' => null,
            'deleted_date' => null,
            'deleted_by' => null,
        ];
    }
}
