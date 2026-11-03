<?php

namespace App\Support;

use App\Models\DiamondCrtMatrix;
use App\Models\MsShape;
use App\Models\MsStone;
use App\Models\SpkStone;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

class SpkStoneStockChecker
{
    public const DOSSIER_MIN_CARAT_PER_PCS = 0.18;

    public const SOURCE_DOSSIER = 'dossier';

    public const SOURCE_MICRO = 'micro';

    public const STATUS_AVAILABLE = 'available';

    public const STATUS_UNAVAILABLE = 'unavailable';

    private const TOLERANCE = 0.0005;

    public function __construct(
        private readonly DiamondDossierInventory $dossierInventory,
        private readonly StoneLedger $stoneLedger,
        private readonly SpkStoneFulfillment $fulfillment,
    ) {}

    /**
     * Status stok per batu SPK, dikunci dengan line_id.
     *
     * Carat per butir >= 0.18 dicari di batu dossier (range dari Matrix CRT Dossier),
     * di bawahnya dicari di batu mikro berdasarkan bentuk dan ukuran.
     *
     * @param  Collection<int, SpkStone>  $stones
     * @return array<int, array{
     *     source: 'dossier'|'micro',
     *     status: 'available'|'unavailable',
     *     requiredPcs: int,
     *     availablePcs: int,
     *     note: string|null
     * }>
     */
    public function forStones(Collection $stones): array
    {
        if ($stones->isEmpty()) {
            return [];
        }

        $context = $this->loadContext($stones);
        $results = [];

        $allocationService = app(SpkStoneAllocationService::class);

        foreach ($stones as $stone) {
            $caratPerPcs = $this->caratPerPcs($stone);
            $shapeId = (int) $stone->shape_id;
            $requiredPcs = max(0, (int) ($stone->pcs ?? 0));
            $remainingPcs = max(0, $requiredPcs - $allocationService->allocatedPcs($stone));

            $dossierMatch = $caratPerPcs >= self::DOSSIER_MIN_CARAT_PER_PCS
                ? $this->resolveDossierMatch(
                    $stone,
                    $caratPerPcs,
                    $context['matrixByShape'][$shapeId] ?? null,
                )
                : null;
            $microMatch = $this->resolveMicroMatch(
                $stone,
                $context['microStonesByShape'][$shapeId] ?? null,
                $context['activePeriod'],
            );

            $dossierAvailable = $dossierMatch !== null && $dossierMatch['dossierRange'] !== null
                ? $this->dossierInventory->availableCountInCrtRange(
                    $shapeId,
                    $dossierMatch['dossierRange']->crt_min,
                    $dossierMatch['dossierRange']->crt_max,
                )
                : 0;
            $microAvailable = $microMatch['microStoneIds'] !== [] && $microMatch['periodId'] !== null
                ? $this->microAvailablePcs($microMatch['periodId'], $microMatch['microStoneIds'])
                : 0;

            $prefersDossier = $caratPerPcs >= self::DOSSIER_MIN_CARAT_PER_PCS;
            $primarySource = $prefersDossier
                ? self::SOURCE_DOSSIER
                : self::SOURCE_MICRO;
            $primaryMatch = $prefersDossier
                ? ($dossierMatch ?? $microMatch)
                : $microMatch;

            $availablePcs = 0;

            if ($remainingPcs > 0) {
                if ($dossierAvailable > 0) {
                    $availablePcs += $dossierAvailable;
                }

                if ($microAvailable > 0) {
                    $availablePcs += $microAvailable;
                }
            }

            $results[(int) $stone->line_id] = $this->result(
                $primarySource,
                $requiredPcs,
                $availablePcs,
                $primaryMatch['note'] ?? null,
            );
        }

        return $results;
    }

    /**
     * Status alokasi & pemesanan batu per baris SPK.
     *
     * @param  Collection<int, SpkStone>  $stones
     * @return array<int, array{
     *     status: string,
     *     label: string,
     *     allocatedPcs: int,
     *     nextAction: string|null,
     *     nextActionLabel: string|null
     * }>
     */
    public function allocationForStones(Collection $stones): array
    {
        if ($stones->isEmpty()) {
            return [];
        }

        $results = [];

        foreach ($stones as $stone) {
            $results[(int) $stone->line_id] = $this->fulfillment->resolve($stone);
        }

        return $results;
    }

