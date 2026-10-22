<?php

namespace Database\Factories;

use App\Models\SpkComponent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SpkComponent>
 */
class SpkComponentFactory extends Factory
{
    protected $model = SpkComponent::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'row_id' => fake()->numberBetween(1, 9999),
            'category' => fake()->randomElement(['Komponen', 'Stopper']),
            'item' => fake()->words(2, true),
            'color' => fake()->randomElement(['Yellow Gold', 'White Gold', 'Rose Gold']),
            'size' => (string) fake()->numberBetween(1, 20),
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
