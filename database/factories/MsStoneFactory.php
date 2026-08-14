<?php

namespace Database\Factories;

use App\Models\MsShape;
use App\Models\MsStone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MsStone>
 */
class MsStoneFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $parcel = 'T'.fake()->unique()->numerify('##');
        $size = fake()->randomFloat(2, 0.5, 5);

        return [
            'parcel' => $parcel,
            'stone_size' => (string) $size,
            'crt' => fake()->randomFloat(3, 0.001, 2),
            'shape_id' => MsShape::query()->notDeleted()->inRandomOrder()->value('row_id'),
            'name' => 'Test '.$parcel.' '.$size.' MM',
            'mounting_rate' => fake()->optional()->randomFloat(2, 1, 100),
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
