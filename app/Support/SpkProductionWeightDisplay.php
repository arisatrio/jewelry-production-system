<?php

namespace App\Support;

use App\Models\Production;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SpkProductionWeightDisplay
{
    /**
     * @return array{emasWeight: string, finishedGoodsWeight: string}
     */
    public function formattedForProduction(Production $production, ?float $stoneWeight = null): array
    {
        return [
            'emasWeight' => $this->formatNullableWeight(
                $this->emasWeightGrams($production, $stoneWeight),
            ),
            'finishedGoodsWeight' => $this->formatNullableWeight(
                $this->finishedGoodsWeightGrams($production),
            ),
        ];
    }

    public function emasWeightGrams(Production $production, ?float $stoneWeight = null): ?float
    {
        $lastWeight = $this->nullableFloat($production->last_weight);

        if ($lastWeight === null) {
            return null;
        }

        $stone = $stoneWeight ?? $this->totalStoneWeightGrams((int) $production->row_id);

        return round($lastWeight - $stone, 2);
    }

    public function finishedGoodsWeightGrams(Production $production): ?float
    {
        return $this->nullableFloat($production->last_weight);
    }

    public function totalStoneWeightGrams(int $spkId): float
    {
        return $this->totalStoneWeightBySpkIds([$spkId])[$spkId] ?? 0.0;
    }

    /**
     * @param  list<int>  $spkIds
     * @return array<int, float>
     */
    public function totalStoneWeightBySpkIds(array $spkIds): array
    {
        $spkIds = array_values(array_unique(array_filter(array_map(intval(...), $spkIds), fn (int $id): bool => $id > 0)));

        $weights = [];

        foreach ($spkIds as $spkId) {
            $weights[$spkId] = 0.0;
        }

        if ($spkIds === [] || ! $this->mountingTableAvailable()) {
            return $weights;
        }

        $columns = ['spk_id'];

        foreach (['weight_frame', 'total_weigth_frame_diamond', 'weight_diamond'] as $column) {
            if (Schema::connection('third')->hasColumn('diamondmounting', $column)) {
                $columns[] = $column;
            }
        }

        $query = DB::connection('third')
            ->table('diamondmounting')
            ->whereIn('spk_id', $spkIds)
            ->select($columns);

        if (Schema::connection('third')->hasColumn('diamondmounting', 'is_deleted')) {
            $query->where('is_deleted', 0);
        }

        foreach ($query->get() as $row) {
            $spkId = (int) ($row->spk_id ?? 0);

            if ($spkId <= 0) {
                continue;
            }

            $stone = $this->stoneWeightFromMountingRow($row);

            if ($stone === null) {
                continue;
            }

            $weights[$spkId] = round(($weights[$spkId] ?? 0.0) + $stone, 3);
        }

        return $weights;
    }

    private function stoneWeightFromMountingRow(object $record): ?float
    {
        $framePlusStone = isset($record->total_weigth_frame_diamond)
            && $record->total_weigth_frame_diamond !== null
            && $record->total_weigth_frame_diamond !== ''
            ? (float) $record->total_weigth_frame_diamond
            : null;
        $frameWeight = isset($record->weight_frame)
            && $record->weight_frame !== null
            && $record->weight_frame !== ''
            ? (float) $record->weight_frame
            : null;

        if ($framePlusStone !== null && $frameWeight !== null) {
            return round($framePlusStone - $frameWeight, 3);
        }

        if (! isset($record->weight_diamond) || $record->weight_diamond === null || $record->weight_diamond === '') {
            return null;
        }

        $diamond = round((float) $record->weight_diamond, 3);

        return abs($diamond) < 0.0005 ? null : $diamond;
    }

    private function mountingTableAvailable(): bool
    {
        return Schema::connection('third')->hasTable('diamondmounting');
    }

    private function nullableFloat(mixed $value): ?float
    {
        if ($value === null || $value === '' || $value === '-') {
            return null;
        }

        return round((float) $value, 2);
    }

    private function formatNullableWeight(?float $value): string
    {
        if ($value === null) {
            return '-';
        }

        return number_format($value, 2, '.', '');
    }
}
