<?php

namespace Database\Factories;

use App\Models\SerahTerimaComponent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SerahTerimaComponent>
 */
class SerahTerimaComponentFactory extends Factory
{
    protected $model = SerahTerimaComponent::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $spkRowId = fake()->unique()->numberBetween(1, 999999);
        $lineId = fake()->unique()->numberBetween(1, 999999);

        return [
            'doc_no' => sprintf('WHOJ/PRD/TTK/TEST/%06d', fake()->unique()->numberBetween(1, 999999)),
            'tanggal' => now()->toDateString(),
            'dari' => 'Head Office',
            'untuk' => 'Workshop',
            'diserahkan_oleh' => fake()->name(),
            'diterima_oleh' => fake()->name(),
            'diketahui_oleh' => fake()->name(),
            'spk_row_id' => $spkRowId,
            'jumlah_baris' => 1,
            'spk_component_line_ids' => [$lineId],
            'items' => [[
                'spkComponentLineId' => $lineId,
                'spkNo' => now()->format('Y').'/PRD/'.fake()->numerify('#####'),
                'category' => 'Komponen',
                'item' => fake()->words(2, true),
                'color' => 'Rose Gold',
                'unitNo' => 'KOM0001',
                'componentSize' => '12.5',
                'weight' => '1.50',
                'customer' => 'Test Customer',
            ]],
            'created_by' => 'system',
        ];
    }
}