    /**
     * @return array<int, list<array<string, mixed>>>
     */
    public function allocatedItemsForStones(Collection $stones): array
    {
        return app(SpkStoneAllocationService::class)->allocationsGroupedByLineId($stones);
    }

    /**
     * Daftar batu stok yang cocok dengan kebutuhan satu baris batu SPK.
     *
     * @return array{
     *     stock: array{source: 'dossier'|'micro', status: 'available'|'unavailable', requiredPcs: int, availablePcs: int, note: string|null},
     *     rows: list<array<string, int|string|null>>
     * }
     */
    /**
     * Cek stok dari atribut batu form (belum disimpan ke database).
     *
     * @return array{
     *     stock: array{source: 'dossier'|'micro', status: 'available'|'unavailable', requiredPcs: int, availablePcs: int, note: string|null},
     *     rows: list<array<string, int|string|null>>
     * }
     */
    public function matchingFromAttributes(
        int $shapeId,
        int $pcs,
        float $caratPerPcs,
        ?string $size = null,
    ): array {
        return $this->matchingStones($this->previewStone($shapeId, $pcs, $caratPerPcs, $size));
    }

    public function matchingStones(SpkStone $stone): array
    {
        $context = $this->loadContext(collect([$stone]));
        $dossier = $this->buildDossierAllocationPayload($stone, $context);
        $micro = $this->buildMicroAllocationPayload($stone, $context);
        $primary = $dossier ?? $micro ?? [
            'stock' => $this->result(
                self::SOURCE_DOSSIER,
                max(0, (int) ($stone->pcs ?? 0)),
                0,
                'Bentuk batu belum diisi.',
            ),
            'rows' => [],
        ];

        return $this->withAllocationMeta($stone, [
            'stock' => $primary['stock'],
            'rows' => $primary['rows'],
            'dossier' => $dossier,
            'micro' => $micro,
        ]);
    }

    /**
     * @param  array{
     *     matrixByShape: array<int|string, EloquentCollection<int, DiamondCrtMatrix>>,
     *     microStonesByShape: array<int|string, EloquentCollection<int, MsStone>>,
     *     activePeriod: array{id: int, label: string}|null
     * }  $context
     * @return array{
     *     stock: array{source: 'dossier', status: 'available'|'unavailable', requiredPcs: int, availablePcs: int, note: string|null},
     *     rows: list<array<string, bool|int|string|null>>
     * }|null
     */
    private function buildDossierAllocationPayload(SpkStone $stone, array $context): ?array
    {
        $caratPerPcs = $this->caratPerPcs($stone);
        $shapeId = (int) $stone->shape_id;

        if ($caratPerPcs < self::DOSSIER_MIN_CARAT_PER_PCS || $shapeId <= 0) {
            return null;
        }

        $match = $this->resolveDossierMatch(
            $stone,
            $caratPerPcs,
            $context['matrixByShape'][$shapeId] ?? null,
        );

        if ($match['dossierRange'] === null) {
            return [
                'stock' => $this->result($match['source'], $match['requiredPcs'], 0, $match['note']),
                'rows' => [],
            ];
        }

        $rows = $this->dossierAllocationRows(
            $this->dossierInventory->availableForShape($shapeId),
            $match['dossierRange'],
        );
        $matchingCount = count(array_filter(
            $rows,
            fn (array $row): bool => ($row['matchesSpkRequirement'] ?? false) === true,
        ));

        return [
            'stock' => $this->result($match['source'], $match['requiredPcs'], $matchingCount, $match['note']),
            'rows' => $this->sortAllocationRows($rows),
        ];
    }

