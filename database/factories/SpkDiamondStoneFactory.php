<?php

namespace Database\Factories;

use App\Models\MsStone;
use App\Models\SpkDiamond;
use App\Models\SpkDiamondStone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SpkDiamondStone>
 */
class SpkDiamondStoneFactory extends Factory
{
    protected $model = SpkDiamondStone::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'spk_diamond_id' => SpkDiamond::factory(),
            'stone_id' => MsStone::factory(),
            'pcs' => fake()->numberBetween(1, 50),
            'crt' => fake()->randomFloat(3, 0.1, 2),
            'notes' => null,
            'is_deleted' => 0,
            'created_date' => now(),
            'created_by' => 'factory',
            'modified_date' => now(),
            'modified_by' => 'factory',
        ];
    }
}
