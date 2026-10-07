<?php

namespace Database\Seeders;

use App\Support\FinishingNotesWorkTypeBackfill;
use Illuminate\Database\Seeder;

class FinishingWorkTypeBackfillSeeder extends Seeder
{
    /**
     * Isi jenis pekerjaan finishing lama dari catatan, lalu simpan toleransi susut.
     */
    public function run(FinishingNotesWorkTypeBackfill $backfill): void
    {
        $updated = $backfill->run();

        $this->command?->info("{$updated} dokumen finishing diisi dari catatan.");
    }
}
