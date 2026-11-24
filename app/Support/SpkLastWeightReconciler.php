<?php

namespace App\Support;

use App\Models\Production;
use Illuminate\Support\Facades\Schema;

class SpkLastWeightReconciler
{
    public function __construct(
        private readonly SpkShrinkSummary $shrinkSummary = new SpkShrinkSummary,
    ) {}

    /**
     * Salin berat akhir proses terakhir (selain Cor) ke {@see Production::$last_weight}.
     */
    public function reconcile(Production $production, string $actor = 'system'): bool
    {
        if (! Schema::connection('third')->hasColumn('spk', 'last_weight')) {
            return false;
        }

        $rows = $this->shrinkSummary->forProduction($production)['rows'];
        $latestEnd = null;

        foreach (array_reverse($rows) as $row) {
            if (($row['process'] ?? '') === 'Cor') {
                continue;
            }

            $end = $row['endWeight'] ?? null;

            if ($end === null || $end === '') {
                continue;
            }

            $latestEnd = (float) $end;

            break;
        }

        if ($latestEnd === null) {
            return false;
        }

        $current = filled($production->last_weight) ? round((float) $production->last_weight, 2) : null;

        if ($current !== null && abs($current - $latestEnd) < 0.005) {
            return false;
        }

        $production->update([
            'last_weight' => number_format($latestEnd, 2, '.', ''),
            'modified_date' => now(),
            'modified_by' => $actor,
        ]);

        return true;
    }
}
