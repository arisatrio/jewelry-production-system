<?php

namespace Database\Factories;

use App\Models\MsComponent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MsComponent>
 */
class MsComponentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $itemCode = 'KMP'.fake()->unique()->numerify('###');

        return [
            'component_category' => MsComponent::CATEGORY_KOMPONEN,
            'item' => 'Komponen Classic',
            'item_code' => $itemCode,
            'item_name' => "Komponen Classic - {$itemCode}",
            'description' => 'Test component master',
            'is_deleted' => 0,
            'created_date' => now(),
            'created_by' => 'system',
            'modified_date' => now(),
            'modified_by' => 'system',
            'deleted_date' => null,
            'deleted_by' => null,
        ];
    }

    public function deleted(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_deleted' => 1,
            'deleted_date' => now(),
            'deleted_by' => 'system',
        ]);
    }
}
