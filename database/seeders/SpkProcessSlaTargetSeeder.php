<?php

namespace Database\Seeders;

use App\Support\SpkProcessSlaResolver;
use Illuminate\Database\Seeder;

class SpkProcessSlaTargetSeeder extends Seeder
{
    /**
     * Seed default SLA targets dari config/spk_processes.php (idempotent).
     */
    public function run(): void
    {
        /** @var array<string, int|string> $defaults */
        $defaults = config('spk_processes.sla_defaults', []);

        $targets = [];

        foreach ($defaults as $processKey => $workingDays) {
            $targets[(string) $processKey] = (int) $workingDays;
        }

        app(SpkProcessSlaResolver::class)->sync($targets, 'system');
    }
}
