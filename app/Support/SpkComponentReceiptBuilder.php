<?php

namespace App\Support;

use App\Models\Production;
use App\Models\SpkComponent;
use Illuminate\Support\Collection;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SpkComponentReceiptBuilder
{
    public function __construct(
        private readonly ComponentStockService $componentStock,
    ) {}

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public function normalizeStoredItemRow(array $row): array
    {
        if (isset($row['allocatedRow']) && is_array($row['allocatedRow'])) {
            $row = $this->mergeLegacyAllocatedRow($row, $row['allocatedRow']);
            unset($row['allocatedRow']);
        }

        if (array_key_exists('componentSize', $row)) {
            $row['componentSize'] = $this->formatMeasurement($row['componentSize']);
        }

        return $row;
    }

    /**
     * @param  list<int>  $lineIds
     * @return list<array<string, mixed>>
     */
    public function buildItems(
        Production $production,
        array $lineIds,
        string $customerLabel,
    ): array {
        $components = $this->resolveComponents($production, $lineIds);

        if ($components->isEmpty()) {
            throw new HttpException(422, 'Tidak ada baris komponen untuk tanda terima.');
        }

        $spkNo = (string) ($production->spk_no ?? '-');

        return $components
            ->map(function (SpkComponent $component) use ($production, $spkNo, $customerLabel): array {
                $unit = $this->latestIssuedUnit($production, $component);

                $item = [
                    'spkComponentLineId' => (int) $component->line_id,
                    'spkNo' => $spkNo,
                    'category' => (string) ($component->category ?? '-'),
                    'item' => (string) ($component->item ?? '-'),
                    'color' => (string) ($component->color ?? '-'),
                    'unitNo' => '-',
                    'componentSize' => '-',
                    'weight' => '-',
                    'customer' => $customerLabel,
                ];

                if ($unit !== null) {
                    $this->applyIssuedUnitToItem($item, $unit);
                }

                return $item;
            })
            ->values()
            ->all();
    }

    /**
     * @param  list<int>  $lineIds
     * @return Collection<int, SpkComponent>
     */
    private function resolveComponents(Production $production, array $lineIds): Collection
    {
        $query = $production->components()
            ->notDeleted()
            ->orderBy('line_id');

        if ($lineIds !== []) {
            $query->whereIn('line_id', $lineIds);
        }

        $components = $query->get();

        if ($lineIds !== []) {
            $components = $components->sortBy(
                fn (SpkComponent $component): int|false => array_search(
                    (int) $component->line_id,
                    $lineIds,
                    true,
                ),
            )->values();
        }

        return $components;
    }

    /**
     * @return array<string, int|string|null>|null
     */
    private function latestIssuedUnit(Production $production, SpkComponent $component): ?array
    {
        if ($component->mscomponent_id === null) {
            return null;
        }

        $unit = $this->componentStock->latestIssuedUnitForSpkComponent(
            (int) $production->row_id,
            (int) $component->mscomponent_id,
        );

        if ($unit === null) {
            return null;
        }

        return $this->componentStock->toUnitRow($unit);
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<string, int|string|null>  $unit
     */
    private function applyIssuedUnitToItem(array &$item, array $unit): void
    {
        $unitColor = trim((string) ($unit['color'] ?? ''));
        if ($unitColor !== '') {
            $item['color'] = $unitColor;
        }

        $item['unitNo'] = (string) ($unit['unitNo'] ?? '-');
        $item['componentSize'] = $this->formatMeasurement($unit['componentSize'] ?? '-');
        $item['weight'] = filled($unit['weight'] ?? null)
            ? (string) $unit['weight']
            : '-';
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $allocated
     * @return array<string, mixed>
     */
    private function mergeLegacyAllocatedRow(array $row, array $allocated): array
    {
        foreach (['color', 'unitNo', 'componentSize', 'weight'] as $field) {
            $value = $allocated[$field] ?? null;

            if ($value === null || $value === '' || $value === '-') {
                continue;
            }

            $row[$field] = $value;
        }

        return $row;
    }

    private function formatMeasurement(mixed $value): string
    {
        $text = trim((string) ($value ?? ''));

        if ($text === '' || $text === '-') {
            return '-';
        }

        $normalized = str_replace(',', '.', $text);

        if (! is_numeric($normalized)) {
            return $text;
        }

        return rtrim(rtrim(number_format((float) $normalized, 2, '.', ''), '0'), '.');
    }
}
