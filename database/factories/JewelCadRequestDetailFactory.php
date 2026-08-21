<?php

namespace Database\Factories;

use App\Models\JewelCadRequest;
use App\Models\JewelCadRequestDetail;
use App\Models\Production;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JewelCadRequestDetail>
 */
class JewelCadRequestDetailFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'row_id' => JewelCadRequest::factory(),
            'spk_id' => Production::factory(),
            'material' => fake()->randomElement(['White Gold', 'Yellow Gold', 'Rose Gold']),
            'qty' => fake()->numberBetween(1, 10),
            'estimation_brj' => number_format(fake()->randomFloat(2, 1, 50), 2, '.', ''),
            'notes' => fake()->optional()->randomElement(['42 CM', 'Ring size 17', 'Prioritas tinggi']),
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
}
