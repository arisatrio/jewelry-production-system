<?php

namespace App\Support;

use App\Models\MsChain;

class SpkChainPayloadNormalizer
{
    /**
     * @param  array<string, mixed>  $chain
     * @return array{
     *     mschain_id: int|null,
     *     category: string|null,
     *     item: string|null,
     *     color: string|null,
     *     size: string|null
     * }
     */
    public function normalize(array $chain): array
    {
        $mschainId = filled($chain['mschain_id'] ?? null)
            ? (int) $chain['mschain_id']
            : null;

        $category = filled($chain['category'] ?? null)
            ? trim((string) $chain['category'])
            : null;
        $item = filled($chain['item'] ?? null)
            ? trim((string) $chain['item'])
            : null;
        $color = filled($chain['color'] ?? null)
            ? trim((string) $chain['color'])
            : null;
        $size = filled($chain['size'] ?? null)
            ? trim((string) $chain['size'])
            : null;

        if ($mschainId === null) {
            return [
                'mschain_id' => null,
                'category' => $category,
                'item' => $item,
                'color' => $color,
                'size' => $size,
            ];
        }

        $master = MsChain::query()
            ->notDeleted()
            ->where('row_id', $mschainId)
            ->first();

        if ($master === null) {
            return [
                'mschain_id' => $mschainId,
                'category' => $category,
                'item' => $item,
                'color' => $color,
                'size' => $size,
            ];
        }

        return [
            'mschain_id' => $mschainId,
            'category' => trim((string) $master->chain_category),
            'item' => filled($master->item_name)
                ? trim((string) $master->item_name)
                : trim((string) $master->item),
            'color' => $color,
            'size' => $this->formatLengthForSpk($master->length) ?? $size,
        ];
    }

    private function formatLengthForSpk(mixed $length): ?string
    {
        if ($length === null || $length === '') {
            return null;
        }

        $numeric = (float) $length;

        if ($numeric <= 0) {
            return null;
        }

        $formatted = number_format($numeric, 2, '.', '');

        return rtrim(rtrim($formatted, '0'), '.');
    }
}
