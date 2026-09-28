<?php

namespace App\Support;

use App\Models\PolishFinishedGood;

class PolishFinishedGoodDocNumberGenerator
{
    /**
     * Legacy prefix shared with the old system; planned to move to PFG.
     */
    public const PREFIX = 'COR';

    /**
     * Generate next Poles Chrome doc number in format COR0007880.
     */
    public function generate(): string
    {
        $candidates = PolishFinishedGood::query()
            ->notDeleted()
            ->where('doc_no', 'like', self::PREFIX.'%')
            ->lockForUpdate()
            ->orderByDesc('doc_no')
            ->limit(50)
            ->pluck('doc_no');

        $max = 0;

        foreach ($candidates as $docNo) {
            if (! is_string($docNo)) {
                continue;
            }

            if (preg_match('/^'.self::PREFIX.'(\d+)$/', $docNo, $matches) !== 1) {
                continue;
            }

            $max = max($max, (int) $matches[1]);
        }

        return sprintf('%s%07d', self::PREFIX, $max + 1);
    }
}
