<?php

namespace Database\Factories;

use App\Models\StokRangka;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<StokRangka>
 */
class StokRangkaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $token = Str::upper(Str::random(8));

        return [
            'doc_no' => 'RK-TEST-'.$token,
            'sku_id' => (string) fake()->numberBetween(900, 9999),
            'category' => fake()->randomElement(['RING', 'EARRING', 'PENDANT']),
            'item_name' => 'Test Frame '.$token,
            'variant' => 'Test Collection',
            'prd' => null,
            'date_in' => now()->toDateString(),
            'date_out' => null,
            'gold_weight' => fake()->randomFloat(2, 1, 10),
            'gold_color' => fake()->randomElement(['Yellow Gold', 'White Gold', 'Rose Gold']),
            'status' => 'READY',
            'is_deleted' => 0,
            'created_by' => 'system',
            'updated_by' => 'system',
        ];
    }

    public function deleted(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_deleted' => 1,
        ]);
    }
}
