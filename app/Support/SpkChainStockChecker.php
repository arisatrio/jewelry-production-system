<?php

namespace App\Support;

use App\Models\SpkChain;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class SpkChainStockChecker
{
    public const STATUS_AVAILABLE = 'available';

    public const STATUS_UNAVAILABLE = 'unavailable';

    private const REQUIRED_UNITS = 1;

    public function __construct(
        private readonly ChainStockService $chainStock,
        private readonly SpkChainFulfillment $fulfillment,
    ) {}

    /**
     * Status stok per baris rantai SPK, dikunci dengan line_id.
     *
     * @param  Collection<int, SpkChain>  $chains
     * @return array<int, array{
     *     status: 'available'|'unavailable',
     *     requiredUnits: int,
     *     availableUnits: int,
     *     balanceLength: string|null,
     *     note: string|null
     * }>
     */
    public function forChains(Collection $chains): array
    {
        if ($chains->isEmpty()) {
            return [];
        }

        $results = [];

        foreach ($chains as $chain) {
            $results[(int) $chain->line_id] = $this->forChain($chain);
        }

        return $results;
    }

    /**
     * Status alokasi & fulfillment rantai per baris SPK.
     *
     * @param  Collection<int, SpkChain>  $chains
     * @return array<int, array{
     *     status: string,
     *     label: string,
     *     allocatedUnits: int,
     *     nextAction: string|null,
     *     nextActionLabel: string|null
     * }>
     */
    public function allocationForChains(Collection $chains): array
    {
        if ($chains->isEmpty()) {
            return [];
        }

        $results = [];

        foreach ($chains as $chain) {
            $results[(int) $chain->line_id] = $this->fulfillment->resolve($chain);
        }

        return $results;
    }

    /**
     * Unit chain terakhir yang sudah dikeluarkan ke SPK (alokasi aktual).
     *
     * @param  Collection<int, SpkChain>  $chains
     * @return array<int, array{
     *     unitNo: string|null,
     *     name: string,
     *     color: string,
     *     size: string,
     *     chainLength: string,
     *     weight: string|null,
     *     id: int,
     *     allocatedDate: string|null,
     *     allocatedBy: string|null
     * }>
     */
    public function issuedUnitsForChains(int $spkRowId, Collection $chains): array
    {
        if ($chains->isEmpty() || ! $this->chainStock->tableAvailable()) {
            return [];
        }

        $results = [];

        foreach ($chains as $chain) {
            if ($chain->mschain_id === null) {
                continue;
            }

            $unit = $this->chainStock->latestIssuedUnitForSpkChain(
                $spkRowId,
                (int) $chain->mschain_id,
            );

            if ($unit === null) {
                continue;
            }

            $results[(int) $chain->line_id] = $this->formatIssuedUnit(
                $this->chainStock->toUnitRow($unit),
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
     *     chainLength: string,
     *     weight: string|null,
     *     allocatedDate: string|null,
     *     allocatedBy: string|null
     * }
     */
    private function formatIssuedUnit(array $unitRow): array
    {
        $unitColor = trim((string) ($unitRow['color'] ?? ''));
        $unitLength = $this->formatWholeMeasurement($unitRow['chainLength'] ?? '-');
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
            'chainLength' => $unitLength,
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
    private function forChain(SpkChain $chain): array
    {
        if ($chain->mschain_id === null) {
            return $this->result(
                self::REQUIRED_UNITS,
                0,
                null,
                'Master chain belum terhubung',
            );
        }

        $color = filled($chain->color) ? trim((string) $chain->color) : null;

        $countsForColor = $this->chainStock->availableInStockCount(
            (int) $chain->mschain_id,
            $color,
        );

        $availableUnits = (int) ($countsForColor['balance'] ?? 0);
        $balanceLength = (string) ($countsForColor['balanceLength'] ?? '0.00');

        $note = null;

        if ($availableUnits < self::REQUIRED_UNITS && filled($color)) {
            $anyColor = $this->chainStock->availableInStockCount(
                (int) $chain->mschain_id,
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
