<?php

namespace Database\Factories;

use App\Models\DiamondCrtMatrix;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DiamondCrtMatrix>
 */
class DiamondCrtMatrixFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $crtMin = fake()->randomFloat(2, 0.18, 0.9);

        return [
            'shape_id' => fake()->numberBetween(1, 19),
            'crt_min' => number_format($crtMin, 3, '.', ''),
            'crt_max' => number_format($crtMin + 0.09, 3, '.', ''),
            'sort_order' => fake()->numberBetween(1, 200),
            'updated_by' => 'system',
        ];
    }
}
