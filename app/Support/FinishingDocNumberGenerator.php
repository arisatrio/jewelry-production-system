<?php

namespace App\Support;

use App\Models\FinishingHandmade;

class FinishingDocNumberGenerator
{
    /**
     * Generate next finishing doc number in format FIN0000213.
     */
    public function generate(): string
    {
        $candidates = FinishingHandmade::query()
            ->notDeleted()
            ->where('doc_no', 'like', 'FIN%')
            ->lockForUpdate()
            ->orderByDesc('doc_no')
            ->limit(50)
            ->pluck('doc_no');

        $max = 0;

        foreach ($candidates as $docNo) {
            if (! is_string($docNo)) {
                continue;
            }

            if (preg_match('/^FIN(\d+)$/', $docNo, $matches) !== 1) {
                continue;
            }

            $max = max($max, (int) $matches[1]);
        }

        return sprintf('FIN%07d', $max + 1);
    }
}
