<?php

namespace Database\Factories;

use App\Models\Production;
use App\Models\Resin;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Resin>
 */
class ResinFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'doc_no' => sprintf('RES%07d', fake()->unique()->numberBetween(1, 9999999)),
            'trans_date' => fake()->date(),
            'spk_id' => Production::factory(),
            'file_upload' => null,
            'status' => Resin::STATUS_OPEN,
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
     * Indicate that the resin is soft-deleted.
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
     * Indicate that the resin is done.
     */
    public function done(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => Resin::STATUS_DONE,
        ]);
    }
}
