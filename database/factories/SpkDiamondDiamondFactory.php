<?php

namespace Database\Factories;

use App\Models\SpkDiamond;
use App\Models\SpkDiamondDiamond;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SpkDiamondDiamond>
 */
class SpkDiamondDiamondFactory extends Factory
{
    protected $model = SpkDiamondDiamond::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'spk_diamond_id' => SpkDiamond::factory(),
            'trdiamond_id' => 1,
            'is_deleted' => 0,
            'created_date' => now(),
            'created_by' => 'factory',
        ];
    }
}
