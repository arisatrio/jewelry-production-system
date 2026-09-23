<?php

namespace App\Support;

use App\Models\PolishFinishedGood;

class PolishFinishedGoodDocNumberGenerator
{
    /**
     * Generate next Poles Chrome doc number in format PFG0000213.
     */
    public function generate(): string
    {
        $candidates = PolishFinishedGood::query()
            ->notDeleted()
            ->where('doc_no', 'like', 'PFG%')
            ->lockForUpdate()
            ->orderByDesc('doc_no')
            ->limit(50)
            ->pluck('doc_no');

        $max = 0;

        foreach ($candidates as $docNo) {
            if (! is_string($docNo)) {
                continue;
            }

            if (preg_match('/^PFG(\d+)$/', $docNo, $matches) !== 1) {
                continue;
            }

            $max = max($max, (int) $matches[1]);
        }

        return sprintf('PFG%07d', $max + 1);
    }
}
