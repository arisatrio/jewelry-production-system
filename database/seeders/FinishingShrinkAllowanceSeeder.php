<?php

namespace Database\Seeders;

use App\Models\FinishingShrinkAllowance;
use App\Support\FinishingShrinkAllowanceSettings;
use Illuminate\Database\Seeder;

class FinishingShrinkAllowanceSeeder extends Seeder
{
    /**
     * Isi matrix jatah susut bawaan bila tabel masih kosong.
     */
    public function run(): void
    {
        if (FinishingShrinkAllowance::query()->exists()) {
            return;
        }

        $settings = app(FinishingShrinkAllowanceSettings::class);

        $settings->sync($settings->formCells(), 'system');
    }
}
