<?php

namespace App\Support;

use Illuminate\Contracts\Validation\Validator;

class SpkAllocationSettingStockValidator
{
    public function __construct(
        private SpkDiamondSettingStockValidator $spkDiamondSettingStockValidator,
        private SpkAllocationReservedStock $reservedStock,
        private StoneLedger $stoneLedger,
    ) {}

    /**
     * @param  list<array{stone_id?: int|string, pcs?: string|float|null}>  $lines
     */
    public function validateLines(array $lines, int $spkId, ?Validator $validator = null): void
    {
        $message = $this->firstErrorForLines($lines, $spkId);

        if ($message === null || $validator === null) {
            return;
        }

        $validator->errors()->add('setting_stones', $message);
    }

    /**
     * @param  list<array{stone_id?: int|string, pcs?: string|float|null}>  $lines
     */
    public function firstErrorForLines(array $lines, int $spkId): ?string
    {
        $requiredByStone = $this->requiredPcsByStone($lines);

        if ($requiredByStone === []) {
            return null;
        }

        $period = $this->stoneLedger->activePeriod();

        if ($period === null) {
            return 'Periode batu aktif tidak ditemukan.';
        }

        $balances = $this->stoneLedger->balancePcsByStone(
            $period['id'],
            array_keys($requiredByStone),
        );

        $reservedElsewhere = $this->reservedStock->pcsReservedByStoneExcludingSpk($spkId);

        foreach ($requiredByStone as $stoneId => $requiredPcs) {
            $balance = (float) ($balances[$stoneId] ?? 0);
            $reserved = (float) ($reservedElsewhere[$stoneId] ?? 0);
            $available = $balance - $reserved;

            if ($available < $requiredPcs) {
                return SpkDiamondSettingStockValidator::INSUFFICIENT_STOCK_MESSAGE;
            }
        }

        return null;
    }

    /**
     * @param  list<array{stone_id?: int|string, pcs?: string|float|null}>  $lines
     * @return array<int, float>
     */
    private function requiredPcsByStone(array $lines): array
    {
        $requiredByStone = [];

        foreach ($lines as $line) {
            if (! is_array($line)) {
                continue;
            }

            $stoneId = (int) ($line['stone_id'] ?? 0);
            $pcs = (float) str_replace(',', '.', trim((string) ($line['pcs'] ?? '0')));

            if ($stoneId <= 0 || $pcs <= 0) {
                continue;
            }

            $requiredByStone[$stoneId] = ($requiredByStone[$stoneId] ?? 0) + $pcs;
        }

        return $requiredByStone;
    }
}
