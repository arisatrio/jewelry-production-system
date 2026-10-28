<?php

namespace App\Support;

use App\Models\Production;
use App\Models\SpkChain;
use Illuminate\Support\Collection;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SpkChainReceiptBuilder
{
    public function __construct(
        private readonly ChainStockService $chainStock,
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

        $row['size'] = $this->formatSize($row['size'] ?? null);

        if (array_key_exists('chainLength', $row)) {
            $row['chainLength'] = $this->formatWholeMeasurement($row['chainLength']);
        }

        return $row;
    }

    public function buildItems(
        Production $production,
        array $lineIds,
        string $customerLabel,
    ): array {
        $chains = $this->resolveChains($production, $lineIds);

        if ($chains->isEmpty()) {
            throw new HttpException(422, 'Tidak ada baris rantai untuk tanda terima.');
        }

        $spkNo = (string) ($production->spk_no ?? '-');

        return $chains
            ->map(function (SpkChain $chain) use ($production, $spkNo, $customerLabel): array {
                $unit = $this->latestIssuedUnit($production, $chain);

                $item = [
                    'spkChainLineId' => (int) $chain->line_id,
                    'spkNo' => $spkNo,
                    'category' => (string) ($chain->category ?? '-'),
                    'item' => (string) ($chain->item ?? '-'),
                    'color' => (string) ($chain->color ?? '-'),
                    'size' => $this->formatSize($chain->size),
                    'unitNo' => '-',
                    'chainLength' => '-',
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
     * @return Collection<int, SpkChain>
     */
    private function resolveChains(Production $production, array $lineIds): Collection
    {
        $query = $production->chains()
            ->notDeleted()
            ->orderBy('line_id');

        if ($lineIds !== []) {
            $query->whereIn('line_id', $lineIds);
        }

        $chains = $query->get();

        if ($lineIds !== []) {
            $chains = $chains->sortBy(
                fn (SpkChain $chain): int|false => array_search(
                    (int) $chain->line_id,
                    $lineIds,
                    true,
                ),
            )->values();
        }

        return $chains;
    }

    /**
     * @return array<string, int|string|null>|null
     */
    private function latestIssuedUnit(Production $production, SpkChain $chain): ?array
    {
        if ($chain->mschain_id === null) {
            return null;
        }

        $unit = $this->chainStock->latestIssuedUnitForSpkChain(
            (int) $production->row_id,
            (int) $chain->mschain_id,
        );

        if ($unit === null) {
            return null;
        }

        return $this->chainStock->toUnitRow($unit);
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

        $unitLength = $this->formatWholeMeasurement($unit['chainLength'] ?? '-');
        if ($unitLength !== '-') {
            $item['size'] = $unitLength.' cm';
            $item['chainLength'] = $unitLength;
        }

        $item['unitNo'] = (string) ($unit['unitNo'] ?? '-');
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
        foreach (['color', 'size', 'unitNo', 'chainLength', 'weight'] as $field) {
            $value = $allocated[$field] ?? null;

            if ($value === null || $value === '' || $value === '-') {
                continue;
            }

            $row[$field] = $value;
        }

        return $row;
    }

    private function formatSize(mixed $size): string
    {
        $text = trim((string) ($size ?? ''));

        if ($text === '') {
            return '-';
        }

        $formatted = $this->formatWholeMeasurement($text);

        if ($formatted === '-') {
            return '-';
        }

        if (str_ends_with(strtolower($formatted), 'cm')) {
            return $formatted;
        }

        return $formatted.' cm';
    }

    /**
     * Tampilan ukuran/panjang tanpa angka di belakang koma (bulatkan ke bilangan bulat).
     */
    private function formatWholeMeasurement(mixed $value): string
    {
        $text = trim((string) ($value));

        if ($text === '' || $text === '-') {
            return '-';
        }

        $lower = strtolower($text);
        $hasCmSuffix = str_ends_with($lower, 'cm');
        $numericPart = $hasCmSuffix
            ? trim(substr($text, 0, strlen($text) - 2))
            : $text;

        $normalized = str_replace(',', '.', $numericPart);

        if (! is_numeric($normalized)) {
            return $text;
        }

        $whole = (string) (int) round((float) $normalized);

        return $hasCmSuffix ? "{$whole} cm" : $whole;
    }
}
