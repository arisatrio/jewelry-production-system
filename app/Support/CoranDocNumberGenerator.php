<?php

namespace App\Support;

use App\Models\Coran;

class CoranDocNumberGenerator
{
    /**
     * Generate next coran doc number in format COR0000213.
     */
    public function generate(): string
    {
        $candidates = Coran::query()
            ->notDeleted()
            ->where('doc_no', 'like', 'COR%')
            ->lockForUpdate()
            ->orderByDesc('doc_no')
            ->limit(50)
            ->pluck('doc_no');

        $max = 0;

        foreach ($candidates as $docNo) {
            if (! is_string($docNo)) {
                continue;
            }

            if (preg_match('/^COR(\d+)$/', $docNo, $matches) !== 1) {
                continue;
            }

            $max = max($max, (int) $matches[1]);
        }

        return sprintf('COR%07d', $max + 1);
    }
}
