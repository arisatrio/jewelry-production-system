<?php

namespace App\Support;

use App\Models\SerahTerimaSpkDiamond;
use Carbon\CarbonInterface;

class SerahTerimaSpkDiamondDocNumberGenerator
{
    /**
     * Generate next serah terima SPK Pasang Batu doc number in format WHOJ/PRD/TTB/YYYY/0001.
     */
    public function generate(?CarbonInterface $at = null): string
    {
        $year = ($at ?? now())->format('Y');
        $prefix = "WHOJ/PRD/TTB/{$year}/";

        $latest = SerahTerimaSpkDiamond::query()
            ->withTrashed()
            ->where('doc_no', 'like', $prefix.'%')
            ->lockForUpdate()
            ->orderByDesc('doc_no')
            ->value('doc_no');

        $next = 1;

        if (is_string($latest) && preg_match('/\/(\d+)$/', $latest, $matches) === 1) {
            $next = (int) $matches[1] + 1;
        }

        return sprintf('%s%04d', $prefix, $next);
    }
}
