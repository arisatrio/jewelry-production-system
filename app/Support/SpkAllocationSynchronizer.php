<?php

namespace App\Support;

use App\Models\Production;
use App\Models\SpkAllocation;
use App\Models\SpkAllocationDiamond;
use App\Models\SpkAllocationStone;

class SpkAllocationSynchronizer
{
    /**
     * @param  array{
     *     setting_stones?: list<array<string, mixed>>,
     *     diamonds?: list<array<string, mixed>>
     * }  $payload
     */
    public function sync(Production $production, array $payload, string $actor): SpkAllocation
    {
        $now = now();

        $allocation = SpkAllocation::query()
            ->notDeleted()
            ->where('spk_id', $production->row_id)
            ->first();

        if ($allocation === null) {
            $allocation = SpkAllocation::query()->create([
                'spk_id' => $production->row_id,
                'spk_no' => $production->spk_no,
                'is_deleted' => 0,
                'created_date' => $now,
                'created_by' => $actor,
                'modified_date' => $now,
                'modified_by' => $actor,
            ]);
        } else {
            $allocation->update([
                'spk_no' => $production->spk_no,
                'modified_date' => $now,
                'modified_by' => $actor,
            ]);
        }

        $this->syncStones($allocation, $payload['setting_stones'] ?? [], $actor);
        $this->syncDiamonds($allocation, $payload['diamonds'] ?? [], $actor);

        return $allocation->refresh();
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    private function syncStones(SpkAllocation $allocation, array $lines, string $actor): void
    {
        $now = now();

        SpkAllocationStone::query()
            ->where('spk_allocation_id', $allocation->id)
            ->where('is_deleted', 0)
            ->update([
                'is_deleted' => 1,
                'modified_date' => $now,
                'modified_by' => $actor,
            ]);

        foreach ($lines as $line) {
            $stoneId = (int) ($line['stone_id'] ?? 0);

            if ($stoneId <= 0) {
                continue;
            }

            SpkAllocationStone::query()->create([
                'spk_allocation_id' => $allocation->id,
                'stone_id' => $stoneId,
                'pcs' => $this->toDecimal($line['pcs'] ?? null, 0),
                'crt' => $this->toDecimal($line['crt'] ?? null, 3),
                'notes' => filled($line['notes'] ?? null) ? trim((string) $line['notes']) : null,
                'is_deleted' => 0,
                'created_date' => $now,
                'created_by' => $actor,
                'modified_date' => $now,
                'modified_by' => $actor,
            ]);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    private function syncDiamonds(SpkAllocation $allocation, array $lines, string $actor): void
    {
        $now = now();

        SpkAllocationDiamond::query()
            ->where('spk_allocation_id', $allocation->id)
            ->where('is_deleted', 0)
            ->update(['is_deleted' => 1]);

        foreach ($lines as $line) {
            $diamondId = (int) ($line['diamond_id'] ?? 0);

            if ($diamondId <= 0) {
                continue;
            }

            SpkAllocationDiamond::query()->create([
                'spk_allocation_id' => $allocation->id,
                'trdiamond_id' => $diamondId,
                'is_deleted' => 0,
                'created_date' => $now,
                'created_by' => $actor,
            ]);
        }
    }

    private function toDecimal(mixed $value, int $precision): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return number_format((float) str_replace(',', '.', (string) $value), $precision, '.', '');
    }
}
