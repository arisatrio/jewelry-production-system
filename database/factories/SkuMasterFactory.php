<?php

namespace Database\Factories;

use App\Models\SkuMaster;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SkuMaster>
 */
class SkuMasterFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $code = 'TST-'.Str::upper(Str::random(8));

        return [
            'sku_code' => $code,
            'item_original' => 'Test Item '.Str::upper(Str::random(6)),
            'name_prefix_id' => null,
            'category_prefix_id' => null,
            'gold_prefix_id' => null,
            'size_prefix_id' => null,
            'stone_shape_prefix_id' => null,
            'stone_type_prefix_id' => null,
            'diamond_type_prefix_id' => null,
            'crt' => '0',
            'gold_weight' => null,
            'sell_price' => null,
            'is_complete' => 1,
            'wildcard_count' => 0,
            'completeness_score' => 100,
            'catalog_image' => null,
            'image_url' => null,
            'image_filename' => null,
            'image_uploaded_at' => null,
            'source' => 'TEST',
            'is_active' => 1,
            'is_deleted' => 0,
            'metadata' => null,
            'label' => null,
            'created_by' => 'system',
            'modified_by' => 'system',
        ];
    }

    /**
     * Indicate that the SKU is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => 0,
        ]);
    }
}
