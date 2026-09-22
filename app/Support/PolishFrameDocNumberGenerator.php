<?php

namespace App\Support;

use App\Models\PolishFrame;

class PolishFrameDocNumberGenerator
{
    /**
     * Generate next Poles Rangka doc number in format PRK0000213.
     */
    public function generate(): string
    {
        $candidates = PolishFrame::query()
            ->notDeleted()
            ->where('doc_no', 'like', 'PRK%')
            ->lockForUpdate()
            ->orderByDesc('doc_no')
            ->limit(50)
            ->pluck('doc_no');

        $max = 0;

        foreach ($candidates as $docNo) {
            if (! is_string($docNo)) {
                continue;
            }

            if (preg_match('/^PRK(\d+)$/', $docNo, $matches) !== 1) {
                continue;
            }

            $max = max($max, (int) $matches[1]);
        }

        return sprintf('PRK%07d', $max + 1);
    }
}
