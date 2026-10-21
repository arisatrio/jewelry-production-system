<?php

namespace Database\Factories;

use App\Models\Production;
use App\Models\SpkDiamond;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SpkDiamond>
 */
class SpkDiamondFactory extends Factory
{
    protected $model = SpkDiamond::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'no_spk_diamond' => 'MK-DM.'.fake()->unique()->numerify('#####'),
            'spk_id' => Production::factory(),
            'spk_no' => null,
            'dmd_no' => null,
            'tanggal' => now()->toDateString(),
            'weight_frame' => fake()->randomFloat(2, 1, 20),
            'weight_stone' => fake()->randomFloat(3, 0.1, 5),
            'weight_total' => null,
            'keterangan' => fake()->optional()->sentence(),
            'status' => SpkDiamond::STATUS_DRAFT,
            'is_deleted' => 0,
            'created_date' => now(),
            'created_by' => 'factory',
            'modified_date' => now(),
            'modified_by' => 'factory',
        ];
    }

    public function submitted(): static
    {
        return $this->state(fn (): array => [
            'status' => SpkDiamond::STATUS_SUBMITTED,
            'submitted_at' => now(),
            'submitted_by' => 'factory',
        ]);
    }
}
