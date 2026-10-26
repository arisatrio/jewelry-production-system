<?php

namespace App\Console\Commands;

use App\Support\MsChainMasterImporter;
use App\Support\TrxChainStockImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class ImportChainDataCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'chain:import
                            {--master= : Path to mschain master CSV (default: database/data/mschain_master_detail_ukuran.csv)}
                            {--stock= : Path to trxchain stock CSV (default: database/data/STOCK CHAIN  26 - Detail Transaksi Rantai (1).csv)}
                            {--actor=system : Value for created_by / modified_by}
                            {--replace : Soft-delete previous stock rows imported before loading stock CSV}
                            {--only=all : Import master, stock, or both (all)}';

    /**
     * @var string
     */
    protected $description = 'Import mschain master and trxchain stock from CSV (stock sheet includes optional Supplier column)';

    public function handle(
        MsChainMasterImporter $masterImporter,
        TrxChainStockImporter $stockImporter,
    ): int {
        $only = strtolower((string) $this->option('only'));

        if (! in_array($only, ['all', 'master', 'stock'], true)) {
            $this->error('Option --only must be all, master, or stock.');

            return self::FAILURE;
        }

        $actor = (string) $this->option('actor');
        $runMaster = $only === 'all' || $only === 'master';
        $runStock = $only === 'all' || $only === 'stock';

        if ($runMaster) {
            if (! Schema::connection('third')->hasTable('mschain')) {
                $this->error('Table mschain does not exist. Run migrations first.');

                return self::FAILURE;
            }

            $masterPath = $this->option('master')
                ?? database_path(MsChainMasterImporter::DEFAULT_CSV_PATH);

            $this->info("Importing mschain master from {$masterPath}…");

            $result = $masterImporter->importFromPath($masterPath, $actor);

            $this->info(sprintf(
                'Master import complete: %d created, %d updated, %d skipped.',
                $result['created'],
                $result['updated'],
                $result['skipped'],
            ));
        }

        if ($runStock) {
            if (! Schema::connection('third')->hasTable('trxchain')) {
                $this->error('Table trxchain does not exist. Run migrations first.');

                return self::FAILURE;
            }

            if (! Schema::connection('third')->hasTable('mschain')) {
                $this->error('Table mschain does not exist. Run migrations first.');

                return self::FAILURE;
            }

            $stockPath = $this->option('stock')
                ?? database_path(TrxChainStockImporter::DEFAULT_CSV_PATH);

            $this->info("Importing trxchain stock from {$stockPath}…");

            $result = $stockImporter->importFromPath(
                $stockPath,
                $actor,
                (bool) $this->option('replace'),
            );

            $this->info(sprintf(
                'Stock import complete: %d created, %d skipped, %d missing master, %d rows with unknown PRD, %d purged.',
                $result['created'],
                $result['skipped'],
                $result['missing_master'],
                $result['missing_spk'],
                $result['purged'],
            ));
        }

        return self::SUCCESS;
    }
}
