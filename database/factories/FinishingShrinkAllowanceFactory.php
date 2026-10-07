<?php

namespace Database\Factories;

use App\Models\FinishingShrinkAllowance;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FinishingShrinkAllowance>
 */
class FinishingShrinkAllowanceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'work_category' => 'Finishing',
            'work_type' => 'Finishing 1',
            'item_category' => 'Barang Kecil',
            'allowance_percent' => '5.00',
            'updated_by' => 'system',
        ];
    }
}
