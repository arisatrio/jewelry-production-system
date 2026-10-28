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
        return $this->reserveSequence(1)[0];
    }

    /**
     * Reserve consecutive unit numbers in one locked read (for bulk receive).
     *
     * @return list<string>
     */
    public function reserveSequence(int $count): array
    {
        if ($count < 1) {
            throw new \InvalidArgumentException('Jumlah unit minimal 1.');
        }

        $pattern = '^'.self::PREFIX.'[0-9]{'.self::PADDING.'}$';

        $latest = TrxChain::query()
            ->whereNotNull('unit_no')
            ->whereRaw('unit_no REGEXP ?', [$pattern])
            ->lockForUpdate()
            ->orderByDesc('unit_no')
            ->value('unit_no');

        $start = 1;

        if (is_string($latest) && preg_match('/^'.self::PREFIX.'(\d+)$/', $latest, $matches) === 1) {
            $start = (int) $matches[1] + 1;
        }

        $numbers = [];

        for ($i = 0; $i < $count; $i++) {
            $numbers[] = sprintf('%s%0'.self::PADDING.'d', self::PREFIX, $start + $i);
        }

        return $numbers;
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
