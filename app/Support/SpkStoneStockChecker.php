<?php

namespace App\Support;

use App\Models\DiamondCrtMatrix;
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

        foreach ($stones as $stone) {
            $match = $this->resolveMatch($stone, $context);

            $availablePcs = match (true) {
                $match['dossierRange'] !== null => $this->dossierInventory->availableCountInCrtRange(
                    (int) $stone->shape_id,
                    $match['dossierRange']->crt_min,
                    $match['dossierRange']->crt_max,
                ),
                $match['microStoneIds'] !== [] && $match['periodId'] !== null => $this->wholePcs(array_sum(
                    $this->stoneLedger->balancePcsByStone($match['periodId'], $match['microStoneIds']),
                )),
                default => 0,
            };

            $results[(int) $stone->line_id] = $this->result($match['source'], $match['requiredPcs'], $availablePcs, $match['note']);
        }

        return $results;
    }

    /**
     * Daftar batu stok yang cocok dengan kebutuhan satu baris batu SPK.
     *
     * @return array{
     *     stock: array{source: 'dossier'|'micro', status: 'available'|'unavailable', requiredPcs: int, availablePcs: int, note: string|null},
     *     rows: list<array<string, int|string|null>>
     * }
     */
    public function matchingStones(SpkStone $stone): array
    {
        $match = $this->resolveMatch($stone, $this->loadContext(collect([$stone])));

        if ($match['dossierRange'] !== null) {
            $rows = $this->dossierInventory->availableInCrtRange(
                (int) $stone->shape_id,
                $match['dossierRange']->crt_min,
                $match['dossierRange']->crt_max,
            );

            return [
                'stock' => $this->result($match['source'], $match['requiredPcs'], count($rows), $match['note']),
                'rows' => $rows,
            ];
        }

        if ($match['microStoneIds'] !== [] && $match['periodId'] !== null) {
            $rows = $this->stoneLedger->stockByStoneIds($match['periodId'], $match['microStoneIds']);
            $balance = array_sum(array_map(fn (array $row): float => (float) $row['balancePcs'], $rows));

            return [
                'stock' => $this->result($match['source'], $match['requiredPcs'], $this->wholePcs($balance), $match['note']),
                'rows' => $rows,
            ];
        }

        return [
            'stock' => $this->result($match['source'], $match['requiredPcs'], 0, $match['note']),
            'rows' => [],
        ];
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
            return [...$match, 'note' => 'Batu mikro dengan bentuk dan ukuran ini tidak ditemukan.'];
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
        $isAvailable = $requiredPcs > 0 && $availablePcs >= $requiredPcs;

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
