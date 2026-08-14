<?php

namespace Database\Factories;

use App\Models\MsItem;
use App\Models\MsItemVariance;
use App\Support\GoldColorOptions;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MsItemVariance>
 */
class MsItemVarianceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'item_id' => MsItem::factory(),
            'name' => fake()->unique()->words(2, true),
            'description' => fake()->optional()->sentence(),
            'diameter' => fake()->optional()->randomElement(['14', '15', '16', '18']),
            'dimensi' => fake()->optional()->randomElement(['10x8', '12x10', '50cm', '60cm']),
            'ring_size' => fake()->optional()->randomElement(['16', '17', '18', '19']),
            'diameter_length_ringsize' => null,
            'gold_weight' => fake()->optional()->randomFloat(2, 1, 50),
            'gold_color' => fake()->optional()->randomElement(GoldColorOptions::all()),
            'jwcad_3d' => fake()->optional()->bothify('JWC-####'),
            'image' => null,
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
     * Indicate that the variance is soft-deleted.
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
