<?php

namespace App\Console\Commands;

use App\Support\ChainUnitNumberGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BackfillTrxChainUnitNumbersCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'trxchain:backfill-unit-numbers';

    /**
     * @var string
     */
    protected $description = 'Assign CHA#### unit numbers to trxchain rows that do not have one';

    public function handle(ChainUnitNumberGenerator $generator): int
    {
        if (! Schema::connection('third')->hasTable('trxchain')) {
            $this->error('Table trxchain does not exist.');

            return self::FAILURE;
        }

        if (! Schema::connection('third')->hasColumn('trxchain', 'unit_no')) {
            $this->error('Column unit_no does not exist. Run migrations first.');

            return self::FAILURE;
        }

        $assigned = DB::connection('third')->transaction(
            fn (): int => $generator->backfillMissing(),
        );

        $this->info("Assigned {$assigned} unit number(s).");

        return self::SUCCESS;
    }
}
