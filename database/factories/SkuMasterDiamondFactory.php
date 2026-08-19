<?php

namespace Database\Factories;

use App\Models\SkuMaster;
use App\Models\SkuMasterDiamond;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SkuMasterDiamond>
 */
class SkuMasterDiamondFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'row_id' => SkuMaster::factory(),
            'grain' => fake()->numberBetween(1, 24),
            'grade' => number_format(fake()->randomFloat(3, 0.010, 1.500), 3, '.', ''),
            'diamond_type' => fake()->randomElement(['R', 'OV', 'PS', 'EM', 'MQ']),
            'no_sert' => null,
            'diameter' => null,
            'position' => null,
            'color' => null,
            'is_gia' => null,
            'is_deleted' => 0,
            'created_date' => now(),
            'created_by' => 'system',
            'modified_date' => now(),
            'modified_by' => 'system',
        ];
    }
}
