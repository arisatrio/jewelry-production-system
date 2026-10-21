<?php

namespace App\Support;

use App\Models\MsShape;
use App\Models\Production;
use App\Models\SpkStone;

class SpkProductionStoneList
{
    public function __construct(
        private SkuMasterDiamondMapper $diamondMapper,
        private SpkStoneStockChecker $stoneStockChecker,
    ) {}

    /**
     * Ringkasan satu baris per batu SPK untuk kolom tabel (selaras format varian item).
     *
     * @return list<string>
     */
    public function indexDisplayLines(Production $production): array
    {
        return array_map(
            fn (array $stone): string => $this->formatIndexDisplayLine($stone),
            $this->forProduction($production, false),
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function forProduction(Production $production, bool $includeStock = true): array
    {
        $production->loadMissing([
            'sku.diamonds' => fn ($query) => $query->notDeleted()->orderBy('line_id'),
        ]);

        $masterStones = $production->sku !== null
            ? $this->diamondMapper->toFormStones($production->sku->diamonds)
            : [];

        $stones = $production->stones()
            ->notDeleted()
            ->with(['shape', 'position'])
            ->orderBy('line_id')
            ->get()
            ->values();

        $stockByLineId = $includeStock
            ? $this->stoneStockChecker->forStones($stones)
            : [];

        return $stones
            ->map(function (SpkStone $stone, int $index) use ($masterStones, $stockByLineId, $includeStock): array {
                $item = $this->toStoneItem($stone);
                if ($includeStock) {
                    $item['stock'] = $stockByLineId[(int) $stone->line_id] ?? null;
                }
                $master = $masterStones[$index] ?? null;

                $item['master'] = $master === null
                    ? null
                    : [
                        'positionName' => filled($master['positionNama']) ? $master['positionNama'] : null,
                        'shapeName' => filled($master['shapeName']) ? $master['shapeName'] : null,
                        'shapeId' => filled($master['shapeId']) ? $master['shapeId'] : null,
                        'size' => filled($master['size']) ? $master['size'] : null,
                        'caratPerPcs' => filled($master['caratPerPcs']) ? $master['caratPerPcs'] : null,
                        'pcs' => filled($master['pcs']) ? $master['pcs'] : null,
                    ];

                return $item;
            })
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function toStoneItem(SpkStone $stone): array
    {
        $pcs = (int) ($stone->pcs ?? 0);
        $totalCarat = (float) ($stone->carat ?? 0);
        $caratPerPcs = $pcs > 0 ? round($totalCarat / $pcs, 3) : 0;
        $shapeName = $this->shapeDisplayName($stone->shape);

        return [
            'id' => (string) $stone->line_id,
            'stoneKind' => $this->stoneStockChecker->stockSourceForStone($stone),
            'positionId' => $stone->position_id !== null
                ? (string) $stone->position_id
                : '',
            'positionName' => $stone->position?->nama ?? '-',
            'shape' => $stone->shape?->name ?? '-',
            'shapeId' => $stone->shape_id !== null
                ? (string) $stone->shape_id
                : '',
            'shapeCode' => $stone->shape?->code ?? '-',
            'shapeName' => $shapeName,
            'pcs' => $pcs,
            'carat' => $caratPerPcs,
            'caratPerPcs' => number_format($caratPerPcs, 3, '.', ''),
            'totalCarat' => number_format($totalCarat, 3, '.', ''),
            'size' => $stone->size ?? '-',
        ];
    }

    /**
     * @param  array<string, mixed>  $stone
     */
    private function formatIndexDisplayLine(array $stone): string
    {
        $shapeName = trim((string) ($stone['shapeName'] ?? ''));
        if ($shapeName === '' || $shapeName === '-') {
            $shapeName = 'Tanpa nama';
        }

        $size = trim((string) ($stone['size'] ?? ''));
        $sizePart = null;
        if ($size !== '' && $size !== '-') {
            $sizePart = str_contains(strtolower($size), 'mm') ? $size : "{$size} mm";
        }

        $pcs = (int) ($stone['pcs'] ?? 0);
        $caratPerPcs = trim((string) ($stone['caratPerPcs'] ?? $stone['carat'] ?? ''));
        $totalCarat = trim((string) ($stone['totalCarat'] ?? ''));

        $parts = array_values(array_filter([
            $shapeName,
            $sizePart,
            $pcs > 0 ? "{$pcs} pcs" : null,
            $caratPerPcs !== '' && $caratPerPcs !== '0' && $caratPerPcs !== '0.000'
                ? "{$caratPerPcs} crt/pcs"
                : null,
            $totalCarat !== '' && $totalCarat !== '0' && $totalCarat !== '0.000'
                ? "{$totalCarat} crt"
                : null,
        ], fn (?string $part): bool => $part !== null && $part !== ''));

        return $parts !== [] ? implode(', ', $parts) : '-';
    }

    private function shapeDisplayName(?MsShape $shape): string
    {
        if ($shape === null) {
            return '-';
        }

        $name = trim((string) ($shape->name ?? ''));

        if ($name !== '') {
            return $name;
        }

        $code = trim((string) ($shape->code ?? ''));

        return $code !== '' ? $code : 'Shape #'.$shape->row_id;
    }
}
