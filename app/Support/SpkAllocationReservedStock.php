<?php

namespace App\Support;

use App\Models\SpkAllocation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SpkAllocationReservedStock
{
    /**
     * @return list<int>
     */
    public function diamondIdsForSpk(int $spkId): array
    {
        if ($spkId <= 0 || ! Schema::connection('third')->hasTable('spk_allocation_diamond')) {
            return [];
        }

        return DB::connection('third')
            ->table('spk_allocation_diamond as line')
            ->join('spk_allocation as header', 'header.id', '=', 'line.spk_allocation_id')
            ->where('header.spk_id', $spkId)
            ->where('header.is_deleted', 0)
            ->where('line.is_deleted', 0)
            ->pluck('line.trdiamond_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @return list<int>
     */
    public function diamondIdsReservedByOtherSpks(int $excludeSpkId): array
    {
        if (! Schema::connection('third')->hasTable('spk_allocation_diamond')) {
            return [];
        }

        return DB::connection('third')
            ->table('spk_allocation_diamond as line')
            ->join('spk_allocation as header', 'header.id', '=', 'line.spk_allocation_id')
            ->where('header.is_deleted', 0)
            ->where('line.is_deleted', 0)
            ->when($excludeSpkId > 0, fn ($query) => $query->where('header.spk_id', '!=', $excludeSpkId))
            ->pluck('line.trdiamond_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, float>
     */
    public function pcsReservedByStoneExcludingSpk(?int $excludeSpkId): array
    {
        if (! Schema::connection('third')->hasTable('spk_allocation_stone')) {
            return [];
        }

        $rows = DB::connection('third')
            ->table('spk_allocation_stone as line')
            ->join('spk_allocation as header', 'header.id', '=', 'line.spk_allocation_id')
            ->where('header.is_deleted', 0)
            ->where('line.is_deleted', 0)
            ->when($excludeSpkId !== null && $excludeSpkId > 0, fn ($query) => $query->where('header.spk_id', '!=', $excludeSpkId))
            ->selectRaw('line.stone_id, SUM(COALESCE(line.pcs, 0)) as total_pcs')
            ->groupBy('line.stone_id')
            ->get();

        $map = [];

        foreach ($rows as $row) {
            $map[(int) $row->stone_id] = (float) $row->total_pcs;
        }

        return $map;
    }

    /**
     * @return array<int, float>
     */
    public function crtReservedByStoneExcludingSpk(?int $excludeSpkId): array
    {
        if (! Schema::connection('third')->hasTable('spk_allocation_stone')) {
            return [];
        }

        $rows = DB::connection('third')
            ->table('spk_allocation_stone as line')
            ->join('spk_allocation as header', 'header.id', '=', 'line.spk_allocation_id')
            ->where('header.is_deleted', 0)
            ->where('line.is_deleted', 0)
            ->when($excludeSpkId !== null && $excludeSpkId > 0, fn ($query) => $query->where('header.spk_id', '!=', $excludeSpkId))
            ->selectRaw('line.stone_id, SUM(COALESCE(line.crt, 0)) as total_crt')
            ->groupBy('line.stone_id')
            ->get();

        $map = [];

        foreach ($rows as $row) {
            $map[(int) $row->stone_id] = (float) $row->total_crt;
        }

        return $map;
    }

    public function hasActiveLinesForSpk(int $spkId): bool
    {
        $allocation = SpkAllocation::query()
            ->notDeleted()
            ->where('spk_id', $spkId)
            ->first();

        if ($allocation === null) {
            return false;
        }

        $stoneCount = $allocation->stones()->notDeleted()->count();
        $diamondCount = $allocation->diamonds()->notDeleted()->count();

        return $stoneCount > 0 || $diamondCount > 0;
    }
}
