<?php

namespace Database\Factories;

use App\Models\SerahTerimaSpkDiamond;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SerahTerimaSpkDiamond>
 */
class SerahTerimaSpkDiamondFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $rowId = fake()->unique()->numberBetween(1, 999999);

        return [
            'doc_no' => sprintf('WHOJ/PRD/TTB/TEST/%06d', fake()->unique()->numberBetween(1, 999999)),
            'tanggal' => now()->toDateString(),
            'dari' => 'Head Office',
            'untuk' => 'Workshop',
            'diserahkan_oleh' => fake()->name(),
            'diterima_oleh' => fake()->name(),
            'diketahui_oleh' => fake()->name(),
            'jumlah_dokumen' => 1,
            'spk_diamond_row_ids' => [$rowId],
            'items' => [[
                'spkDiamondRowId' => $rowId,
                'docNo' => 'TEST/SPK-DIAMOND/001',
                'spkNo' => now()->format('Y').'/PRD/'.fake()->numerify('#####'),
                'item' => fake()->words(3, true),
                'lineCount' => 2,
                'tanggal' => now()->format('d-M-Y'),
            ]],
            'created_by' => 'system',
        ];
    }
}
