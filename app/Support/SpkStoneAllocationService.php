<?php

namespace App\Support;

use App\Models\SpkStone;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class SpkStoneAllocationService
{
    public const TABLE = 'spkstone_allocation';

    public const STATUS_RESERVED = 'reserved';

    public const SOURCE_DOSSIER = 'dossier';

    public const SOURCE_MICRO = 'micro';

    public function tableAvailable(): bool
    {
        return Schema::connection('third')->hasTable(self::TABLE);
    }

    public function allocatedPcs(SpkStone $stone): int
    {
        if (! $this->tableAvailable()) {
            return 0;
        }

        $rows = DB::connection('third')
            ->table(self::TABLE)
            ->where('line_id', $stone->line_id)
            ->where('is_deleted', 0)
            ->where('status', self::STATUS_RESERVED)
            ->get(['source', 'reserved_pcs']);

        $total = 0;

        foreach ($rows as $row) {
            if ((string) $row->source === self::SOURCE_DOSSIER) {
                $total += 1;

                continue;
            }

            $total += (int) round((float) ($row->reserved_pcs ?? 0));
        }

        return $total;
    }

    /**
     * @return list<array{
     *     id: int,
     *     source: string,
     *     label: string,
     *     diamondId: int|null,
     *     msStoneId: int|null,
     *     reservedPcs: string,
     *     reservedCrt: string|null,
     *     spkNo: string|null
     * }>
     */
    public function allocationsForStone(SpkStone $stone): array
    {
        if (! $this->tableAvailable()) {
            return [];
        }

        return $this->allocationRowsQuery()
            ->where('allocation.line_id', $stone->line_id)
            ->orderBy('allocation.row_id')
            ->get()
            ->map(fn (object $row): array => $this->mapAllocationRow($row))
            ->values()
            ->all();
    }

    /**
     * @return array<int, list<array{
     *     id: int,
     *     source: string,
     *     sourceLabel: string,
     *     label: string,
     *     code: string,
     *     shape: string,
     *     displayId: string|null,
     *     certificate: string|null,
     *     diamondType: string|null,
     *     color: string|null,
     *     diamondId: int|null,
     *     msStoneId: int|null,
     *     reservedPcs: string,
     *     reservedCrt: string|null,
     *     spkNo: string|null,
     *     allocatedDate: string|null,
     *     allocatedBy: string|null
     * }>>
     */
    public function allocationsGroupedByLineId(Collection $stones): array
    {
        if (! $this->tableAvailable() || $stones->isEmpty()) {
            return [];
        }

        $lineIds = $stones
            ->pluck('line_id')
            ->map(fn (mixed $lineId): int => (int) $lineId)
            ->unique()
            ->values()
            ->all();

        $grouped = [];

        $this->allocationRowsQuery()
            ->whereIn('allocation.line_id', $lineIds)
            ->orderBy('allocation.line_id')
            ->orderBy('allocation.row_id')
            ->get()
            ->each(function (object $row) use (&$grouped): void {
                $lineId = (int) $row->line_id;
                $grouped[$lineId] ??= [];
                $grouped[$lineId][] = $this->mapAllocationRow($row);
            });

        return $grouped;
    }

    private function allocationRowsQuery(): Builder
    {
        return DB::connection('third')
            ->table(self::TABLE.' as allocation')
            ->join('spkstone as stone_line', 'stone_line.line_id', '=', 'allocation.line_id')
            ->leftJoin('spk as production', function ($join): void {
                $join->on('production.row_id', '=', 'stone_line.row_id')
                    ->where('production.is_deleted', 0);
            })
            ->leftJoin('trdiamond as diamond', 'diamond.row_id', '=', 'allocation.diamond_id')
            ->leftJoin('msstone as micro_stone', 'micro_stone.row_id', '=', 'allocation.msstone_id')
            ->leftJoin('msshape as diamond_shape', 'diamond_shape.row_id', '=', 'diamond.shape_id')
            ->leftJoin('msshape as micro_shape', 'micro_shape.row_id', '=', 'micro_stone.shape_id')
            ->where('allocation.is_deleted', 0)
            ->where('allocation.status', self::STATUS_RESERVED)
            ->select([
                'allocation.line_id',
                'allocation.row_id',
                'allocation.source',
                'allocation.diamond_id',
                'allocation.msstone_id',
                'allocation.reserved_pcs',
                'allocation.reserved_crt',
                'allocation.created_date',
                'allocation.created_by',
                'diamond.doc_no as diamond_code',
                'diamond.doc_no as diamond_doc_no',
                'diamond.certificate as diamond_certificate',
                'diamond.diamond_type',
                'diamond.color as diamond_color',
                'diamond.crt as diamond_crt',
                'diamond_shape.name as diamond_shape_name',
                'diamond_shape.code as diamond_shape_code',
                'micro_stone.name as micro_name',
                'micro_shape.name as micro_shape_name',
                'micro_shape.code as micro_shape_code',
                'production.spk_no',
            ]);
    }

    /**
     * @return array{
     *     id: int,
     *     source: string,
     *     sourceLabel: string,
     *     label: string,
     *     code: string,
     *     shape: string,
     *     displayId: string|null,
     *     certificate: string|null,
     *     diamondType: string|null,
     *     color: string|null,
     *     diamondId: int|null,
     *     msStoneId: int|null,
     *     reservedPcs: string,
     *     reservedCrt: string|null,
     *     spkNo: string|null,
     *     allocatedDate: string|null,
     *     allocatedBy: string|null
     * }
     */
    private function mapAllocationRow(object $row): array
    {
        $source = (string) $row->source;
        $isDossier = $source === self::SOURCE_DOSSIER;
        $code = $isDossier
            ? (string) ($row->diamond_code ?? 'Batu dossier #'.($row->diamond_id ?? ''))
            : (string) ($row->micro_name ?? 'Batu mikro #'.($row->msstone_id ?? ''));

        $label = $code;

        $shape = $isDossier
            ? DiamondDossierInventory::formatShapeLabel(
                $this->nullableString($row->diamond_shape_name ?? null),
                $this->nullableString($row->diamond_shape_code ?? null),
            )
            : DiamondDossierInventory::formatShapeLabel(
                $this->nullableString($row->micro_shape_name ?? null),
                $this->nullableString($row->micro_shape_code ?? null),
            );

        $displayId = $isDossier
            ? ($this->nullableString($row->diamond_doc_no ?? null)
                ?? ($row->diamond_id !== null ? (string) (int) $row->diamond_id : null))
            : ($row->msstone_id !== null ? (string) (int) $row->msstone_id : null);

        $diamondType = $isDossier
            ? $this->nullableString($row->diamond_type ?? null)
            : null;

        $color = $isDossier && filled($row->diamond_color ?? null)
            ? (string) $row->diamond_color
            : null;

        $certificate = $isDossier
            ? $this->nullableString($row->diamond_certificate ?? null)
            : null;

        $reservedCrt = filled($row->reserved_crt ?? null)
            ? number_format((float) $row->reserved_crt, 3, '.', '')
            : null;

        if ($isDossier && $reservedCrt === null && filled($row->diamond_crt ?? null)) {
            $reservedCrt = number_format((float) $row->diamond_crt, 3, '.', '');
        }

        $allocatedBy = trim((string) ($row->created_by ?? ''));

        return [
            'id' => (int) $row->row_id,
            'source' => $source,
            'sourceLabel' => $isDossier ? 'Dossier' : 'Mikro',
            'label' => $label,
            'code' => $isDossier ? ($displayId ?? $code) : $code,
            'shape' => $shape,
            'displayId' => $displayId,
            'certificate' => $certificate,
            'diamondType' => $diamondType,
            'color' => $color,
            'diamondId' => $row->diamond_id !== null ? (int) $row->diamond_id : null,
            'msStoneId' => $row->msstone_id !== null ? (int) $row->msstone_id : null,
            'reservedPcs' => $this->formatPcs((float) ($row->reserved_pcs ?? 0)),
            'reservedCrt' => $reservedCrt,
            'spkNo' => filled($row->spk_no ?? null) ? (string) $row->spk_no : null,
            'allocatedDate' => $this->formatAllocationDate($row->created_date ?? null),
            'allocatedBy' => $allocatedBy !== '' ? $allocatedBy : null,
        ];
    }

    private function formatAllocationDate(mixed $value): ?string
    {
        if (! filled($value)) {
            return null;
        }

        try {
            return Carbon::parse($value)->format('d-M-Y');
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array<int, float> msstone_id => reserved pcs in period
     */
    public function microReservedPcsByStone(int $periodId): array
    {
        if (! $this->tableAvailable()) {
            return [];
        }

        return DB::connection('third')
            ->table(self::TABLE)
            ->where('is_deleted', 0)
            ->where('status', self::STATUS_RESERVED)
            ->where('source', self::SOURCE_MICRO)
            ->where('period_id', $periodId)
            ->selectRaw('msstone_id, SUM(COALESCE(reserved_pcs, 0)) as reserved_pcs')
            ->groupBy('msstone_id')
            ->pluck('reserved_pcs', 'msstone_id')
            ->mapWithKeys(fn (mixed $pcs, int|string $stoneId): array => [
                (int) $stoneId => (float) $pcs,
            ])
            ->all();
    }

    public function allocateDossier(
        SpkStone $stone,
        int $diamondId,
        string $actor,
        ?float $reservedCrt = null,
    ): void {
        abort_if($stone->is_deleted === 1, 404);
        abort_if(! $this->tableAvailable(), 503, 'Alokasi batu belum tersedia.');

        $requiredPcs = max(0, (int) ($stone->pcs ?? 0));

        if ($requiredPcs <= 0) {
            throw ValidationException::withMessages([
                'diamond_id' => 'Kebutuhan pcs batu SPK tidak valid.',
            ]);
        }

        if ($this->allocatedPcs($stone) >= $requiredPcs) {
            throw ValidationException::withMessages([
                'diamond_id' => 'Kebutuhan batu SPK sudah terpenuhi.',
            ]);
        }

        $stockChecker = app(SpkStoneStockChecker::class);
        $match = $stockChecker->matchingStones($stone);
        $dossierRows = $match['dossier']['rows'] ?? $match['rows'];
        $allowedIds = [];

        foreach ($dossierRows as $row) {
            $diamondRowId = (int) ($row['id'] ?? 0);

            if ($diamondRowId > 0) {
                $allowedIds[$diamondRowId] = true;
            }
        }

        if (! isset($allowedIds[$diamondId])) {
            throw ValidationException::withMessages([
                'diamond_id' => 'Batu dossier tidak ditemukan atau sudah tidak tersedia.',
            ]);
        }

        if ($this->diamondIsReserved($diamondId)) {
            throw ValidationException::withMessages([
                'diamond_id' => 'Batu dossier sudah dialokasikan ke SPK lain.',
            ]);
        }

        $now = now();

        $diamondCrt = DB::connection('third')
            ->table('trdiamond')
            ->where('row_id', $diamondId)
            ->value('crt');

        $crtValue = $reservedCrt !== null && $reservedCrt >= 0
            ? round($reservedCrt, 4)
            : (filled($diamondCrt) ? round((float) $diamondCrt, 4) : null);

        DB::connection('third')->table(self::TABLE)->insert([
            'line_id' => $stone->line_id,
            'source' => self::SOURCE_DOSSIER,
            'diamond_id' => $diamondId,
            'msstone_id' => null,
            'period_id' => null,
            'reserved_pcs' => 1,
            'reserved_crt' => $crtValue,
            'status' => self::STATUS_RESERVED,
            'is_deleted' => 0,
            'created_date' => $now,
            'created_by' => $actor,
            'modified_date' => $now,
            'modified_by' => $actor,
        ]);

        $this->syncFulfillment($stone, $actor);
    }

    public function allocateMicro(
        SpkStone $stone,
        int $msStoneId,
        string $actor,
        float $reservedPcs,
        ?float $reservedCrt = null,
    ): void {
        abort_if($stone->is_deleted === 1, 404);
        abort_if(! $this->tableAvailable(), 503, 'Alokasi batu belum tersedia.');

        $requiredPcs = max(0, (int) ($stone->pcs ?? 0));

        if ($requiredPcs <= 0) {
            throw ValidationException::withMessages([
                'msstone_id' => 'Kebutuhan pcs batu SPK tidak valid.',
            ]);
        }

        if ($this->allocatedPcs($stone) >= $requiredPcs) {
            throw ValidationException::withMessages([
                'msstone_id' => 'Kebutuhan batu SPK sudah terpenuhi.',
            ]);
        }

        if ($reservedPcs <= 0) {
            throw ValidationException::withMessages([
                'reserved_pcs' => 'Total pcs harus lebih dari 0.',
            ]);
        }

        $alreadyAllocated = $this->allocatedPcs($stone);
        $nextAllocated = $alreadyAllocated + (int) round($reservedPcs);

        if ($nextAllocated > $requiredPcs) {
            throw ValidationException::withMessages([
                'reserved_pcs' => 'Total pcs melebihi kebutuhan SPK yang belum teralokasi.',
            ]);
        }

        $ledger = app(StoneLedger::class);
        $period = $ledger->activePeriod();

        if ($period === null) {
            throw ValidationException::withMessages([
                'msstone_id' => 'Periode batu mikro aktif tidak tersedia.',
            ]);
        }

        $stockChecker = app(SpkStoneStockChecker::class);
        $match = $stockChecker->matchingStones($stone);
        $microRows = $match['micro']['rows'] ?? [];

        if ($microRows === [] && ($match['stock']['source'] ?? null) === SpkStoneStockChecker::SOURCE_MICRO) {
            $microRows = $match['rows'];
        }

        $allowed = false;

        foreach ($microRows as $row) {
            if ((int) ($row['id'] ?? 0) === $msStoneId) {
                $allowed = true;

                break;
            }
        }

        if (! $allowed) {
            throw ValidationException::withMessages([
                'msstone_id' => 'Master batu mikro tidak ditemukan dalam daftar stok.',
            ]);
        }

        $balances = $ledger->balancePcsByStone($period['id'], [$msStoneId]);
        $reservedMap = $this->microReservedPcsByStone($period['id']);
        $balance = (float) ($balances[$msStoneId] ?? 0);
        $reserved = (float) ($reservedMap[$msStoneId] ?? 0);
        $available = $balance - $reserved;

        if ($available + 0.0001 < $reservedPcs) {
            throw ValidationException::withMessages([
                'reserved_pcs' => 'Saldo batu mikro setelah reserved tidak mencukupi.',
            ]);
        }

        $totalCarat = (float) ($stone->carat ?? 0);
        $crtValue = $reservedCrt !== null && $reservedCrt >= 0
            ? round($reservedCrt, 4)
            : ($requiredPcs > 0 && $totalCarat > 0
                ? round($totalCarat * ($reservedPcs / $requiredPcs), 4)
                : null);

        $now = now();

        DB::connection('third')->table(self::TABLE)->insert([
            'line_id' => $stone->line_id,
            'source' => self::SOURCE_MICRO,
            'diamond_id' => null,
            'msstone_id' => $msStoneId,
            'period_id' => $period['id'],
            'reserved_pcs' => $reservedPcs,
            'reserved_crt' => $crtValue,
            'status' => self::STATUS_RESERVED,
            'is_deleted' => 0,
            'created_date' => $now,
            'created_by' => $actor,
            'modified_date' => $now,
            'modified_by' => $actor,
        ]);

        $this->syncFulfillment($stone, $actor);
    }

    public function revokeAll(SpkStone $stone, string $actor): void
    {
        abort_if($stone->is_deleted === 1, 404);

        if (! $this->tableAvailable()) {
            return;
        }

        $now = now();

        DB::connection('third')
            ->table(self::TABLE)
            ->where('line_id', $stone->line_id)
            ->where('is_deleted', 0)
            ->where('status', self::STATUS_RESERVED)
            ->update([
                'is_deleted' => 1,
                'deleted_date' => $now,
                'deleted_by' => $actor,
                'modified_date' => $now,
                'modified_by' => $actor,
            ]);

        if ($this->fulfillmentColumnAvailable()) {
            app(SpkStoneFulfillment::class)->resolve($stone->refresh());
        }
    }

    public function revokeAllocation(SpkStone $stone, int $allocationRowId, string $actor): void
    {
        abort_if($stone->is_deleted === 1, 404);
        abort_if(! $this->tableAvailable(), 503, 'Alokasi batu belum tersedia.');

        $allocation = DB::connection('third')
            ->table(self::TABLE)
            ->where('row_id', $allocationRowId)
            ->where('line_id', $stone->line_id)
            ->where('is_deleted', 0)
            ->where('status', self::STATUS_RESERVED)
            ->first(['row_id']);

        if ($allocation === null) {
            throw ValidationException::withMessages([
                'allocation' => 'Alokasi batu tidak ditemukan.',
            ]);
        }

        $now = now();

        DB::connection('third')
            ->table(self::TABLE)
            ->where('row_id', $allocationRowId)
            ->update([
                'is_deleted' => 1,
                'deleted_date' => $now,
                'deleted_by' => $actor,
                'modified_date' => $now,
                'modified_by' => $actor,
            ]);

        app(SpkStoneFulfillment::class)->resolve($stone->refresh());
    }

    public function revokeDossierByDiamondId(int $diamondId, string $actor): void
    {
        if (! $this->tableAvailable()) {
            abort(503, 'Alokasi batu belum tersedia.');
        }

        $allocation = DB::connection('third')
            ->table(self::TABLE)
            ->where('diamond_id', $diamondId)
            ->where('source', self::SOURCE_DOSSIER)
            ->where('is_deleted', 0)
            ->where('status', self::STATUS_RESERVED)
            ->first(['row_id', 'line_id']);

        if ($allocation === null) {
            throw ValidationException::withMessages([
                'allocation' => 'Batu dossier tidak sedang dialokasikan ke SPK.',
            ]);
        }

        $now = now();

        DB::connection('third')
            ->table(self::TABLE)
            ->where('row_id', (int) $allocation->row_id)
            ->update([
                'is_deleted' => 1,
                'deleted_date' => $now,
                'deleted_by' => $actor,
                'modified_date' => $now,
                'modified_by' => $actor,
            ]);

        $stone = SpkStone::query()
            ->notDeleted()
            ->where('line_id', (int) $allocation->line_id)
            ->first();

        if ($stone === null) {
            return;
        }

        app(SpkStoneFulfillment::class)->resolve($stone->refresh());
    }

    public function applyReservedScope(Builder $query, string $diamondColumn = 'diamond.row_id'): Builder
    {
        if (! $this->tableAvailable()) {
            return $query;
        }

        return $query->whereNotExists(function (Builder $subquery) use ($diamondColumn): void {
            $subquery->selectRaw('1')
                ->from(self::TABLE.' as stone_allocation')
                ->whereColumn('stone_allocation.diamond_id', $diamondColumn)
                ->where('stone_allocation.is_deleted', 0)
                ->where('stone_allocation.status', self::STATUS_RESERVED)
                ->where('stone_allocation.source', self::SOURCE_DOSSIER);
        });
    }

    public function diamondIsReserved(int $diamondId): bool
    {
        if (! $this->tableAvailable()) {
            return false;
        }

        return DB::connection('third')
            ->table(self::TABLE)
            ->where('diamond_id', $diamondId)
            ->where('is_deleted', 0)
            ->where('status', self::STATUS_RESERVED)
            ->exists();
    }

    private function existingSource(SpkStone $stone): ?string
    {
        if (! $this->tableAvailable()) {
            return null;
        }

        $source = DB::connection('third')
            ->table(self::TABLE)
            ->where('line_id', $stone->line_id)
            ->where('is_deleted', 0)
            ->where('status', self::STATUS_RESERVED)
            ->value('source');

        return is_string($source) && $source !== '' ? $source : null;
    }

    private function syncFulfillment(SpkStone $stone, string $actor): void
    {
        if (! $this->fulfillmentColumnAvailable()) {
            return;
        }

        $requiredPcs = max(0, (int) ($stone->pcs ?? 0));
        $allocatedPcs = $this->allocatedPcs($stone);

        if ($requiredPcs > 0 && $allocatedPcs >= $requiredPcs) {
            $stone->update([
                'fulfillment_status' => SpkStoneFulfillment::ALLOCATED,
                'modified_date' => now(),
                'modified_by' => $actor,
            ]);
        }
    }

    private function fulfillmentColumnAvailable(): bool
    {
        $schema = Schema::connection('third');

        return $schema->hasTable('spkstone')
            && $schema->hasColumn('spkstone', 'fulfillment_status');
    }

    private function formatPcs(float $value): string
    {
        return (string) (int) round($value);
    }

    private function nullableString(mixed $value): ?string
    {
        if (! filled($value)) {
            return null;
        }

        $text = trim((string) $value);

        return $text !== '' ? $text : null;
    }
}
