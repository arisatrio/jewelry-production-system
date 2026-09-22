<?php

namespace Database\Factories;

use App\Models\FinishingHandmade;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FinishingHandmade>
 */
class FinishingHandmadeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'doc_no' => sprintf('FIN%07d', fake()->unique()->numberBetween(1, 9999999)),
            'spk_id' => null,
            'process_name' => 'Finishing',
            'craftsman_id' => null,
            'start_weight' => number_format(fake()->randomFloat(3, 0.1, 50), 3, '.', ''),
            'finish_weight' => number_format(fake()->randomFloat(3, 0.1, 45), 3, '.', ''),
            'submit_materialgold' => number_format(fake()->randomFloat(3, 0, 5), 3, '.', ''),
            'result_materialgold' => number_format(fake()->randomFloat(3, 0, 5), 3, '.', ''),
            'shrink' => number_format(fake()->randomFloat(3, 0, 2), 3, '.', ''),
            'shrink_tolerance' => number_format(fake()->randomFloat(2, 0, 10), 2, '.', ''),
            'send_craftsman_date' => now()->subDay(),
            'received_craftsman_date' => now(),
            'item_category' => null,
            'notes' => fake()->optional()->sentence(),
            'status' => null,
            'is_from_new_system' => 0,
            'is_deleted' => 0,
            'created_date' => now(),
            'created_by' => 'system',
            'modified_date' => now(),
            'modified_by' => 'system',
            'deleted_date' => null,
            'deleted_by' => null,
            'koreksi_qc' => 0,
            'keterangan_qc' => null,
        ];
    }

    /**
     * Indicate that the finishing document is soft-deleted.
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
     * Indicate that the finishing document is done.
     */
    public function done(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => FinishingHandmade::STATUS_DONE,
        ]);
    }
}
