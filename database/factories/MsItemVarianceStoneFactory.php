<?php

namespace Database\Factories;

use App\Models\MsItemVariance;
use App\Models\MsItemVarianceStone;
use App\Models\MsShape;
use App\Support\MsItemVarianceStoneCalculator;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MsItemVarianceStone>
 */
class MsItemVarianceStoneFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $pcs = fake()->numberBetween(1, 20);
        $caratPerPcs = fake()->randomFloat(3, 0.001, 2);

        return [
            'item_variance_id' => MsItemVariance::factory(),
            'shape_id' => MsShape::query()->notDeleted()->inRandomOrder()->value('row_id'),
            'position_id' => null,
            'pcs' => $pcs,
            'carat_per_pcs' => $caratPerPcs,
            'total_carat' => MsItemVarianceStoneCalculator::totalCarat($pcs, $caratPerPcs),
            'size' => fake()->optional()->passthrough(
                number_format(fake()->randomFloat(2, 0.5, 10), 2, '.', ''),
            ),
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
     * Indicate that the stone is soft-deleted.
     */
    public function deleted(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_deleted' => 1,
            'deleted_date' => now(),
            'deleted_by' => 'system',
        ]);
    }
}
