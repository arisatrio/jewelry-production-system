<?php

namespace App\Support;

use App\Models\Resin;
use Carbon\CarbonInterface;

class ResinDocNumberGenerator
{
    /**
     * Generate next resin doc number in format YYYY/RSN/00001.
     */
    public function generate(?CarbonInterface $at = null): string
    {
        $year = ($at ?? now())->format('Y');
        $prefix = "{$year}/RSN/";

        $latest = Resin::query()
            ->notDeleted()
            ->where('doc_no', 'like', $prefix.'%')
            ->lockForUpdate()
            ->orderByDesc('doc_no')
            ->value('doc_no');

        $next = 1;

        if (is_string($latest) && preg_match('/\/(\d+)$/', $latest, $matches) === 1) {
            $next = (int) $matches[1] + 1;
        }

        return sprintf('%s%05d', $prefix, $next);
    }
}
