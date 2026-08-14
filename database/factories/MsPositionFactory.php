<?php

namespace Database\Factories;

use App\Models\MsPosition;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MsPosition>
 */
class MsPositionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nama' => 'Posisi '.Str::upper(Str::random(6)),
        ];
    }
}
