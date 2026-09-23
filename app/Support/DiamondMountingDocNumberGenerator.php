<?php

namespace App\Support;

use App\Models\DiamondMounting;

class DiamondMountingDocNumberGenerator
{
    /**
     * Generate next Pasang Batu doc number in format DMD0005173.
     */
    public function generate(): string
    {
        $candidates = DiamondMounting::query()
            ->notDeleted()
            ->where('doc_no', 'like', 'DMD%')
            ->lockForUpdate()
            ->orderByDesc('doc_no')
            ->limit(50)
            ->pluck('doc_no');

        $max = 0;

        foreach ($candidates as $docNo) {
            if (! is_string($docNo)) {
                continue;
            }

            if (preg_match('/^DMD(\d+)$/', $docNo, $matches) !== 1) {
                continue;
            }

            $max = max($max, (int) $matches[1]);
        }

        return sprintf('DMD%07d', $max + 1);
    }
}
