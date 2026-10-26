<?php

namespace Database\Seeders;

use App\Support\MsChainMasterImporter;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class MsChainSeeder extends Seeder
{
    /**
     * Seed chain master from bundled CSV (idempotent).
     */
    public function run(): void
    {
        if (! Schema::connection('third')->hasTable('mschain')) {
            $this->command?->warn('Table mschain is not available; skipping chain master seed.');

            return;
        }

        $result = app(MsChainMasterImporter::class)->importDefault('seeder');

        $this->command?->info(sprintf(
            'MsChain seed: %d created, %d updated, %d skipped.',
            $result['created'],
            $result['updated'],
            $result['skipped'],
        ));
    }
}