    /**
     * @param  array{
     *     matrixByShape: array<int|string, EloquentCollection<int, DiamondCrtMatrix>>,
     *     microStonesByShape: array<int|string, EloquentCollection<int, MsStone>>,
     *     activePeriod: array{id: int, label: string}|null
     * }  $context
     * @return array{
     *     stock: array{source: 'micro', status: 'available'|'unavailable', requiredPcs: int, availablePcs: int, note: string|null},
     *     rows: list<array<string, bool|int|string|null>>
     * }|null
     */
    private function buildMicroAllocationPayload(SpkStone $stone, array $context): ?array
    {
        $shapeId = (int) $stone->shape_id;

        if ($shapeId <= 0) {
            return null;
        }

        $match = $this->resolveMicroMatch(
            $stone,
            $context['microStonesByShape'][$shapeId] ?? null,
            $context['activePeriod'],
        );

        $periodId = $match['periodId'];

        if ($match['microStoneIds'] === [] && $periodId === null) {
            return [
                'stock' => $this->result($match['source'], $match['requiredPcs'], 0, $match['note']),
                'rows' => [],
            ];
        }

        if ($periodId === null) {
            return [
                'stock' => $this->result($match['source'], $match['requiredPcs'], 0, $match['note']),
                'rows' => [],
            ];
        }

        $allMicroIds = ($context['microStonesByShape'][$shapeId] ?? collect())
            ->map(fn (MsStone $microStone): int => (int) $microStone->row_id)
            ->values()
            ->all();
        $matchedIds = $match['microStoneIds'] === []
            ? []
            : array_flip($match['microStoneIds']);
        $rows = $this->microAllocationRows(
            $this->stoneLedger->stockByStoneIds($periodId, $allMicroIds),
            $matchedIds,
        );
        $balance = array_sum(array_map(
            fn (array $row): float => ($row['matchesSpkRequirement'] ?? false) === true
                ? (float) ($row['availablePcs'] ?? $row['balancePcs'] ?? 0)
                : 0.0,
            $rows,
        ));

        return [
            'stock' => $this->result(
                $match['source'],
                $match['requiredPcs'],
                $this->wholePcs($balance),
                $match['note'],
            ),
            'rows' => $this->sortAllocationRows($rows),
        ];
    }

    /**
     * @return list<array<string, bool|int|string|null>>
     */
    public function dossierAllocationRowsForStone(SpkStone $stone): array
    {
        $payload = $this->buildDossierAllocationPayload(
            $stone,
            $this->loadContext(collect([$stone])),
        );

        return $payload['rows'] ?? [];
    }

    /**
     * @return list<array<string, bool|int|string|null>>
     */
    public function microAllocationRowsForStone(SpkStone $stone): array
    {
        $payload = $this->buildMicroAllocationPayload(
            $stone,
            $this->loadContext(collect([$stone])),
        );

        return $payload['rows'] ?? [];
    }

    /**
     * @param  array{
     *     stock: array{source: 'dossier'|'micro', status: 'available'|'unavailable', requiredPcs: int, availablePcs: int, note: string|null},
     *     rows: list<array<string, int|string|null>>,
     *     dossier?: array{stock: array<string, mixed>, rows: list<array<string, mixed>>}|null,
     *     micro?: array{stock: array<string, mixed>, rows: list<array<string, mixed>>}|null
     * }  $payload
     * @return array{
     *     stock: array{source: 'dossier'|'micro', status: 'available'|'unavailable', requiredPcs: int, availablePcs: int, note: string|null},
     *     rows: list<array<string, int|string|null>>,
     *     dossier: array{stock: array<string, mixed>, rows: list<array<string, mixed>>}|null,
     *     micro: array{stock: array<string, mixed>, rows: list<array<string, mixed>>}|null,
     *     allocations: list<array<string, mixed>>,
     *     allocatedPcs: int
     * }
     */
    private function withAllocationMeta(SpkStone $stone, array $payload): array
    {
        $allocationService = app(SpkStoneAllocationService::class);
        $allocatedPcs = $allocationService->allocatedPcs($stone);

        if (isset($payload['stock']) && is_array($payload['stock'])) {
            $payload['stock'] = $this->stockResultForRemaining($payload['stock'], $allocatedPcs);
        }

        if (isset($payload['dossier']['stock']) && is_array($payload['dossier']['stock'])) {
            $payload['dossier']['stock'] = $this->stockResultForRemaining(
                $payload['dossier']['stock'],
                $allocatedPcs,
            );
        }

        if (isset($payload['micro']['stock']) && is_array($payload['micro']['stock'])) {
            $payload['micro']['stock'] = $this->stockResultForRemaining(
                $payload['micro']['stock'],
                $allocatedPcs,
            );
        }

        return [
            ...$payload,
            'allocations' => $allocationService->allocationsForStone($stone),
            'allocatedPcs' => $allocatedPcs,
        ];
    }

