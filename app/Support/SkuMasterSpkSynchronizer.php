<?php

namespace App\Support;

use App\Models\MsPosition;
use App\Models\MsShape;
use App\Models\SkuMaster;
use App\Models\SkuMasterDiamond;
use Illuminate\Support\Facades\DB;

class SkuMasterSpkSynchronizer
{
    public function __construct(
        private SkuMasterDiamondMapper $diamondMapper,
    ) {}

    /**
     * Push SPK form gold weight / stones into SKU master when they differ from master.
     *
     * @param  array<string, mixed>  $data
     */
    public function sync(?SkuMaster $sku, array $data, string $actor): void
    {
        if ($sku === null) {
            return;
        }

        DB::connection('second')->transaction(function () use ($sku, $data, $actor): void {
            $sku->refresh();

            $this->syncGoldWeight($sku, $data['gold_weight'] ?? null, $actor);
            $this->syncDiamonds($sku, is_array($data['stones'] ?? null) ? $data['stones'] : [], $actor);
        });
    }

    private function syncGoldWeight(SkuMaster $sku, mixed $goldWeight, string $actor): void
    {
        $next = $this->normalizeGoldWeight($goldWeight);
        $current = $this->normalizeGoldWeight($sku->gold_weight);

        if ($next === $current) {
            return;
        }

        $sku->update([
            'gold_weight' => $next,
            'modified_by' => $actor,
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $stones
     */
    private function syncDiamonds(SkuMaster $sku, array $stones, string $actor): void
    {
        $submitted = $this->normalizeSubmittedStones($stones);
        $master = $this->normalizeMasterDiamonds($sku);

        if ($submitted === $master) {
            return;
        }

        $now = now();

        $sku->diamonds()
            ->notDeleted()
            ->update([
                'is_deleted' => 1,
                'modified_date' => $now,
                'modified_by' => $actor,
            ]);

        foreach ($submitted as $stone) {
            SkuMasterDiamond::query()->create([
                'row_id' => $sku->id,
                'grain' => $stone['pcs'] !== '' ? (int) $stone['pcs'] : null,
                'grade' => $stone['carat_per_pcs'] !== '' ? $stone['carat_per_pcs'] : null,
                'diamond_type' => $stone['diamond_type'] !== '' ? $stone['diamond_type'] : null,
                'diameter' => $stone['size'] !== '' ? $stone['size'] : null,
                'position' => $stone['position'] !== '' ? $stone['position'] : null,
                'is_deleted' => 0,
                'created_date' => $now,
                'created_by' => $actor,
                'modified_date' => $now,
                'modified_by' => $actor,
            ]);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $stones
     * @return list<array{
     *     shape_id: string,
     *     diamond_type: string,
     *     position: string,
     *     pcs: string,
     *     carat_per_pcs: string,
     *     size: string
     * }>
     */
    private function normalizeSubmittedStones(array $stones): array
    {
        $normalized = [];

        foreach ($stones as $stone) {
            if (! is_array($stone)) {
                continue;
            }

            $shapeId = filled($stone['shape_id'] ?? null) ? (string) (int) $stone['shape_id'] : '';
            $position = $this->resolvePositionLabel(
                filled($stone['position_id'] ?? null) ? (int) $stone['position_id'] : null,
                isset($stone['position_nama']) ? (string) $stone['position_nama'] : null,
            );
            $pcs = filled($stone['pcs'] ?? null) ? (string) (int) $stone['pcs'] : '';
            $caratPerPcs = $this->normalizeCarat($stone['carat_per_pcs'] ?? null);
            $size = filled($stone['size'] ?? null) ? trim((string) $stone['size']) : '';

            if (
                $shapeId === ''
                && $position === ''
                && $pcs === ''
                && $caratPerPcs === ''
                && $size === ''
            ) {
                continue;
            }

            $normalized[] = [
                'shape_id' => $shapeId,
                'diamond_type' => $this->resolveDiamondType($shapeId),
                'position' => $position,
                'pcs' => $pcs,
                'carat_per_pcs' => $caratPerPcs,
                'size' => $size,
            ];
        }

        return $normalized;
    }

    /**
     * @return list<array{
     *     shape_id: string,
     *     diamond_type: string,
     *     position: string,
     *     pcs: string,
     *     carat_per_pcs: string,
     *     size: string
     * }>
     */
    private function normalizeMasterDiamonds(SkuMaster $sku): array
    {
        $diamonds = $sku->diamonds()
            ->notDeleted()
            ->orderBy('line_id')
            ->get();

        return collect($this->diamondMapper->toFormStones($diamonds))
            ->map(function (array $stone): array {
                $shapeId = filled($stone['shapeId'] ?? null) ? (string) $stone['shapeId'] : '';

                return [
                    'shape_id' => $shapeId,
                    'diamond_type' => $this->resolveDiamondType($shapeId),
                    'position' => trim((string) ($stone['positionNama'] ?? '')),
                    'pcs' => filled($stone['pcs'] ?? null) ? (string) (int) $stone['pcs'] : '',
                    'carat_per_pcs' => $this->normalizeCarat($stone['caratPerPcs'] ?? null),
                    'size' => filled($stone['size'] ?? null) ? trim((string) $stone['size']) : '',
                ];
            })
            ->values()
            ->all();
    }

    private function resolveDiamondType(string $shapeId): string
    {
        if ($shapeId === '') {
            return '';
        }

        $shape = MsShape::query()
            ->notDeleted()
            ->whereKey((int) $shapeId)
            ->first(['row_id', 'code', 'name']);

        if ($shape === null) {
            return '';
        }

        $code = trim((string) ($shape->code ?? ''));

        if ($code !== '') {
            return strtoupper($code);
        }

        return trim((string) ($shape->name ?? ''));
    }

    private function resolvePositionLabel(?int $positionId, ?string $positionNama): string
    {
        $nama = trim((string) $positionNama);

        if ($nama !== '') {
            return $nama;
        }

        if ($positionId === null) {
            return '';
        }

        $position = MsPosition::query()->whereKey($positionId)->value('nama');

        return filled($position) ? trim((string) $position) : '';
    }

    private function normalizeGoldWeight(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return number_format((float) $value, 3, '.', '');
    }

    private function normalizeCarat(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return number_format((float) $value, 3, '.', '');
    }
}
