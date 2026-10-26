<?php

namespace Database\Factories;

use App\Models\SerahTerimaChain;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SerahTerimaChain>
 */
class SerahTerimaChainFactory extends Factory
{
    protected $model = SerahTerimaChain::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $spkRowId = fake()->unique()->numberBetween(1, 999999);
        $lineId = fake()->unique()->numberBetween(1, 999999);

        return [
            'doc_no' => sprintf('WHOJ/PRD/TTR/TEST/%06d', fake()->unique()->numberBetween(1, 999999)),
            'tanggal' => now()->toDateString(),
            'dari' => 'Head Office',
            'untuk' => 'Workshop',
            'diserahkan_oleh' => fake()->name(),
            'diterima_oleh' => fake()->name(),
            'diketahui_oleh' => fake()->name(),
            'spk_row_id' => $spkRowId,
            'jumlah_baris' => 1,
            'spk_chain_line_ids' => [$lineId],
            'items' => [[
                'spkChainLineId' => $lineId,
                'spkNo' => now()->format('Y').'/PRD/'.fake()->numerify('#####'),
                'category' => 'Bracelet Chain',
                'item' => fake()->words(2, true),
                'color' => 'Rose Gold',
                'size' => '45 cm',
                'unitNo' => 'CH-0001',
                'chainLength' => '45.00',
                'weight' => '12.50',
                'customer' => 'Test Customer',
            ]],
            'created_by' => 'system',
        ];
    }
}