    /**
     * @param  array{source: 'dossier'|'micro', status: 'available'|'unavailable', requiredPcs: int, availablePcs: int, note: string|null}  $stock
     * @return array{source: 'dossier'|'micro', status: 'available'|'unavailable', requiredPcs: int, availablePcs: int, note: string|null}
     */
    private function stockResultForRemaining(array $stock, int $allocatedPcs): array
    {
        $remainingRequired = max(0, (int) ($stock['requiredPcs'] ?? 0) - $allocatedPcs);
        $availablePcs = (int) ($stock['availablePcs'] ?? 0);
        $isAvailable = $remainingRequired > 0 && $availablePcs > 0;

        return [
            ...$stock,
            'status' => $isAvailable ? self::STATUS_AVAILABLE : self::STATUS_UNAVAILABLE,
        ];
    }

    /**
     * @param  list<int>  $microStoneIds
     */
    private function microAvailablePcs(int $periodId, array $microStoneIds): int
    {
        $balances = $this->stoneLedger->balancePcsByStone($periodId, $microStoneIds);
        $reservedMap = app(SpkStoneAllocationService::class)->microReservedPcsByStone($periodId);
        $available = 0.0;

        foreach ($microStoneIds as $microStoneId) {
            $available += max(
                0,
                (float) ($balances[$microStoneId] ?? 0) - (float) ($reservedMap[$microStoneId] ?? 0),
            );
        }

        return $this->wholePcs($available);
    }

    /**
     * @param  list<array<string, int|string|null>>  $rows
     * @return list<array<string, bool|int|string|null>>
     */
    private function dossierAllocationRows(array $rows, ?DiamondCrtMatrix $range): array
    {
        return array_map(function (array $row) use ($range): array {
            $crt = filled($row['crt'] ?? null)
                ? (float) $row['crt']
                : null;
            $matches = $range !== null
                && $crt !== null
                && $crt >= (float) $range->crt_min - self::TOLERANCE
                && $crt <= (float) $range->crt_max + self::TOLERANCE;

            return [
                ...$row,
                'matchesSpkRequirement' => $matches,
            ];
        }, $rows);
    }

    /**
     * @param  list<array<string, int|string|null>>  $rows
     * @param  array<int, int>  $matchedMicroIds
     * @return list<array<string, bool|int|string|null>>
     */
    private function microAllocationRows(array $rows, array $matchedMicroIds): array
    {
        return array_map(function (array $row) use ($matchedMicroIds): array {
            $availablePcs = (float) ($row['availablePcs'] ?? $row['balancePcs'] ?? 0);
            $matches = isset($matchedMicroIds[(int) ($row['id'] ?? 0)])
                && $this->wholePcs($availablePcs) > 0;

            if (filled($row['balanceCrt'] ?? null)) {
                $row['balanceCrt'] = number_format((float) $row['balanceCrt'], 3, '.', '');
            }

            return [
                ...$row,
                'matchesSpkRequirement' => $matches,
            ];
        }, $rows);
    }

    /**
     * @param  list<array<string, bool|int|string|null>>  $rows
     * @return list<array<string, bool|int|string|null>>
     */
    private function sortAllocationRows(array $rows): array
    {
        usort($rows, function (array $left, array $right): int {
            $leftMatch = ($left['matchesSpkRequirement'] ?? false) === true ? 1 : 0;
            $rightMatch = ($right['matchesSpkRequirement'] ?? false) === true ? 1 : 0;

            if ($leftMatch !== $rightMatch) {
                return $rightMatch <=> $leftMatch;
            }

            return ((int) ($right['id'] ?? 0)) <=> ((int) ($left['id'] ?? 0));
        });

        return array_values($rows);
    }

    /**
     * @param  Collection<int, SpkStone>  $stones
     * @return array{
     *     matrixByShape: array<int|string, EloquentCollection<int, DiamondCrtMatrix>>,
     *     microStonesByShape: array<int|string, EloquentCollection<int, MsStone>>,
     *     activePeriod: array{id: int, label: string}|null
     * }
     */
    private function loadContext(Collection $stones): array
    {
        $shapeIds = $stones->pluck('shape_id')->filter()->map(fn (mixed $id): int => (int) $id)->unique()->values()->all();

        return [
            'matrixByShape' => DiamondCrtMatrix::query()
                ->whereIn('shape_id', $shapeIds)
                ->orderBy('crt_min')
                ->get()
                ->groupBy('shape_id')
                ->all(),
            'microStonesByShape' => MsStone::query()
                ->notDeleted()
                ->whereIn('shape_id', $shapeIds)
                ->get(['row_id', 'shape_id', 'stone_size'])
                ->groupBy('shape_id')
                ->all(),
            'activePeriod' => $this->stoneLedger->activePeriod(),
        ];
    }

