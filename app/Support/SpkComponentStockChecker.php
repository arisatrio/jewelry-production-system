<?php

namespace App\Support;

use App\Models\SpkComponent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class SpkComponentStockChecker
{
    public const STATUS_AVAILABLE = 'available';

    public const STATUS_UNAVAILABLE = 'unavailable';

    private const REQUIRED_UNITS = 1;

    public function __construct(
        private readonly ComponentStockService $componentStock,
        private readonly SpkComponentFulfillment $fulfillment,
    ) {}

    /**
     * Status stok per baris komponen SPK, dikunci dengan line_id.
     *
     * @param  Collection<int, SpkComponent>  $chains
     * @return array<int, array{
     *     status: 'available'|'unavailable',
     *     requiredUnits: int,
     *     availableUnits: int,
     *     balanceLength: string|null,
     *     note: string|null
     * }>
     */
    public function forComponents(Collection $components): array
    {
        if ($components->isEmpty()) {
            return [];
        }

        $results = [];

        foreach ($components as $component) {
            $results[(int) $component->line_id] = $this->forComponent($component);
        }

        return $results;
    }

    /**
     * Status alokasi & fulfillment komponen per baris SPK.
     *
     * @param  Collection<int, SpkComponent>  $chains
     * @return array<int, array{
     *     status: string,
     *     label: string,
     *     allocatedUnits: int,
     *     nextAction: string|null,
     *     nextActionLabel: string|null
     * }>
     */
    public function allocationForComponents(Collection $components): array
    {
        if ($components->isEmpty()) {
            return [];
        }

        $results = [];

        foreach ($components as $component) {
            $results[(int) $component->line_id] = $this->fulfillment->resolve($component);
        }

        return $results;
    }

    /**
     * Unit komponen terakhir yang sudah dikeluarkan ke SPK (alokasi aktual).
     *
     * @param  Collection<int, SpkComponent>  $chains
     * @return array<int, array{
     *     unitNo: string|null,
     *     name: string,
     *     color: string,
     *     size: string,
     *     componentSize: string,
     *     weight: string|null,
     *     id: int,
     *     allocatedDate: string|null,
     *     allocatedBy: string|null
     * }>
     */
    public function issuedUnitsForComponents(int $spkRowId, Collection $components): array
    {
        if ($components->isEmpty() || ! $this->componentStock->tableAvailable()) {
            return [];
        }

        $results = [];

        foreach ($components as $component) {
            if ($component->mscomponent_id === null) {
                continue;
            }

            $unit = $this->componentStock->latestIssuedUnitForSpkComponent(
                $spkRowId,
                (int) $component->mscomponent_id,
            );

            if ($unit === null) {
                continue;
            }

            $results[(int) $component->line_id] = $this->formatIssuedUnit(
                $this->componentStock->toUnitRow($unit),
            );
        }

        return $results;
    }

    /**
     * @param  array<string, int|string|null>  $unitRow
     * @return array{
     *     id: int,
     *     unitNo: string|null,
     *     name: string,
     *     color: string,
     *     size: string,
     *     componentSize: string,
     *     weight: string|null,
     *     allocatedDate: string|null,
     *     allocatedBy: string|null
     * }
     */
    private function formatIssuedUnit(array $unitRow): array
    {
        $unitColor = trim((string) ($unitRow['color'] ?? ''));
        $unitLength = $this->formatWholeMeasurement($unitRow['componentSize'] ?? '-');
        $size = $unitLength !== '-' ? $unitLength.' cm' : '-';
        $name = trim((string) ($unitRow['name'] ?? ''));

        $outDate = trim((string) ($unitRow['outDate'] ?? ''));
        $allocatedDate = $outDate !== ''
            ? Carbon::createFromFormat('Y-m-d', $outDate)->format('d-M-Y')
            : null;

        $modifiedBy = trim((string) ($unitRow['modifiedBy'] ?? ''));
        $createdBy = trim((string) ($unitRow['createdBy'] ?? ''));
        $allocatedBy = $modifiedBy !== ''
            ? $modifiedBy
            : ($createdBy !== '' ? $createdBy : null);

        return [
            'id' => (int) ($unitRow['id'] ?? 0),
            'unitNo' => filled($unitRow['unitNo'] ?? null)
                ? (string) $unitRow['unitNo']
                : null,
            'name' => $name !== '' ? $name : '-',
            'color' => $unitColor !== '' ? $unitColor : '-',
            'size' => $size,
            'componentSize' => $unitLength,
            'weight' => filled($unitRow['weight'] ?? null)
                ? (string) $unitRow['weight']
                : null,
            'allocatedDate' => $allocatedDate,
            'allocatedBy' => $allocatedBy,
        ];
    }

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

        return (string) (int) round((float) $normalized);
    }

    /**
     * @return array{
     *     status: 'available'|'unavailable',
     *     requiredUnits: int,
     *     availableUnits: int,
     *     balanceLength: string|null,
     *     note: string|null
     * }
     */
    private function forComponent(SpkComponent $component): array
    {
        if ($component->mscomponent_id === null) {
            return $this->result(
                self::REQUIRED_UNITS,
                0,
                null,
                'Master komponen belum terhubung',
            );
        }

        $color = filled($component->color) ? trim((string) $component->color) : null;

        $countsForColor = $this->componentStock->availableInStockCount(
            (int) $component->mscomponent_id,
            $color,
        );

        $availableUnits = (int) ($countsForColor['balance'] ?? 0);
        $balanceLength = (string) ($countsForColor['balanceLength'] ?? '0.00');

        $note = null;

        if ($availableUnits < self::REQUIRED_UNITS && filled($color)) {
            $anyColor = $this->componentStock->availableInStockCount(
                (int) $component->mscomponent_id,
                null,
            );
            $otherColorUnits = (int) ($anyColor['balance'] ?? 0);

            $note = $otherColorUnits >= self::REQUIRED_UNITS
                ? "Tidak ada stok untuk warna {$color}. Masih bisa alokasi dari stok warna lain."
                : "Tidak ada stok untuk warna {$color}";
        }

        return $this->result(
            self::REQUIRED_UNITS,
            $availableUnits,
            $balanceLength,
            $note,
        );
    }

    /**
     * @return array{
     *     status: 'available'|'unavailable',
     *     requiredUnits: int,
     *     availableUnits: int,
     *     balanceLength: string|null,
     *     note: string|null
     * }
     */
    private function result(
        int $requiredUnits,
        int $availableUnits,
        ?string $balanceLength,
        ?string $note,
    ): array {
        $isAvailable = $requiredUnits > 0 && $availableUnits >= $requiredUnits;

        return [
            'status' => $isAvailable ? self::STATUS_AVAILABLE : self::STATUS_UNAVAILABLE,
            'requiredUnits' => $requiredUnits,
            'availableUnits' => $availableUnits,
            'balanceLength' => $balanceLength,
            'note' => $note,
        ];
    }
}
