<?php

namespace Database\Factories;

use App\Models\PolishFrame;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PolishFrame>
 */
class PolishFrameFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = fake()->randomFloat(2, 0.1, 50);
        $finish = fake()->randomFloat(2, 0.1, max(0.1, $start));

        return [
            'doc_no' => sprintf('PRK%07d', fake()->unique()->numberBetween(1, 9999999)),
            'date_from' => now()->subDay(),
            'spk_id' => null,
            'craftsman_id' => null,
            'date_to' => now(),
            'start_weight' => number_format($start, 2, '.', ''),
            'finish_weight' => number_format($finish, 2, '.', ''),
            'shrink' => number_format(max(0, $start - $finish), 2, '.', ''),
            'status_item' => 'OK',
            'send_craftsman_date' => now()->subDay(),
            'received_craftsman_date' => now(),
            'frame_id' => null,
            'notes' => fake()->optional()->sentence(),
            'status' => null,
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
            'status' => PolishFrame::STATUS_DONE,
        ]);
    }
}
