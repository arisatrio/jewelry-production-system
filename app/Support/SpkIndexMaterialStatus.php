<?php

namespace App\Support;

use App\Models\SpkComponent;
use App\Models\SpkStone;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SpkIndexMaterialStatus
{
    public const STOCK_AVAILABLE = 'Stok Ada';

    public const STOCK_UNAVAILABLE = 'Stok Tidak ada';

    public const FLOW_WAITING_ALLOCATION = 'Menunggu Alokasi';

    public const FLOW_NOT_ORDERED = 'Belum diorder';

    public const FLOW_ORDERING = 'Sedang diorder';

    public const FLOW_ORDERED = 'Selesai';

    public const FLOW_SHIPPING = 'Sedang Dikirim Supplier';

    public const FLOW_ALLOCATED = 'Sudah dialokasikan';

    public const FLOW_SPK_DIAMOND = 'SPK Diamond';

    /** @deprecated Use composite stoneStatus labels from summariesForRowIds */
    public const STONE_WAITING = 'Menunggu dialokasikan';

    /** @deprecated Use composite stoneStatus labels from summariesForRowIds */
    public const STONE_ALLOCATED = 'Sudah dialokasikan';

    /** @deprecated Use composite stoneStatus labels from summariesForRowIds */
    public const STONE_SPK_DIAMOND = 'SPK Diamond';

    /** @deprecated Use composite stoneStatus labels from summariesForRowIds */
    public const STONE_ORDERING = 'Sedang diorder';

    /** @deprecated Use composite stoneStatus labels from summariesForRowIds */
    public const STONE_ORDERED = 'Selesai';

    /** @deprecated Use composite stoneStatus labels from summariesForRowIds */
    public const STONE_SHIPPING = 'Sedang Dikirim Supplier';

    public const COMPONENT_WAITING = 'Menunggu dialokasikan';

    public const COMPONENT_ALLOCATED = 'Sudah dialokasikan';

    public static function stonePartialAllocatedLabel(int $allocatedLines, int $totalLines): string
    {
        return "{$allocatedLines} dari {$totalLines} batu sudah dialokasikan";
    }

    public static function formatStoneIndexStatus(string $stockLabel, string $flowLabel): string
    {
        return "{$stockLabel} | {$flowLabel}";
    }

    public function __construct(
        private readonly SpkStoneAllocationService $stoneAllocation,
        private readonly SpkComponentFulfillment $componentFulfillment,
        private readonly SpkStoneStockChecker $stoneStockChecker,
    ) {}

    /**
     * @param  list<int>  $rowIds
     * @return array<int, array{stoneIndexStatus: array<string, mixed>|null, componentStatus: string|null}>
     */
    public function summariesForRowIds(array $rowIds): array
    {
        $rowIds = array_values(array_unique(array_filter(
            array_map(static fn (mixed $id): int => (int) $id, $rowIds),
            static fn (int $id): bool => $id > 0,
        )));

        $empty = ['stoneIndexStatus' => null, 'componentStatus' => null];

        if ($rowIds === []) {
            return [];
        }

        $result = array_fill_keys($rowIds, $empty);

        $stones = SpkStone::query()
            ->notDeleted()
            ->whereIn('row_id', $rowIds)
            ->with('shape')
            ->get(['line_id', 'row_id', 'pcs', 'carat', 'shape_id', 'size', 'fulfillment_status']);

        $stockByLineId = $this->stoneStockChecker->forStones($stones);

        $components = SpkComponent::query()
            ->notDeleted()
            ->whereIn('row_id', $rowIds)
            ->get(['line_id', 'row_id', 'mscomponent_id', 'fulfillment_status']);

        $allocatedPcsByLineId = $this->allocatedPcsByLineId(
            $stones->pluck('line_id')->map(static fn (mixed $id): int => (int) $id)->all(),
        );

        $spkDiamondByRowId = $this->spkDiamondDocumentByRowId($rowIds);

        /** @var Collection<int, Collection<int, SpkStone>> $stonesByRowId */
        $stonesByRowId = $stones->groupBy(static fn (SpkStone $stone): int => (int) $stone->row_id);

        /** @var Collection<int, Collection<int, SpkComponent>> $componentsByRowId */
        $componentsByRowId = $components->groupBy(static fn (SpkComponent $component): int => (int) $component->row_id);

        foreach ($rowIds as $rowId) {
            $stoneLines = $stonesByRowId->get($rowId, collect());

            if ($stoneLines->isNotEmpty()) {
                $result[$rowId]['stoneIndexStatus'] = $this->summarizeStoneIndexStatus(
                    $stoneLines,
                    $allocatedPcsByLineId,
                    $spkDiamondByRowId[$rowId] ?? false,
                    $stockByLineId,
                );
            }

            $componentLines = $componentsByRowId->get($rowId, collect());

            if ($componentLines->isNotEmpty()) {
                $result[$rowId]['componentStatus'] = $this->summarizeComponentStatus($componentLines);
            }
        }

        return $result;
    }

    /**
     * @param  Collection<int, SpkStone>  $stones
     * @param  array<int, int>  $allocatedPcsByLineId
     * @param  array<int, array{source: string, status: string, requiredPcs: int, availablePcs: int, note: string|null}>  $stockByLineId
     * @return array{
     *     stock: array{source: string, status: string, requiredPcs: int, availablePcs: int, note: string|null}|null,
     *     allocation: array{status: string, label: string, allocatedPcs: int}
     * }
     */
    private function summarizeStoneIndexStatus(
        Collection $stones,
        array $allocatedPcsByLineId,
        bool $hasSpkDiamondDocument,
        array $stockByLineId,
    ): array {
        $stockAvailable = $this->stockLabelForSpk($stones, $stockByLineId, $allocatedPcsByLineId)
            === self::STOCK_AVAILABLE;
        $allocation = $this->aggregateAllocation(
            $stones,
            $allocatedPcsByLineId,
            $hasSpkDiamondDocument,
        );

        $allocated = $allocation['status'] === 'allocated'
            && $allocation['allocatedPcs'] > 0;
        $showStock = ! ($allocated && ! $stockAvailable);

        $firstStock = null;

        foreach ($stones as $stone) {
            $lineStock = $stockByLineId[(int) $stone->line_id] ?? null;

            if (is_array($lineStock)) {
                $firstStock = $lineStock;

                break;
            }
        }

        $stockPayload = null;

        if ($showStock) {
            $stockPayload = [
                'source' => (string) ($firstStock['source'] ?? SpkStoneStockChecker::SOURCE_MICRO),
                'status' => $stockAvailable
                    ? SpkStoneStockChecker::STATUS_AVAILABLE
                    : SpkStoneStockChecker::STATUS_UNAVAILABLE,
                'requiredPcs' => (int) ($firstStock['requiredPcs'] ?? 0),
                'availablePcs' => (int) ($firstStock['availablePcs'] ?? 0),
                'note' => $firstStock['note'] ?? null,
            ];
        }

        return [
            'stock' => $stockPayload,
            'allocation' => $allocation,
        ];
    }

    /**
     * @param  Collection<int, SpkStone>  $stones
     * @param  array<int, int>  $allocatedPcsByLineId
     * @return array{status: string, label: string, allocatedPcs: int}
     */
    private function aggregateAllocation(
        Collection $stones,
        array $allocatedPcsByLineId,
        bool $hasSpkDiamondDocument,
    ): array {
        $lineStatuses = $stones->map(function (SpkStone $stone) use ($allocatedPcsByLineId): string {
            $lineId = (int) $stone->line_id;
            $allocatedPcs = $allocatedPcsByLineId[$lineId] ?? 0;
            $stored = $this->normalizeStoneStoredStatus($stone->fulfillment_status);

            return $this->effectiveStoneStatus($stored, $allocatedPcs);
        });

        $allocatedPcs = $this->summarizedAllocatedPcs($stones, $allocatedPcsByLineId);

        if ($lineStatuses->contains(SpkStoneFulfillment::SHIPPING)) {
            return [
                'status' => 'shipping',
                'label' => SpkStoneFulfillment::label(SpkStoneFulfillment::SHIPPING),
                'allocatedPcs' => $allocatedPcs,
            ];
        }

        if ($lineStatuses->contains(SpkStoneFulfillment::ORDERING)) {
            return [
                'status' => 'ordering',
                'label' => SpkStoneFulfillment::label(SpkStoneFulfillment::ORDERING),
                'allocatedPcs' => $allocatedPcs,
            ];
        }

        if ($lineStatuses->contains(SpkStoneFulfillment::ORDERED)) {
            return [
                'status' => 'ordered',
                'label' => SpkStoneFulfillment::label(SpkStoneFulfillment::ORDERED),
                'allocatedPcs' => $allocatedPcs,
            ];
        }

        $allFullyAllocated = $stones->every(function (SpkStone $stone) use ($allocatedPcsByLineId): bool {
            return $this->lineFullyAllocated($stone, $allocatedPcsByLineId);
        });

        if ($allFullyAllocated) {
            return [
                'status' => 'allocated',
                'label' => $hasSpkDiamondDocument
                    ? self::FLOW_SPK_DIAMOND
                    : SpkStoneFulfillment::label(SpkStoneFulfillment::ALLOCATED),
                'allocatedPcs' => $allocatedPcs,
            ];
        }

        $totalLines = $stones->count();
        $fullyAllocatedLines = $stones->filter(function (SpkStone $stone) use ($allocatedPcsByLineId): bool {
            return $this->lineFullyAllocated($stone, $allocatedPcsByLineId);
        })->count();

        if ($fullyAllocatedLines > 0 && $fullyAllocatedLines < $totalLines) {
            return [
                'status' => 'partial',
                'label' => self::stonePartialAllocatedLabel($fullyAllocatedLines, $totalLines),
                'allocatedPcs' => $allocatedPcs,
            ];
        }

        return [
            'status' => 'not_allocated',
            'label' => SpkStoneFulfillment::label(SpkStoneFulfillment::NOT_ALLOCATED),
            'allocatedPcs' => $allocatedPcs,
        ];
    }

    /**
     * @param  Collection<int, SpkStone>  $stones
     * @param  array<int, int>  $allocatedPcsByLineId
     */
    private function summarizedAllocatedPcs(
        Collection $stones,
        array $allocatedPcsByLineId,
    ): int {
        return (int) $stones->sum(function (SpkStone $stone) use ($allocatedPcsByLineId): int {
            $requiredPcs = max(0, (int) ($stone->pcs ?? 0));
            $allocatedPcs = $allocatedPcsByLineId[(int) $stone->line_id] ?? 0;

            if ($requiredPcs <= 0) {
                return max(0, $allocatedPcs);
            }

            return min($allocatedPcs, $requiredPcs);
        });
    }

    /**
     * @param  Collection<int, SpkStone>  $stones
     * @param  array<int, array{source: string, status: string, requiredPcs: int, availablePcs: int, note: string|null}>  $stockByLineId
     * @param  array<int, int>  $allocatedPcsByLineId
     */
    private function stockLabelForSpk(
        Collection $stones,
        array $stockByLineId,
        array $allocatedPcsByLineId,
    ): string {
        if ($stones->every(fn (SpkStone $stone): bool => $this->lineFullyAllocated($stone, $allocatedPcsByLineId))) {
            return self::STOCK_AVAILABLE;
        }

        $hasAvailable = $stones->contains(function (SpkStone $stone) use ($stockByLineId): bool {
            $stock = $stockByLineId[(int) $stone->line_id] ?? null;

            return is_array($stock)
                && ($stock['status'] ?? '') === SpkStoneStockChecker::STATUS_AVAILABLE;
        });

        return $hasAvailable ? self::STOCK_AVAILABLE : self::STOCK_UNAVAILABLE;
    }

    /**
     * @param  array<int, int>  $allocatedPcsByLineId
     */
    private function lineFullyAllocated(SpkStone $stone, array $allocatedPcsByLineId): bool
    {
        $requiredPcs = max(0, (int) ($stone->pcs ?? 0));
        $allocatedPcs = $allocatedPcsByLineId[(int) $stone->line_id] ?? 0;

        if ($requiredPcs <= 0) {
            return $allocatedPcs > 0;
        }

        return $allocatedPcs >= $requiredPcs;
    }

    /**
     * @param  Collection<int, SpkComponent>  $components
     */
    private function summarizeComponentStatus(Collection $components): string
    {
        $allAllocated = $components->every(function (SpkComponent $component): bool {
            $allocation = $this->componentFulfillment->resolve($component);

            return $allocation['allocatedUnits'] > 0
                && ! in_array($allocation['status'], [
                    SpkComponentFulfillment::NOT_ALLOCATED,
                    SpkComponentFulfillment::UNLINKED,
                ], true);
        });

        return $allAllocated
            ? self::COMPONENT_ALLOCATED
            : self::COMPONENT_WAITING;
    }

    /**
     * @param  list<int>  $lineIds
     * @return array<int, int>
     */
    private function allocatedPcsByLineId(array $lineIds): array
    {
        $lineIds = array_values(array_unique(array_filter(
            array_map(static fn (mixed $id): int => (int) $id, $lineIds),
            static fn (int $id): bool => $id > 0,
        )));

        if ($lineIds === [] || ! $this->stoneAllocation->tableAvailable()) {
            return [];
        }

        $totals = array_fill_keys($lineIds, 0);

        $rows = DB::connection('third')
            ->table(SpkStoneAllocationService::TABLE)
            ->whereIn('line_id', $lineIds)
            ->where('is_deleted', 0)
            ->where('status', SpkStoneAllocationService::STATUS_RESERVED)
            ->get(['line_id', 'source', 'reserved_pcs']);

        foreach ($rows as $row) {
            $lineId = (int) $row->line_id;

            if ((string) $row->source === SpkStoneAllocationService::SOURCE_DOSSIER) {
                $totals[$lineId] = ($totals[$lineId] ?? 0) + 1;

                continue;
            }

            $totals[$lineId] = ($totals[$lineId] ?? 0) + (int) round((float) ($row->reserved_pcs ?? 0));
        }

        return $totals;
    }

    /**
     * @param  list<int>  $rowIds
     * @return array<int, bool>
     */
    private function spkDiamondDocumentByRowId(array $rowIds): array
    {
        $flags = array_fill_keys($rowIds, false);
        $schema = Schema::connection('third');

        if ($schema->hasTable('spk_diamond')) {
            $hasDeleted = $schema->hasColumn('spk_diamond', 'is_deleted');
            $spkColumn = $schema->hasColumn('spk_diamond', 'spk_row_id')
                ? 'spk_row_id'
                : ($schema->hasColumn('spk_diamond', 'row_id') ? 'row_id' : null);

            if ($spkColumn !== null) {
                $query = DB::connection('third')->table('spk_diamond')->whereIn($spkColumn, $rowIds);

                if ($hasDeleted) {
                    $query->where('is_deleted', 0);
                }

                foreach ($query->pluck($spkColumn) as $spkRowId) {
                    $flags[(int) $spkRowId] = true;
                }
            }
        }

        if ($schema->hasColumn('spk', 'spk_diamond_doc_no')) {
            DB::connection('third')
                ->table('spk')
                ->whereIn('row_id', $rowIds)
                ->where('is_deleted', 0)
                ->whereNotNull('spk_diamond_doc_no')
                ->where('spk_diamond_doc_no', '!=', '')
                ->pluck('row_id')
                ->each(function (mixed $rowId) use (&$flags): void {
                    $flags[(int) $rowId] = true;
                });
        }

        return $flags;
    }

    private function normalizeStoneStoredStatus(mixed $value): string
    {
        $status = is_string($value) ? trim($value) : '';

        if ($status === '' || ! in_array($status, SpkStoneFulfillment::orderedStatuses(), true)) {
            return SpkStoneFulfillment::NOT_ALLOCATED;
        }

        return $status;
    }

    private function effectiveStoneStatus(string $stored, int $allocatedPcs): string
    {
        if ($allocatedPcs > 0) {
            return $stored === SpkStoneFulfillment::ORDERING
                || $stored === SpkStoneFulfillment::ORDERED
                ? $stored
                : SpkStoneFulfillment::ALLOCATED;
        }

        if ($stored === SpkStoneFulfillment::ORDERING
            || $stored === SpkStoneFulfillment::ORDERED
            || $stored === SpkStoneFulfillment::SHIPPING) {
            return $stored;
        }

        return SpkStoneFulfillment::NOT_ALLOCATED;
    }
}
