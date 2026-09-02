<?php

namespace Database\Factories;

use App\Models\Production;
use App\Models\Resin;
use App\Models\ResinDetail;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ResinDetail>
 */
class ResinDetailFactory extends Factory
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
            'spk_id' => Production::factory(),
            'berat_resin' => number_format(fake()->randomFloat(3, 1, 20), 3, '.', ''),
            'status_resin' => null,
            'catatan' => fake()->optional()->sentence(),
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
     * Indicate that the detail is soft-deleted.
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
     * Indicate that the detail is done.
     */
    public function done(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status_resin' => ResinDetail::STATUS_OK,
        ]);
    }
}
