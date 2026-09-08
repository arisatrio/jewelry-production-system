<?php

namespace Database\Factories;

use App\Models\Production;
use App\Models\Resin;
use App\Support\ResinApprovalService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Resin>
 */
class ResinFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'doc_no' => sprintf('%s/RSN/%05d', now()->format('Y'), fake()->unique()->numberBetween(1, 99999)),
            'operator' => 'system',
            'trans_date' => fake()->date(),
            'spk_id' => Production::factory(),
            'file_upload' => null,
            'status' => 'DRAFT',
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
     * Indicate that the resin is soft-deleted.
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
     * Indicate that the resin is done.
     */
    public function done(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ResinApprovalService::STATUS_DONE,
        ]);
    }

    public function submitted(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ResinApprovalService::STATUS_SUBMITTED,
        ]);
    }

    public function managerApproved(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ResinApprovalService::STATUS_MANAGER,
        ]);
    }
}
