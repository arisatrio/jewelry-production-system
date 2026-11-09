<?php

namespace App\Support;

use App\Models\Production;

class SpkProductionListItemPresentation
{
    /**
     * @return array{
     *     spkImageUrl: string|null,
     *     typeSkuLabel: string|null,
     *     itemDescription: string|null,
     *     skuAssigned: bool
     * }
     */
    public static function forProduction(?Production $production): array
    {
        if ($production === null) {
            return [
                'spkImageUrl' => null,
                'typeSkuLabel' => null,
                'itemDescription' => null,
                'skuAssigned' => false,
            ];
        }

        $production->loadMissing(['categoryPrefix', 'sku']);

        $skuAssigned = filled($production->sku_id)
            && (int) $production->sku_id !== 0
            && $production->sku !== null;

        $typeSkuLabel = null;

        if ($skuAssigned) {
            $typeCode = trim((string) ($production->categoryPrefix?->prefix ?? ''));
            $skuCode = trim((string) ($production->sku?->sku_code ?? ''));
            $parts = array_values(array_filter(
                [$typeCode, $skuCode],
                fn (string $part): bool => $part !== '',
            ));
            $typeSkuLabel = $parts !== [] ? implode(' | ', $parts) : null;
        }

        $itemDescription = null;

        if (filled($production->description)) {
            $description = trim((string) $production->description);

            $itemDescription = $description !== '' ? $description : null;
        }

        return [
            'spkImageUrl' => SpkItemImageUrl::fromFileName($production->file_name),
            'typeSkuLabel' => $typeSkuLabel,
            'itemDescription' => $itemDescription,
            'skuAssigned' => $skuAssigned,
        ];
    }
}
