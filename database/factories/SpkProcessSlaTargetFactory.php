<?php

namespace Database\Factories;

use App\Models\SpkProcessSlaTarget;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SpkProcessSlaTarget>
 */
class SpkProcessSlaTargetFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'process_key' => fake()->unique()->randomElement([
                'JewelCAD',
                'Resin',
                'Coran',
                'Finishing',
                'Poles Rangka',
                'Pasang Batu',
                'Poles Chrome',
            ]),
            'working_days' => fake()->numberBetween(1, 10),
            'updated_by' => 'system',
        ];
    }
}
