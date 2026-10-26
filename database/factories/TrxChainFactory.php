<?php

namespace Database\Factories;

use App\Models\MsChain;
use App\Models\TrxChain;
use App\Support\ChainUnitNumberGenerator;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrxChain>
 */
class TrxChainFactory extends Factory
{
    protected $model = TrxChain::class;

    public function configure(): static
    {
        return $this->afterCreating(function (TrxChain $unit): void {
            if ($unit->unit_no !== null && $unit->unit_no !== '') {
                return;
            }

            $unit->update([
                'unit_no' => app(ChainUnitNumberGenerator::class)->generate(),
            ]);
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'mschain_id' => MsChain::factory(),
            'source_type' => TrxChain::SOURCE_PURCHASE,
            'parent_row_id' => null,
            'name' => 'Test Chain Unit',
            'item_code' => 'TEST-'.fake()->unique()->numerify('###'),
            'chain_length' => '45.00',
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
