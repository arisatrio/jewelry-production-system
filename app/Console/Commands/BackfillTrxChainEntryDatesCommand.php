<?php

namespace App\Console\Commands;

use App\Models\TrxChain;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class BackfillTrxChainEntryDatesCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'trxchain:backfill-entry-dates';

    /**
     * @var string
     */
    protected $description = 'Set missing trxchain entry_date from out_date or created_date';

    public function handle(): int
    {
        if (! Schema::connection('third')->hasTable('trxchain')) {
            $this->error('Table trxchain does not exist.');

            return self::FAILURE;
        }

        $updated = 0;
        $now = now();

        TrxChain::query()
            ->notDeleted()
            ->whereNull('entry_date')
            ->orderBy('row_id')
            ->each(function (TrxChain $unit) use (&$updated, $now): void {
                $entryDate = $unit->out_date?->format('Y-m-d')
                    ?? $unit->created_date?->format('Y-m-d')
                    ?? $now->format('Y-m-d');

                $unit->update([
                    'entry_date' => $entryDate,
                    'modified_date' => $now,
                    'modified_by' => 'system',
                ]);

                $updated++;
            });

        $this->info("Updated {$updated} trxchain row(s) with entry_date.");

        return self::SUCCESS;
    }
}
