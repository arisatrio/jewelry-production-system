<?php

namespace Database\Factories;

use App\Models\SpkChain;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SpkChain>
 */
class SpkChainFactory extends Factory
{
    protected $model = SpkChain::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'row_id' => fake()->numberBetween(1, 9999),
            'category' => fake()->randomElement(['Rantai', 'Kalung', 'Gelang']),
            'item' => fake()->words(2, true),
            'color' => fake()->randomElement(['Yellow Gold', 'White Gold', 'Rose Gold']),
            'size' => (string) fake()->numberBetween(35, 50),
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
