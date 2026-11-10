<?php

namespace App\Support;

use App\Models\SerahTerimaSpkDiamond;
use Carbon\CarbonInterface;

class SerahTerimaSpkDiamondDocNumberGenerator
{
    /**
     * Format: FAKTUR/{dari}-{untuk}/PBT/{MMYYYY}-{running per bulan}.
     */
    public function generate(
        ?CarbonInterface $at = null,
        ?string $fromLocation = null,
        ?string $toLocation = null,
    ): string {
        $date = $at ?? now();
        $period = $date->format('mY');
        $fromCode = $this->locationCode($fromLocation);
        $toCode = $this->locationCode($toLocation);
        $prefix = "FAKTUR/{$fromCode}-{$toCode}/PBT/{$period}-";

        $latest = SerahTerimaSpkDiamond::query()
            ->withTrashed()
            ->where('doc_no', 'like', $prefix.'%')
            ->lockForUpdate()
            ->orderByDesc('doc_no')
            ->value('doc_no');

        $next = 1;

        if (is_string($latest) && preg_match('/-(\d+)$/', $latest, $matches) === 1) {
            $next = (int) $matches[1] + 1;
        }

        return sprintf('%s%04d', $prefix, $next);
    }

    private function locationCode(?string $location): string
    {
        $normalized = trim((string) $location);

        return match ($normalized) {
            'Head Office' => 'HO',
            'Workshop' => 'WO',
            'Store' => 'ST',
            default => 'XX',
        };
    }
}
