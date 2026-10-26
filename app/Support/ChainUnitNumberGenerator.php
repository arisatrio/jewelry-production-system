<?php

namespace App\Support;

use App\Models\TrxChain;

class ChainUnitNumberGenerator
{
    public const PREFIX = 'CHA';

    public const PADDING = 4;

    /**
     * Generate the next chain unit number (e.g. CHA0001).
     */
    public function generate(): string
    {
        $pattern = '^'.self::PREFIX.'[0-9]{'.self::PADDING.'}$';

        $latest = TrxChain::query()
            ->whereNotNull('unit_no')
            ->whereRaw('unit_no REGEXP ?', [$pattern])
            ->lockForUpdate()
            ->orderByDesc('unit_no')
            ->value('unit_no');

        $next = 1;

        if (is_string($latest) && preg_match('/^'.self::PREFIX.'(\d+)$/', $latest, $matches) === 1) {
            $next = (int) $matches[1] + 1;
        }

        return sprintf('%s%0'.self::PADDING.'d', self::PREFIX, $next);
    }

    /**
     * Assign unit numbers to rows missing them, ordered by row_id.
     */
    public function backfillMissing(): int
    {
        $assigned = 0;

        while (TrxChain::query()->whereNull('unit_no')->exists()) {
            $unit = TrxChain::query()
                ->whereNull('unit_no')
                ->orderBy('row_id')
                ->lockForUpdate()
                ->first();

            if ($unit === null) {
                break;
            }

            $unit->update([
                'unit_no' => $this->generate(),
                'modified_date' => now(),
                'modified_by' => 'system',
            ]);

            $assigned++;
        }

        return $assigned;
    }
}