    /**
     * @param  array{
     *     matrixByShape: array<int|string, EloquentCollection<int, DiamondCrtMatrix>>,
     *     microStonesByShape: array<int|string, EloquentCollection<int, MsStone>>,
     *     activePeriod: array{id: int, label: string}|null
     * }  $context
     * @return array{
     *     source: 'dossier'|'micro',
     *     requiredPcs: int,
     *     note: string|null,
     *     dossierRange: DiamondCrtMatrix|null,
     *     microStoneIds: list<int>,
     *     periodId: int|null
     * }
     */
    private function resolveMatch(SpkStone $stone, array $context): array
    {
        $caratPerPcs = $this->caratPerPcs($stone);
        $shapeId = (int) $stone->shape_id;

        return $caratPerPcs >= self::DOSSIER_MIN_CARAT_PER_PCS
            ? $this->resolveDossierMatch($stone, $caratPerPcs, $context['matrixByShape'][$shapeId] ?? null)
            : $this->resolveMicroMatch($stone, $context['microStonesByShape'][$shapeId] ?? null, $context['activePeriod']);
    }

    /**
     * @param  Collection<int, DiamondCrtMatrix>|null  $ranges
     * @return array{source: 'dossier', requiredPcs: int, note: string|null, dossierRange: DiamondCrtMatrix|null, microStoneIds: list<int>, periodId: int|null}
     */
    private function resolveDossierMatch(SpkStone $stone, float $caratPerPcs, ?Collection $ranges): array
    {
        $match = [
            'source' => self::SOURCE_DOSSIER,
            'requiredPcs' => (int) ($stone->pcs ?? 0),
            'note' => null,
            'dossierRange' => null,
            'microStoneIds' => [],
            'periodId' => null,
        ];

        if (blank($stone->shape_id)) {
            return [...$match, 'note' => 'Bentuk batu belum diisi.'];
        }

        $range = $ranges?->first(fn (DiamondCrtMatrix $range): bool => $caratPerPcs >= (float) $range->crt_min - self::TOLERANCE
            && $caratPerPcs <= (float) $range->crt_max + self::TOLERANCE);

        if ($range === null) {
            return [...$match, 'note' => 'Bentuk dan CRT ini tidak ada di Matrix CRT Dossier.'];
        }

        $shapeName = $stone->shape->name ?? "Shape #{$stone->shape_id}";

        return [
            ...$match,
            'note' => "{$shapeName}, range {$range->crt_min} – {$range->crt_max} ct",
            'dossierRange' => $range,
        ];
    }

    /**
     * @param  Collection<int, MsStone>|null  $microStones
     * @param  array{id: int, label: string}|null  $activePeriod
     * @return array{source: 'micro', requiredPcs: int, note: string|null, dossierRange: DiamondCrtMatrix|null, microStoneIds: list<int>, periodId: int|null}
     */
    private function resolveMicroMatch(SpkStone $stone, ?Collection $microStones, ?array $activePeriod): array
    {
        $match = [
            'source' => self::SOURCE_MICRO,
            'requiredPcs' => (int) ($stone->pcs ?? 0),
            'note' => null,
            'dossierRange' => null,
            'microStoneIds' => [],
            'periodId' => null,
        ];
        $spkSize = trim((string) ($stone->size ?? ''));

        if (blank($stone->shape_id) || $spkSize === '' || $spkSize === '-') {
            return [...$match, 'note' => 'Bentuk atau ukuran batu belum diisi.'];
        }

        if ($activePeriod === null) {
            return [...$match, 'note' => 'Periode stok batu aktif tidak ditemukan.'];
        }

        $matchedStoneIds = array_values(($microStones ?? collect())
            ->filter(fn (MsStone $microStone): bool => $this->sizeMatches($spkSize, $microStone->stone_size))
            ->map(fn (MsStone $microStone): int => (int) $microStone->row_id)
            ->all());

        if ($matchedStoneIds === []) {
            $note = 'Batu mikro dengan bentuk dan ukuran ini tidak ditemukan.';

            if (($microStones ?? collect())->isEmpty()) {
                return [...$match, 'note' => $note];
            }

            return [
                ...$match,
                'note' => $note,
                'periodId' => $activePeriod['id'],
            ];
        }

        return [
            ...$match,
            'microStoneIds' => $matchedStoneIds,
            'periodId' => $activePeriod['id'],
        ];
    }

