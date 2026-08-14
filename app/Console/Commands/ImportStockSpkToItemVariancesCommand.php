<?php

namespace App\Console\Commands;

use App\Support\ImportStockSpkToItemVariance;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('spk:import-stock-variances {--dry-run : Hitung eligible tanpa menulis data} {--limit= : Batasi jumlah SPK yang diproses}')]
#[Description('Import SPK Stock 2026 ke master product varian beserta list batu, lalu link item_variance_id')]
class ImportStockSpkToItemVariancesCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(ImportStockSpkToItemVariance $importer): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $limitOption = $this->option('limit');
        $limit = filled($limitOption) ? (int) $limitOption : null;

        if ($limit !== null && $limit < 1) {
            $this->error('Opsi --limit harus berupa bilangan positif.');

            return self::FAILURE;
        }

        $eligible = $importer->eligibleQuery()->count();

        $this->info(sprintf(
            'Eligible SPK Stock %d: %d%s%s',
            ImportStockSpkToItemVariance::YEAR,
            $eligible,
            $limit !== null ? " (limit {$limit})" : '',
            $dryRun ? ' [dry-run]' : '',
        ));

        $result = $importer->handle($dryRun, $limit);

        $this->table(
            ['Metric', 'Count'],
            [
                ['created', (string) $result['created']],
                ['linked', (string) $result['linked']],
                ['skipped', (string) $result['skipped']],
                ['errors', (string) count($result['errors'])],
            ],
        );

        foreach ($result['errors'] as $error) {
            $this->error(sprintf(
                '%s: %s',
                $error['spk_no'] ?? '(tanpa spk_no)',
                $error['message'],
            ));
        }

        return count($result['errors']) > 0 ? self::FAILURE : self::SUCCESS;
    }
}
