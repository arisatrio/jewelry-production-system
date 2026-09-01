<?php

namespace App\Support;

use App\Models\JewelCadRequest;
use Carbon\CarbonInterface;

class JewelCadDocNumberGenerator
{
    /**
     * Generate next JewelCAD doc number in format YYYY/JWC/00001.
     */
    public function generate(?CarbonInterface $at = null): string
    {
        $year = ($at ?? now())->format('Y');
        $prefix = "{$year}/JWC/";

        $latest = JewelCadRequest::query()
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
