<?php

namespace Database\Factories;

use App\Models\Coran;
use App\Models\CoranSpk;
use App\Models\Production;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CoranSpk>
 */
class CoranSpkFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'row_id' => Coran::factory(),
            'spk_id' => Production::factory(),
            'weight' => number_format(fake()->randomFloat(3, 1, 20), 3, '.', ''),
            'status' => CoranSpk::STATUS_OK,
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
     * Indicate that the SPK line is not OK.
     */
    public function notOk(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => CoranSpk::STATUS_NOK,
        ]);
    }
}
