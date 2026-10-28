<?php

namespace Database\Factories;

use App\Models\MsComponent;
use App\Models\TrxComponent;
use App\Support\ComponentUnitNumberGenerator;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrxComponent>
 */
class TrxComponentFactory extends Factory
{
    protected $model = TrxComponent::class;

    public function configure(): static
    {
        return $this->afterCreating(function (TrxComponent $unit): void {
            if ($unit->unit_no !== null && $unit->unit_no !== '') {
                return;
            }

            $unit->update([
                'unit_no' => app(ComponentUnitNumberGenerator::class)->generate(),
            ]);
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'mscomponent_id' => MsComponent::factory(),
            'source_type' => TrxComponent::SOURCE_PURCHASE,
            'parent_row_id' => null,
            'name' => 'Test Komponen Unit',
            'item_code' => 'TEST-'.fake()->unique()->numerify('###'),
            'component_size' => '45.00',
            'color' => 'Rose Gold',
            'weight' => '1.50',
            'supplier' => fake()->company(),
            'purchase_price' => fake()->randomFloat(2, 25, 850),
            'entry_date' => now()->toDateString(),
            'out_date' => null,
            'spk_id' => null,
            'is_used' => 0,
            'notes' => null,
            'is_deleted' => 0,
            'created_date' => now(),
            'created_by' => 'system',
            'modified_date' => now(),
            'modified_by' => 'system',
            'deleted_date' => null,
            'deleted_by' => null,
        ];
    }

    public function issued(): static
    {
        return $this->state(fn (array $attributes): array => [
            'out_date' => now()->toDateString(),
            'is_used' => 1,
        ]);
    }
}
