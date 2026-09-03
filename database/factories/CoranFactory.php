<?php

namespace Database\Factories;

use App\Models\Coran;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Coran>
 */
class CoranFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'doc_no' => sprintf('COR%07d', fake()->unique()->numberBetween(1, 9999999)),
            'trans_date' => fake()->date(),
            'craftsman_id' => null,
            'submit_material_rosegold' => number_format(fake()->randomFloat(3, 0, 100), 3, '.', ''),
            'submit_material_whitegold' => number_format(fake()->randomFloat(3, 0, 100), 3, '.', ''),
            'submit_material_yellowgold' => '0.000',
            'result_material_rosegold' => number_format(fake()->randomFloat(3, 0, 80), 3, '.', ''),
            'result_material_whitegold' => number_format(fake()->randomFloat(3, 0, 80), 3, '.', ''),
            'result_material_yellowgold' => '0.000',
            'shrink' => number_format(fake()->randomFloat(3, 0, 2), 3, '.', ''),
            'weight' => '0.000',
            'status' => null,
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
     * Indicate that the coran is soft-deleted.
     */
    public function deleted(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_deleted' => 1,
            'deleted_date' => now(),
            'deleted_by' => 'system',
        ]);
    }

    /**
     * Indicate that the coran is done.
     */
    public function done(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => Coran::STATUS_DONE,
        ]);
    }
}
