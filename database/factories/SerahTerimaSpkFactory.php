<?php

namespace Database\Factories;

use App\Models\SerahTerimaSpk;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SerahTerimaSpk>
 */
class SerahTerimaSpkFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $spkRowId = fake()->unique()->numberBetween(1, 999999);

        return [
            'doc_no' => sprintf('WHOJ/PRD/TTS/TEST/%06d', fake()->unique()->numberBetween(1, 999999)),
            'tanggal' => now()->toDateString(),
            'dari' => 'Head Office',
            'untuk' => 'Workshop',
            'diserahkan_oleh' => fake()->name(),
            'diterima_oleh' => fake()->name(),
            'diketahui_oleh' => fake()->name(),
            'jumlah_spk' => 1,
            'spk_row_ids' => [$spkRowId],
            'items' => [[
                'spkRowId' => $spkRowId,
                'spkNo' => now()->format('Y').'/PRD/'.fake()->numerify('#####'),
                'type' => 'Stock',
                'item' => fake()->words(3, true),
                'description' => '',
                'customer' => '',
                'targetDate' => now()->addWeek()->format('d-M-Y'),
            ]],
            'created_by' => 'system',
        ];
    }
}
