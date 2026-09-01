<?php

namespace Database\Factories;

use App\Models\Production;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Production>
 */
class ProductionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $orderDate = fake()->dateTimeBetween('-3 months', 'now');

        return [
            'spk_no' => sprintf(
                '%s/PRD/%05d',
                $orderDate->format('Y'),
                fake()->unique()->numberBetween(1, 99999),
            ),
            'spk_type' => fake()->randomElement(['Stock', 'Pesanan', 'Refund']),
            'request_order_no' => null,
            'ref_spk_id' => null,
            'customer_name' => fake()->optional(0.6)->name(),
            'item_name' => fake()->randomElement(['Bangle', 'Pendant', 'Ladies Ring', 'Necklace', 'Earring']),
            'description' => fake()->optional()->sentence(),
            'order_date' => $orderDate->format('Y-m-d'),
            'work_estimated' => fake()->numberBetween(1, 30),
            'estimated_delivery_time' => fake()->dateTimeBetween($orderDate, '+2 months')->format('Y-m-d'),
            'supplier_id' => null,
            'status_order' => fake()->randomElement(['RO', 'PPIC', 'PRODUCTION']),
            'jwcad_3d' => null,
            'item_id' => null,
            'qty' => fake()->numberBetween(1, 10),
            'satuan' => fake()->randomElement(['Pcs', 'Pasang', 'Setengah Pasang']),
            'diameter_length_ringsize' => null,
            'gold_weight' => fake()->randomFloat(2, 1, 50),
            'gold_color' => fake()->optional()->randomElement(['Yellow', 'White', 'Rose']),
            'gold_content' => fake()->optional()->randomElement(['75%', '92.5%']),
            'priority' => fake()->optional()->randomElement(['Low', 'Normal', 'High']),
            'item_type_id' => null,
            'notes' => fake()->optional()->sentence(),
            'status' => fake()->randomElement(['SPK010', 'SPKDONE', '0']),
            'last_weight' => null,
            'frame_id' => null,
            'file_name' => null,
            'last_process' => fake()->optional()->randomElement(['Pasang Batu', 'Finishing', 'Coran']),
            'is_coran' => 0,
            'is_finishinghandmade' => 0,
            'is_polishframe' => 0,
            'is_diamondmounting' => 0,
            'is_polishfinishedgood' => 0,
            'is_grafir' => 0,
            'is_inprocess' => 0,
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
     * Indicate that the production record is soft-deleted.
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
