<?php

namespace Database\Factories;

use App\Models\JewelCadRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JewelCadRequest>
 */
class JewelCadRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'doc_no' => sprintf('%s/JWC/%05d', now()->format('Y'), fake()->unique()->numberBetween(1, 99999)),
            'operator' => 'system',
            'trans_date' => fake()->date(),
            'notes' => fake()->optional()->sentence(),
            'status' => fake()->randomElement(['DRAFT', 'OPEN', 'JWDDONE']),
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
     * Indicate that the request is soft-deleted.
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