    /**
     * @template TSource of 'dossier'|'micro'
     *
     * @param  TSource  $source
     * @return array{source: TSource, status: 'available'|'unavailable', requiredPcs: int, availablePcs: int, note: string|null}
     */
    private function result(string $source, int $requiredPcs, int $availablePcs, ?string $note): array
    {
        $isAvailable = $requiredPcs > 0 && $availablePcs > 0;

        return [
            'source' => $source,
            'status' => $isAvailable ? self::STATUS_AVAILABLE : self::STATUS_UNAVAILABLE,
            'requiredPcs' => $requiredPcs,
            'availablePcs' => $availablePcs,
            'note' => $note,
        ];
    }

    private function wholePcs(float $balance): int
    {
        return (int) floor(max(0, $balance) + self::TOLERANCE);
    }

    private function caratPerPcs(SpkStone $stone): float
    {
        $pcs = (int) ($stone->pcs ?? 0);

        return $pcs > 0 ? round((float) ($stone->carat ?? 0) / $pcs, 3) : 0.0;
    }

    private function previewStone(
        int $shapeId,
        int $pcs,
        float $caratPerPcs,
        ?string $size = null,
    ): SpkStone {
        $totalCarat = $pcs > 0 ? round($caratPerPcs * $pcs, 3) : 0.0;

        $stone = new SpkStone([
            'shape_id' => $shapeId,
            'pcs' => $pcs,
            'carat' => number_format($totalCarat, 3, '.', ''),
            'size' => $size,
        ]);
        $stone->line_id = 0;

        $shape = MsShape::query()->notDeleted()->find($shapeId);
        $stone->setRelation('shape', $shape);

        return $stone;
    }

    /**
     * Ukuran SPK berupa angka tunggal ("2.15") atau PxL ("4,7x3,3").
     * Ukuran batu mikro berupa angka tunggal ("2.90") atau rentang ("2.4 - 2.50").
     */
    private function sizeMatches(string $spkSize, ?string $stoneSize): bool
    {
        $spkDimensions = $this->parseDimensions($spkSize);
        $normalizedStoneSize = $this->normalizeSize((string) ($stoneSize ?? ''));

        if ($spkDimensions === null || $normalizedStoneSize === '') {
            return false;
        }

        if (count($spkDimensions) > 1) {
            $stoneDimensions = $this->parseDimensions($normalizedStoneSize);

            if ($stoneDimensions === null || count($stoneDimensions) !== count($spkDimensions)) {
                return false;
            }

            foreach ($spkDimensions as $index => $dimension) {
                if (abs($dimension - $stoneDimensions[$index]) > self::TOLERANCE) {
                    return false;
                }
            }

            return true;
        }

        $bounds = explode('-', $normalizedStoneSize);

        if (count($bounds) > 2 || ! is_numeric($bounds[0]) || ! is_numeric($bounds[count($bounds) - 1])) {
            return false;
        }

        $size = $spkDimensions[0];

        return $size >= (float) $bounds[0] - self::TOLERANCE
            && $size <= (float) $bounds[count($bounds) - 1] + self::TOLERANCE;
    }

    /**
     * @return list<float>|null
     */
    private function parseDimensions(string $size): ?array
    {
        $parts = explode('x', $this->normalizeSize($size));
        $dimensions = [];

        foreach ($parts as $part) {
            if (! is_numeric($part)) {
                return null;
            }

            $dimensions[] = (float) $part;
        }

        return $dimensions;
    }

    private function normalizeSize(string $size): string
    {
        $normalized = strtolower(str_replace(',', '.', $size));
        $normalized = str_replace('mm', '', $normalized);

        return (string) preg_replace('/\s+/', '', $normalized);
    }
}
