<?php

namespace App\Support;

use App\Models\SerahTerimaFakturBarangJadi;
use Carbon\CarbonInterface;

class SerahTerimaFakturBarangJadiDocNumberGenerator
{
    /**
     * Format: FAKTUR/{dari}-{untuk}/BRJ/{MONTH}{YY}/{running per bulan, 4 digit}.
     *
     * Example: FAKTUR/WO-HO/BRJ/NOVEMBER26/0002
     */
    public function generate(
        ?CarbonInterface $at = null,
        ?string $fromLocation = null,
        ?string $toLocation = null,
    ): string {
        $date = $at ?? now();
        $period = strtoupper($date->format('F')).$date->format('y');
        $fromCode = $this->locationCode($fromLocation);
        $toCode = $this->locationCode($toLocation);
        $prefix = "FAKTUR/{$fromCode}-{$toCode}/BRJ/{$period}/";

        $latest = SerahTerimaFakturBarangJadi::query()
            ->withTrashed()
            ->where('doc_no', 'like', $prefix.'%')
            ->lockForUpdate()
            ->orderByDesc('doc_no')
            ->value('doc_no');

        $next = 1;

        if (is_string($latest) && preg_match('/\/(\d{4})$/', $latest, $matches) === 1) {
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
