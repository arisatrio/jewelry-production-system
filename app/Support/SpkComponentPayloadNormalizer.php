<?php

namespace App\Support;

use App\Models\MsComponent;

class SpkComponentPayloadNormalizer
{
    /**
     * @param  array<string, mixed>  $component
     * @return array{
     *     mscomponent_id: int|null,
     *     category: string|null,
     *     item: string|null,
     *     color: string|null,
     *     size: string|null
     * }
     */
    public function normalize(array $component): array
    {
        $mscomponentId = filled($component['mscomponent_id'] ?? null)
            ? (int) $component['mscomponent_id']
            : null;

        $category = filled($component['category'] ?? null)
            ? trim((string) $component['category'])
            : null;
        $item = filled($component['item'] ?? null)
            ? trim((string) $component['item'])
            : null;
        $color = filled($component['color'] ?? null)
            ? trim((string) $component['color'])
            : null;
        $size = filled($component['size'] ?? null)
            ? trim((string) $component['size'])
            : null;

        if ($mscomponentId === null) {
            return [
                'mscomponent_id' => null,
                'category' => $category,
                'item' => $item,
                'color' => $color,
                'size' => $size,
            ];
        }

        $master = MsComponent::query()
            ->notDeleted()
            ->where('row_id', $mscomponentId)
            ->first();

        if ($master === null) {
            return [
                'mscomponent_id' => $mscomponentId,
                'category' => $category,
                'item' => $item,
                'color' => $color,
                'size' => $size,
            ];
        }

        return [
            'mscomponent_id' => $mscomponentId,
            'category' => trim((string) $master->component_category),
            'item' => filled($master->item_name)
                ? trim((string) $master->item_name)
                : trim((string) $master->item),
            'color' => $color,
            'size' => $size,
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
