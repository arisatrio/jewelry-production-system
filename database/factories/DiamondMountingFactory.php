<?php

namespace Database\Factories;

use App\Models\DiamondMounting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DiamondMounting>
 */
class DiamondMountingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $weightFrame = fake()->randomFloat(2, 0.1, 50);
        $weightDiamond = fake()->randomFloat(3, 0.001, 2);
        $total = round($weightFrame + $weightDiamond, 2);
        $finish = fake()->randomFloat(2, 0.1, max(0.1, $total));

        return [
            'doc_no' => sprintf('DMD%07d', fake()->unique()->numberBetween(1, 9999999)),
            'trans_date' => now()->toDateString(),
            'process_name' => 'Pasang Batu',
            'spk_id' => null,
            'weight_frame' => number_format($weightFrame, 2, '.', ''),
            'weight_diamond' => number_format($weightDiamond, 3, '.', ''),
            'total_weigth_frame_diamond' => number_format($total, 2, '.', ''),
            'mounting_return' => null,
            'mounting_shrink' => number_format(max(0, $total - $finish), 2, '.', ''),
            'weight_finish_goods' => number_format($finish, 2, '.', ''),
            'polish_shrink' => null,
            'craftman_id' => null,
            'setting_id' => null,
            'qc_id' => null,
            'notes' => fake()->optional()->sentence(),
            'status' => null,
            'send_craftsman_date' => now()->subDay(),
            'received_craftsman_date' => now(),
            'is_from_new_system' => 0,
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
     * Indicate that the document is soft-deleted.
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
     * Indicate that the document is done.
     */
    public function done(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => DiamondMounting::STATUS_DONE,
        ]);
    }
}
