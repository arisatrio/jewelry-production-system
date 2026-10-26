<?php

namespace Database\Factories;

use App\Models\MsChain;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MsChain>
 */
class MsChainFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $itemCode = 'CDOV'.fake()->unique()->numerify('###');
        $length = fake()->randomElement(['16.00', '18.00', '40.00', '45.00']);

        return [
            'chain_category' => MsChain::CATEGORY_PENDANT,
            'item' => 'Chain Classic',
            'item_code' => $itemCode,
            'length' => $length,
            'item_name' => "Chain Classic - {$itemCode} - {$length} cm",
            'description' => 'Test chain master',
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
