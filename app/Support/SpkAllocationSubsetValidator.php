<?php

namespace App\Support;

use App\Models\SpkAllocation;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Support\Facades\DB;

class SpkAllocationSubsetValidator
{
    public function __construct(private SpkAllocationReservedStock $reservedStock) {}

    /**
     * @param  list<array{stone_id?: int|string, pcs?: string|float|null}>  $stoneLines
     * @param  list<array{diamond_id?: int|string}>  $diamondLines
     */
    public function validateForSpk(
        int $spkId,
        array $stoneLines,
        array $diamondLines,
        ?Validator $validator = null,
    ): void {
        if ($spkId <= 0) {
            return;
        }

        if (! $this->reservedStock->hasActiveLinesForSpk($spkId)) {
            return;
        }

        $message = $this->firstErrorForSpk($spkId, $stoneLines, $diamondLines);

        if ($message === null || $validator === null) {
            return;
        }

        if (str_contains($message, 'Dossier')) {
            $validator->errors()->add('diamonds', $message);
        } else {
            $validator->errors()->add('setting_stones', $message);
        }
    }

    /**
     * @param  list<array{stone_id?: int|string, pcs?: string|float|null}>  $stoneLines
     * @param  list<array{diamond_id?: int|string}>  $diamondLines
     */
    public function firstErrorForSpk(int $spkId, array $stoneLines, array $diamondLines): ?string
    {
        if ($spkId <= 0 || ! $this->reservedStock->hasActiveLinesForSpk($spkId)) {
            return null;
        }

        $allocatedPcs = $this->allocatedPcsByStone($spkId);
        $requiredPcs = $this->requiredPcsByStone($stoneLines);

        foreach ($requiredPcs as $stoneId => $required) {
            $allowed = (float) ($allocatedPcs[$stoneId] ?? 0);

            if ($required > $allowed) {
                return 'Jumlah batu setting melebihi alokasi untuk SPK ini.';
            }
        }

        foreach ($stoneLines as $line) {
            if (! is_array($line)) {
                continue;
            }

            $stoneId = (int) ($line['stone_id'] ?? 0);
            $pcs = (float) str_replace(',', '.', trim((string) ($line['pcs'] ?? '0')));

            if ($stoneId <= 0 || $pcs <= 0) {
                continue;
            }

            if (! array_key_exists($stoneId, $allocatedPcs)) {
                return 'Batu setting harus sesuai alokasi SPK produksi.';
            }
        }

        $allocatedDiamondIds = collect($this->reservedStock->diamondIdsForSpk($spkId))
            ->flip()
            ->all();

        foreach ($diamondLines as $line) {
            if (! is_array($line)) {
                continue;
            }

            $diamondId = (int) ($line['diamond_id'] ?? 0);

            if ($diamondId <= 0) {
                continue;
            }

            if (! isset($allocatedDiamondIds[$diamondId])) {
                return 'Batu Dossier harus sesuai alokasi SPK produksi.';
            }
        }

        return null;
    }

    /**
     * @return array<int, float>
     */
    private function allocatedPcsByStone(int $spkId): array
    {
        $allocation = SpkAllocation::query()
            ->notDeleted()
            ->where('spk_id', $spkId)
            ->first();

        if ($allocation === null) {
            return [];
        }

        $rows = DB::connection('third')
            ->table('spk_allocation_stone')
            ->where('spk_allocation_id', $allocation->id)
            ->where('is_deleted', 0)
            ->selectRaw('stone_id, SUM(COALESCE(pcs, 0)) as total_pcs')
            ->groupBy('stone_id')
            ->get();

        $map = [];

        foreach ($rows as $row) {
            $map[(int) $row->stone_id] = (float) $row->total_pcs;
        }

        return $map;
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
