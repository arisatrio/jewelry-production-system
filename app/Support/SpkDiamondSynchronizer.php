<?php

namespace App\Support;

use App\Models\SpkDiamond;
use App\Models\SpkDiamondDiamond;
use App\Models\SpkDiamondStone;

class SpkDiamondSynchronizer
{
    /**
     * @param  array{
     *     setting_stones?: list<array<string, mixed>>,
     *     diamonds?: list<array<string, mixed>>
     * }  $payload
     */
    public function sync(SpkDiamond $document, array $payload, string $actor): void
    {
        $this->syncStones($document, $payload['setting_stones'] ?? [], $actor);
        $this->syncDiamonds($document, $payload['diamonds'] ?? [], $actor);
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    private function syncStones(SpkDiamond $document, array $lines, string $actor): void
    {
        $now = now();

        SpkDiamondStone::query()
            ->where('spk_diamond_id', $document->id)
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

            SpkDiamondStone::query()->create([
                'spk_diamond_id' => $document->id,
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
    private function syncDiamonds(SpkDiamond $document, array $lines, string $actor): void
    {
        $now = now();

        SpkDiamondDiamond::query()
            ->where('spk_diamond_id', $document->id)
            ->where('is_deleted', 0)
            ->update(['is_deleted' => 1]);

        foreach ($lines as $line) {
            $diamondId = (int) ($line['diamond_id'] ?? 0);

            if ($diamondId <= 0) {
                continue;
            }

            SpkDiamondDiamond::query()->create([
                'spk_diamond_id' => $document->id,
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
