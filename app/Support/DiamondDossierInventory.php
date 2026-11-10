<?php

namespace App\Support;

use App\Models\DiamondCrtMatrix;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class DiamondDossierInventory
{
    public const STATUS_AVAILABLE = 'available';

    public const STATUS_RESERVED = 'reserved';

    public const STATUS_USED = 'used';

    public const STATUS_ACTUAL_AVAILABLE = 'actual_available';

    public const DEFAULT_LOCATION = 'Head Office';

    /**
     * @return list<string>
     */
    public static function diamondTypes(): array
    {
        return [
            'Dossier',
            'Safir',
            'Amethys',
            'FOS',
            'RUBY',
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function diamondTypeOptions(): array
    {
        return array_map(
            fn (string $type): array => ['value' => $type, 'label' => $type],
            self::diamondTypes(),
        );
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function shapeOptions(): array
    {
        if (! Schema::connection('third')->hasTable('msshape')) {
            return [];
        }

        $query = DB::connection('third')
            ->table('msshape')
            ->whereNotNull('name')
            ->where('name', '!=', '')
            ->orderBy('name');

        if (Schema::connection('third')->hasColumn('msshape', 'is_deleted')) {
            $query->where('is_deleted', 0);
        }

        $columns = ['row_id', 'name'];

        if (Schema::connection('third')->hasColumn('msshape', 'code')) {
            $columns[] = 'code';
        }

        return $query
            ->get($columns)
            ->map(fn (object $row): array => [
                'value' => (string) $row->row_id,
                'label' => self::formatShapeLabel(
                    $this->nullableString($row->name),
                    isset($row->code) ? $this->nullableString($row->code) : null,
                ),
            ])
            ->values()
            ->all();
    }

    public function resolveShapeId(?string $input): ?int
    {
        $input = trim((string) ($input ?? ''));

        if ($input === '' || ! Schema::connection('third')->hasTable('msshape')) {
            return null;
        }

        $query = DB::connection('third')
            ->table('msshape')
            ->select(['row_id', 'name']);

        if (Schema::connection('third')->hasColumn('msshape', 'code')) {
            $query->addSelect('code');
        }

        if (Schema::connection('third')->hasColumn('msshape', 'is_deleted')) {
            $query->where('is_deleted', 0);
        }

        $shapes = $query->get();

        $normalizedInput = mb_strtolower($input);

        foreach ($shapes as $shape) {
            $name = trim((string) ($shape->name ?? ''));
            $code = isset($shape->code) ? trim((string) $shape->code) : '';
            $label = self::formatShapeLabel(
                $name !== '' ? $name : null,
                $code !== '' ? $code : null,
            );

            $candidates = array_filter([
                $name,
                $code,
                $label,
            ], fn (string $candidate): bool => $candidate !== '');

            foreach ($candidates as $candidate) {
                if (mb_strtolower($candidate) === $normalizedInput) {
                    return (int) $shape->row_id;
                }
            }
        }

        return null;
    }

    public static function formatShapeLabel(?string $name, ?string $code): string
    {
        $name = trim((string) ($name ?? ''));
        $code = trim((string) ($code ?? ''));

        if ($name === '' && $code === '') {
            return '—';
        }

        if ($name === '') {
            return $code;
        }

        if ($code === '') {
            return $name;
        }

        return "{$name} ({$code})";
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function supplierOptions(): array
    {
        if (! Schema::connection('third')->hasTable('trdiamond')) {
            return [];
        }

        return DB::connection('third')
            ->table('trdiamond')
            ->where('is_deleted', 0)
            ->whereNotNull('supplier')
            ->where('supplier', '!=', '')
            ->distinct()
            ->orderBy('supplier')
            ->pluck('supplier')
            ->map(fn (mixed $supplier): array => [
                'value' => (string) $supplier,
                'label' => (string) $supplier,
            ])
            ->values()
            ->all();
    }

    public function lastInventoryModifiedAt(): ?string
    {
        if (! Schema::connection('third')->hasTable('trdiamond')) {
            return null;
        }

        $modifiedAt = DB::connection('third')
            ->table('trdiamond')
            ->where('is_deleted', 0)
            ->max('modified_date');

        return filled($modifiedAt) ? $this->nullableString($modifiedAt) : null;
    }

    /**
     * @return list<string>
     */
    public static function indexSorts(): array
    {
        return [
            'id',
            'entry_date',
            'out_date',
            'crt',
            'color',
            'shape',
            'diamond_type',
            'certificate',
            'supplier',
            'modified_date',
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function indexSortOptions(): array
    {
        return [
            ['value' => 'id', 'label' => 'ID'],
            ['value' => 'entry_date', 'label' => 'Tanggal masuk'],
            ['value' => 'out_date', 'label' => 'Tanggal keluar'],
            ['value' => 'crt', 'label' => 'CRT'],
            ['value' => 'color', 'label' => 'Warna'],
            ['value' => 'shape', 'label' => 'Bentuk'],
            ['value' => 'diamond_type', 'label' => 'Tipe'],
            ['value' => 'certificate', 'label' => 'No. sertifikat'],
            ['value' => 'supplier', 'label' => 'Supplier'],
            ['value' => 'modified_date', 'label' => 'Terakhir diubah'],
        ];
    }

    public static function resolveIndexSort(string $sort): string
    {
        if ($sort === '') {
            return 'entry_date';
        }

        return in_array($sort, self::indexSorts(), true) ? $sort : 'entry_date';
    }

    public static function resolveIndexDirection(string $direction): string
    {
        return in_array($direction, ['asc', 'desc'], true) ? $direction : 'desc';
    }

    /**
     * @return LengthAwarePaginator<int, array<string, int|string|null>>
     */
    public function paginate(
        string $search,
        ?string $status,
        int $perPage,
        ?string $diamondType = null,
        ?int $shapeId = null,
        ?string $supplier = null,
        string $sort = 'entry_date',
        string $direction = 'desc',
    ): LengthAwarePaginator {
        $query = $this->filteredQuery($search, $status, $diamondType, $shapeId, $supplier);
        $this->applyIndexSort($query, $sort, $direction);

        return $query
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (object $diamond): array => $this->toRow($diamond));
    }

    private function applyIndexSort(Builder $query, string $sort, string $direction): void
    {
        $direction = self::resolveIndexDirection($direction);
        $sort = self::resolveIndexSort($sort);

        $column = match ($sort) {
            'entry_date' => 'diamond.entry_date',
            'out_date' => 'diamond.out_date',
            'crt' => 'diamond.crt',
            'color' => 'diamond.color',
            'shape' => 'shape.name',
            'diamond_type' => 'diamond.diamond_type',
            'certificate' => 'diamond.certificate',
            'supplier' => 'diamond.supplier',
            'modified_date' => 'diamond.modified_date',
            default => 'diamond.row_id',
        };

        $query->orderBy($column, $direction);

        if ($sort !== 'id') {
            $query->orderByDesc('diamond.row_id');
        }
    }

    /**
     * @return array{count: int, crt: string}
     */
    public function summary(
        string $search,
        ?string $status,
        ?string $diamondType = null,
        ?int $shapeId = null,
        ?string $supplier = null,
    ): array {
        $summary = $this->filteredQuery($search, $status, $diamondType, $shapeId, $supplier)
            ->cloneWithout(['columns', 'orders'])
            ->selectRaw('COUNT(*) as diamond_count, SUM(COALESCE(diamond.crt, 0)) as total_crt')
            ->first();

        return [
            'count' => (int) ($summary->diamond_count ?? 0),
            'crt' => number_format((float) ($summary->total_crt ?? 0), 3, '.', ''),
        ];
    }

    /**
     * @return array{
     *     available: array{pcs: int, crt: string},
     *     reserved: array{pcs: int, crt: string, spkCount: int},
     *     used: array{pcs: int, crt: string, spkCount: int}
     * }
     */
    public function statusSummaries(
        string $search,
        ?string $diamondType = null,
        ?int $shapeId = null,
        ?string $supplier = null,
    ): array {
        return [
            'available' => $this->statusSummaryMetrics(
                $search,
                self::STATUS_AVAILABLE,
                false,
                $diamondType,
                $shapeId,
                $supplier,
            ),
            'reserved' => $this->statusSummaryMetrics(
                $search,
                self::STATUS_RESERVED,
                true,
                $diamondType,
                $shapeId,
                $supplier,
            ),
            'used' => $this->statusSummaryMetrics(
                $search,
                self::STATUS_USED,
                true,
                $diamondType,
                $shapeId,
                $supplier,
            ),
        ];
    }

    /**
     * @return array{pcs: int, crt: string, spkCount?: int}
     */
    private function statusSummaryMetrics(
        string $search,
        string $status,
        bool $includeSpkCount,
        ?string $diamondType = null,
        ?int $shapeId = null,
        ?string $supplier = null,
    ): array {
        $totals = $this->summary($search, $status, $diamondType, $shapeId, $supplier);

        $metrics = [
            'pcs' => $totals['count'],
            'crt' => $totals['crt'],
        ];

        if ($includeSpkCount) {
            $metrics['spkCount'] = $this->distinctSpkCount(
                $search,
                $status,
                $diamondType,
                $shapeId,
                $supplier,
            );
        }

        return $metrics;
    }

    private function distinctSpkCount(
        string $search,
        string $status,
        ?string $diamondType = null,
        ?int $shapeId = null,
        ?string $supplier = null,
    ): int {
        if ($status === self::STATUS_USED) {
            return (int) ($this->filteredQuery($search, $status, $diamondType, $shapeId, $supplier)
                ->cloneWithout(['columns', 'orders'])
                ->whereNotNull('production.spk_no')
                ->where('production.spk_no', '!=', '')
                ->selectRaw('COUNT(DISTINCT production.spk_no) as aggregate')
                ->value('aggregate') ?? 0);
        }

        if ($status === self::STATUS_RESERVED
            && app(SpkStoneAllocationService::class)->tableAvailable()) {
            return (int) ($this->filteredQuery($search, $status, $diamondType, $shapeId, $supplier)
                ->cloneWithout(['columns', 'orders'])
                ->whereNotNull('reserved_production.spk_no')
                ->where('reserved_production.spk_no', '!=', '')
                ->selectRaw('COUNT(DISTINCT reserved_production.spk_no) as aggregate')
                ->value('aggregate') ?? 0);
        }

        return 0;
    }

    /**
     * @return array{
     *     id: int,
     *     code: string|null,
     *     diamondType: string|null,
     *     shape: string|null,
     *     color: string|null,
     *     crt: string|null,
     *     certificate: string|null,
     *     supplier: string|null,
     *     entryDate: string|null,
     *     outDate: string|null,
     *     status: string,
     *     mountingDocumentNo: string|null,
     *     mountingDocumentId: int|null,
     *     spkNo: string|null,
     *     createdBy: string|null,
     *     createdDate: string|null,
     *     modifiedBy: string|null,
     *     modifiedDate: string|null
     * }|null
     */
    public function find(int $diamondId): ?array
    {
        $diamond = $this->detailQuery()
            ->where('diamond.row_id', $diamondId)
            ->first();

        if ($diamond === null) {
            return null;
        }

        return $this->toDetailRow($diamond);
    }

    /**
     * @return array{
     *     docNo: string|null,
     *     diamondType: string|null,
     *     shapeId: int|null,
     *     color: string|null,
     *     crt: string|null,
     *     certificate: string|null,
     *     supplier: string|null,
     *     entryDate: string|null
     * }|null
     */
    public function formPayload(int $diamondId): ?array
    {
        $diamond = DB::connection('third')
            ->table('trdiamond as diamond')
            ->leftJoin('msshape as shape', 'shape.row_id', '=', 'diamond.shape_id')
            ->where('diamond.row_id', $diamondId)
            ->where('diamond.is_deleted', 0)
            ->first([
                'diamond.doc_no',
                'diamond.diamond_type',
                'diamond.shape_id',
                'diamond.color',
                'diamond.crt',
                'diamond.certificate',
                'diamond.supplier',
                'diamond.entry_date',
                'diamond.is_used',
            ]);

        if ($diamond === null) {
            return null;
        }

        return [
            'docNo' => $this->nullableString($diamond->doc_no),
            'diamondType' => $this->nullableString($diamond->diamond_type),
            'shapeId' => filled($diamond->shape_id ?? null) ? (int) $diamond->shape_id : null,
            'color' => $this->nullableString($diamond->color),
            'crt' => filled($diamond->crt)
                ? number_format((float) $diamond->crt, 3, '.', '')
                : null,
            'certificate' => $this->nullableString($diamond->certificate),
            'supplier' => $this->nullableString($diamond->supplier),
            'entryDate' => $this->nullableString($diamond->entry_date),
        ];
    }

    public function editableBlockReason(int $diamondId): ?string
    {
        if (! Schema::connection('third')->hasTable('trdiamond')) {
            return 'Data batu dossier tidak tersedia.';
        }

        $diamond = DB::connection('third')
            ->table('trdiamond')
            ->where('row_id', $diamondId)
            ->where('is_deleted', 0)
            ->first(['is_used', 'diamondmounting_id']);

        if ($diamond === null) {
            return 'Batu dossier tidak ditemukan.';
        }

        if ((int) ($diamond->is_used ?? 0) === 1 || (int) ($diamond->diamondmounting_id ?? 0) > 0) {
            return 'Batu yang sudah terpakai tidak dapat diubah.';
        }

        return null;
    }

    public function deletableBlockReason(int $diamondId): ?string
    {
        $blockReason = $this->editableBlockReason($diamondId);

        if ($blockReason !== null) {
            return $blockReason;
        }

        if ($this->hasActiveReservation($diamondId)) {
            return 'Batu yang sedang reserved SPK tidak dapat dihapus.';
        }

        return null;
    }

    /**
     * @param  array{
     *     doc_no?: string|null,
     *     diamond_type: string,
     *     shape_id: int,
     *     color: string,
     *     crt: float|string,
     *     certificate?: string|null,
     *     supplier?: string|null,
     *     entry_date: string
     * }  $attributes
     */
    public function create(array $attributes, string $actor): int
    {
        if (! Schema::connection('third')->hasTable('trdiamond')) {
            throw ValidationException::withMessages([
                'doc_no' => 'Data batu dossier tidak tersedia.',
            ]);
        }

        $now = now();

        return (int) DB::connection('third')->transaction(function () use ($attributes, $actor, $now): int {
            $docNo = filled($attributes['doc_no'] ?? null)
                ? trim((string) $attributes['doc_no'])
                : app(DiamondDossierDocNumberGenerator::class)->generate();

            if ($this->docNoExists($docNo)) {
                throw ValidationException::withMessages([
                    'doc_no' => 'Kode batu sudah digunakan.',
                ]);
            }

            return (int) DB::connection('third')
                ->table('trdiamond')
                ->insertGetId([
                    'doc_no' => $docNo,
                    'diamond_type' => trim((string) $attributes['diamond_type']),
                    'entry_date' => (string) $attributes['entry_date'],
                    'out_date' => null,
                    'supplier' => $this->nullableString($attributes['supplier'] ?? null),
                    'location' => self::DEFAULT_LOCATION,
                    'crt' => number_format((float) $attributes['crt'], 3, '.', ''),
                    'shape_id' => (int) $attributes['shape_id'],
                    'color' => trim((string) $attributes['color']),
                    'certificate' => $this->nullableString($attributes['certificate'] ?? null),
                    'rapp' => null,
                    'disc' => null,
                    'hpp' => null,
                    'diamondmounting_id' => 0,
                    'is_used' => 0,
                    'is_deleted' => 0,
                    'created_date' => $now,
                    'created_by' => $actor,
                    'modified_date' => $now,
                    'modified_by' => $actor,
                    'deleted_date' => null,
                    'deleted_by' => null,
                ]);
        });
    }

    /**
     * @param  array{
     *     diamond_type: string,
     *     shape_id: int,
     *     color: string,
     *     crt: float|string,
     *     certificate?: string|null,
     *     supplier?: string|null,
     *     entry_date: string
     * }  $attributes
     */
    public function update(int $diamondId, array $attributes, string $actor): void
    {
        $blockReason = $this->editableBlockReason($diamondId);

        if ($blockReason !== null) {
            throw ValidationException::withMessages([
                'entry_date' => $blockReason,
            ]);
        }

        $now = now();

        DB::connection('third')
            ->table('trdiamond')
            ->where('row_id', $diamondId)
            ->where('is_deleted', 0)
            ->update([
                'diamond_type' => trim((string) $attributes['diamond_type']),
                'shape_id' => (int) $attributes['shape_id'],
                'color' => trim((string) $attributes['color']),
                'crt' => number_format((float) $attributes['crt'], 3, '.', ''),
                'certificate' => $this->nullableString($attributes['certificate'] ?? null),
                'supplier' => $this->nullableString($attributes['supplier'] ?? null),
                'entry_date' => (string) $attributes['entry_date'],
                'modified_date' => $now,
                'modified_by' => $actor,
            ]);
    }

    /**
     * @param  list<int>  $diamondIds
     */
    public function softDeleteMany(array $diamondIds, string $actor): int
    {
        if (! Schema::connection('third')->hasTable('trdiamond')) {
            throw ValidationException::withMessages([
                'ids' => 'Data batu dossier tidak tersedia.',
            ]);
        }

        $diamondIds = array_values(array_unique(array_map(intval(...), $diamondIds)));

        if ($diamondIds === []) {
            throw ValidationException::withMessages([
                'ids' => 'Pilih minimal satu batu dossier.',
            ]);
        }

        foreach ($diamondIds as $diamondId) {
            $blockReason = $this->deletableBlockReason($diamondId);

            if ($blockReason !== null) {
                throw ValidationException::withMessages([
                    'ids' => $blockReason,
                ]);
            }
        }

        $now = now();

        return DB::connection('third')->transaction(function () use ($diamondIds, $actor, $now): int {
            return DB::connection('third')
                ->table('trdiamond')
                ->whereIn('row_id', $diamondIds)
                ->where('is_deleted', 0)
                ->update([
                    'is_deleted' => 1,
                    'deleted_date' => $now,
                    'deleted_by' => $actor,
                    'modified_date' => $now,
                    'modified_by' => $actor,
                ]);
        });
    }

    private function docNoExists(string $docNo, ?int $ignoreDiamondId = null): bool
    {
        $query = DB::connection('third')
            ->table('trdiamond')
            ->where('doc_no', $docNo)
            ->where('is_deleted', 0);

        if ($ignoreDiamondId !== null) {
            $query->where('row_id', '!=', $ignoreDiamondId);
        }

        return $query->exists();
    }

    private function hasActiveReservation(int $diamondId): bool
    {
        $allocationService = app(SpkStoneAllocationService::class);

        if (! $allocationService->tableAvailable()) {
            return false;
        }

        return DB::connection('third')
            ->table('spkstone_allocation')
            ->where('diamond_id', $diamondId)
            ->where('is_deleted', 0)
            ->where('status', SpkStoneAllocationService::STATUS_RESERVED)
            ->where('source', SpkStoneAllocationService::SOURCE_DOSSIER)
            ->exists();
    }

    public function availableCountInCrtRange(int $shapeId, string $crtMin, string $crtMax): int
    {
        return $this->availableInCrtRangeQuery($shapeId, $crtMin, $crtMax)->count();
    }

    /**
     * @return list<array<string, int|string|null>>
     */
    public function availableInCrtRange(int $shapeId, string $crtMin, string $crtMax): array
    {
        return array_values($this->availableInCrtRangeQuery($shapeId, $crtMin, $crtMax)
            ->orderBy('diamond.crt')
            ->orderBy('diamond.row_id')
            ->get()
            ->map(fn (object $diamond): array => $this->toRow($diamond))
            ->all());
    }

    /**
     * @return list<array<string, int|string|null>>
     */
    public function availableForShape(int $shapeId, int $limit = 150): array
    {
        return array_values($this->filteredQuery('', self::STATUS_AVAILABLE)
            ->where('diamond.shape_id', $shapeId)
            ->orderBy('diamond.crt')
            ->orderByDesc('diamond.row_id')
            ->limit($limit)
            ->get()
            ->map(fn (object $diamond): array => $this->toRow($diamond))
            ->all());
    }

    /**
     * Available dossier diamonds plus the ones already assigned to the given mounting document.
     *
     * @return list<array{value: string, label: string, description: string, code: string|null, diamondType: string|null, shape: string|null, certificate: string|null, crt: string|null}>
     */
    public function mountingOptions(?int $mountingId = null): array
    {
        return array_values($this->selectableForMountingQuery($mountingId)
            ->orderBy('diamond.doc_no')
            ->orderBy('diamond.row_id')
            ->get()
            ->map(fn (object $diamond): array => $this->toMountingOption($diamond))
            ->all());
    }

    /**
     * @param  list<int>  $diamondIds
     * @return list<int>
     */
    public function selectableIdsForMounting(
        array $diamondIds,
        ?int $mountingId = null,
        ?int $spkProductionRowId = null,
    ): array {
        if ($diamondIds === []) {
            return [];
        }

        return array_values($this->selectableForMountingQuery($mountingId, $spkProductionRowId)
            ->whereIn('diamond.row_id', $diamondIds)
            ->pluck('diamond.row_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all());
    }

    /**
     * Links the selected dossier diamonds to the mounting document and releases the ones no longer selected.
     *
     * @param  list<int>  $diamondIds
     *
     * @throws ValidationException
     */
    public function assignToMounting(int $mountingId, array $diamondIds, string $actor): void
    {
        $now = now();
        $diamondIds = array_values(array_unique($diamondIds));

        $assignedIds = DB::connection('third')
            ->table('trdiamond')
            ->where('diamondmounting_id', $mountingId)
            ->where('is_deleted', 0)
            ->pluck('row_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        $releasedIds = array_values(array_diff($assignedIds, $diamondIds));
        $newIds = array_values(array_diff($diamondIds, $assignedIds));

        if ($releasedIds !== []) {
            DB::connection('third')
                ->table('trdiamond')
                ->whereIn('row_id', $releasedIds)
                ->update([
                    'diamondmounting_id' => 0,
                    'is_used' => 0,
                    'out_date' => null,
                    'modified_date' => $now,
                    'modified_by' => $actor,
                ]);
        }

        if ($newIds === []) {
            return;
        }

        $spkProductionRowId = (int) (DB::connection('third')
            ->table('diamondmounting')
            ->where('row_id', $mountingId)
            ->value('spk_id') ?? 0);

        $linkedCount = DB::connection('third')
            ->table('trdiamond as diamond')
            ->whereIn('diamond.row_id', $newIds)
            ->where('diamond.is_deleted', 0)
            ->where(fn (Builder $query) => $this->applyMountingEligibleScope(
                $query,
                $mountingId,
                $spkProductionRowId > 0 ? $spkProductionRowId : null,
            ))
            ->update([
                'diamond.diamondmounting_id' => $mountingId,
                'diamond.is_used' => 1,
                'diamond.out_date' => $now->toDateString(),
                'diamond.modified_date' => $now,
                'diamond.modified_by' => $actor,
            ]);

        if ($linkedCount !== count($newIds)) {
            throw ValidationException::withMessages([
                'diamonds' => 'Sebagian Batu Dossier yang dipilih sudah dipakai dokumen lain. Muat ulang halaman lalu pilih ulang.',
            ]);
        }
    }

    private function selectableForMountingQuery(?int $mountingId, ?int $spkProductionRowId = null): Builder
    {
        return $this->filteredQuery('', null)
            ->where(fn (Builder $query) => $this->applyMountingEligibleScope(
                $query,
                $mountingId,
                $spkProductionRowId,
            ));
    }

    private function applyMountingEligibleScope(
        Builder $query,
        ?int $mountingId,
        ?int $spkProductionRowId,
    ): Builder {
        return $query->where(function (Builder $inner) use ($mountingId, $spkProductionRowId): void {
            $inner->where(fn (Builder $availableQuery) => $this->whereSelectable($availableQuery));

            if ($mountingId !== null && $mountingId > 0) {
                $inner->orWhere('diamond.diamondmounting_id', $mountingId);
            }

            if ($spkProductionRowId !== null && $spkProductionRowId > 0) {
                $inner->orWhere(fn (Builder $reservedQuery) => app(SpkStoneAllocationService::class)
                    ->whereReservedForProduction($reservedQuery, $spkProductionRowId));
            }
        });
    }

    /**
     * @return array{value: string, label: string, description: string, code: string|null, diamondType: string|null, shape: string|null, certificate: string|null, crt: string|null}
     */
    private function toMountingOption(object $diamond): array
    {
        $row = $this->toRow($diamond);
        $code = $row['code'] ?? '#'.$row['id'];
        $crtLabel = $row['crt'] !== null ? $row['crt'].' ct' : null;

        return [
            'value' => (string) $row['id'],
            'label' => implode(' · ', array_filter([$code, $row['shape'], $crtLabel])),
            'description' => implode(' · ', array_filter([$row['diamondType'], $row['certificate']])),
            'code' => $row['code'],
            'diamondType' => $row['diamondType'],
            'shape' => $row['shape'],
            'certificate' => $row['certificate'],
            'crt' => $row['crt'],
        ];
    }

    private function availableInCrtRangeQuery(int $shapeId, string $crtMin, string $crtMax): Builder
    {
        return $this->filteredQuery('', self::STATUS_AVAILABLE)
            ->where('diamond.shape_id', $shapeId)
            ->whereBetween('diamond.crt', [$crtMin, $crtMax]);
    }

    /**
     * @return array{id: int, code: string|null, diamondType: string|null, shape: string|null, color: string|null, crt: string|null, certificate: string|null, supplier: string|null, entryDate: string|null, outDate: string|null, status: string, reservedSpkNo: string|null, spkNo: string|null, mountingDocumentNo: string|null, mountingDocumentId: int|null, createdBy: string|null, createdDate: string|null, modifiedBy: string|null, modifiedDate: string|null}
     */
    private function toRow(object $diamond): array
    {
        $mountingSpkNo = $this->nullableString($diamond->mounting_spk_no ?? null);
        $reservedSpkNo = $this->nullableString($diamond->reserved_spk_no ?? null);

        return [
            'id' => (int) $diamond->row_id,
            'code' => $this->nullableString($diamond->doc_no),
            'diamondType' => $this->nullableString($diamond->diamond_type),
            'shapeId' => filled($diamond->shape_id ?? null) ? (int) $diamond->shape_id : null,
            'shape' => self::formatShapeLabel(
                $this->nullableString($diamond->shape_name),
                $this->nullableString($diamond->shape_code ?? null),
            ),
            'color' => $this->nullableString($diamond->color),
            'crt' => filled($diamond->crt)
                ? number_format((float) $diamond->crt, 3, '.', '')
                : null,
            'certificate' => $this->nullableString($diamond->certificate),
            'supplier' => $this->nullableString($diamond->supplier),
            'entryDate' => $this->nullableString($diamond->entry_date),
            'outDate' => $this->nullableString($diamond->out_date),
            'status' => $this->resolveInventoryStatus($diamond),
            'reservedSpkNo' => $reservedSpkNo,
            'spkNo' => $mountingSpkNo ?? $reservedSpkNo,
            'mountingDocumentNo' => $this->nullableString($diamond->mounting_doc_no),
            'mountingDocumentId' => filled($diamond->mounting_doc_id ?? null)
                ? (int) $diamond->mounting_doc_id
                : null,
            'createdBy' => $this->nullableString($diamond->created_by),
            'createdDate' => $this->nullableString($diamond->created_date),
            'modifiedBy' => $this->nullableString($diamond->modified_by ?? null),
            'modifiedDate' => $this->nullableString($diamond->modified_date ?? null),
        ];
    }

    private function resolveInventoryStatus(object $diamond): string
    {
        if ((int) ($diamond->is_used ?? 0) === 1) {
            return self::STATUS_USED;
        }

        if (filled($diamond->allocation_id ?? null)) {
            return self::STATUS_RESERVED;
        }

        return self::STATUS_AVAILABLE;
    }

    /**
     * @return array{
     *     id: int,
     *     code: string|null,
     *     diamondType: string|null,
     *     shape: string|null,
     *     color: string|null,
     *     crt: string|null,
     *     certificate: string|null,
     *     supplier: string|null,
     *     entryDate: string|null,
     *     outDate: string|null,
     *     status: string,
     *     mountingDocumentNo: string|null,
     *     mountingDocumentId: int|null,
     *     spkNo: string|null,
     *     createdBy: string|null,
     *     createdDate: string|null,
     *     modifiedBy: string|null,
     *     modifiedDate: string|null
     * }
     */
    private function toDetailRow(object $diamond): array
    {
        return [
            ...$this->toRow($diamond),
            'modifiedBy' => $this->nullableString($diamond->modified_by ?? null),
            'modifiedDate' => $this->nullableString($diamond->modified_date ?? null),
        ];
    }

    private function detailQuery(): Builder
    {
        $query = DB::connection('third')
            ->table('trdiamond as diamond')
            ->leftJoin('msshape as shape', 'shape.row_id', '=', 'diamond.shape_id')
            ->leftJoin('diamondmounting as mounting_document', function ($join): void {
                $join->on('mounting_document.row_id', '=', 'diamond.diamondmounting_id')
                    ->where('mounting_document.is_deleted', 0);
            })
            ->leftJoin('spk as production', 'production.row_id', '=', 'mounting_document.spk_id')
            ->where('diamond.is_deleted', 0);

        if (app(SpkStoneAllocationService::class)->tableAvailable()) {
            $query
                ->leftJoin('spkstone_allocation as active_allocation', function ($join): void {
                    $join->on('active_allocation.diamond_id', '=', 'diamond.row_id')
                        ->where('active_allocation.is_deleted', 0)
                        ->where('active_allocation.status', SpkStoneAllocationService::STATUS_RESERVED)
                        ->where('active_allocation.source', SpkStoneAllocationService::SOURCE_DOSSIER);
                })
                ->leftJoin('spkstone as reserved_stone_line', 'reserved_stone_line.line_id', '=', 'active_allocation.line_id')
                ->leftJoin('spk as reserved_production', function ($join): void {
                    $join->on('reserved_production.row_id', '=', 'reserved_stone_line.row_id')
                        ->where('reserved_production.is_deleted', 0);
                });
        }

        $select = [
            'diamond.row_id',
            'diamond.doc_no',
            'diamond.diamond_type',
            'diamond.shape_id',
            'shape.name as shape_name',
            'shape.code as shape_code',
            'diamond.color',
            'diamond.crt',
            'diamond.certificate',
            'diamond.supplier',
            'diamond.entry_date',
            'diamond.out_date',
            'diamond.is_used',
            'mounting_document.doc_no as mounting_doc_no',
            'mounting_document.row_id as mounting_doc_id',
            'production.spk_no as mounting_spk_no',
            'diamond.created_by',
            'diamond.created_date',
            'diamond.modified_by',
            'diamond.modified_date',
        ];

        if (app(SpkStoneAllocationService::class)->tableAvailable()) {
            $select[] = 'active_allocation.row_id as allocation_id';
            $select[] = 'reserved_production.spk_no as reserved_spk_no';
        } else {
            $select[] = DB::raw('NULL as allocation_id');
            $select[] = DB::raw('NULL as reserved_spk_no');
        }

        return $query->select($select);
    }

    private function whereAvailable(Builder $query): Builder
    {
        return $query->where(function (Builder $inner): void {
            $inner->where('diamond.is_used', '!=', 1)
                ->orWhereNull('diamond.is_used');
        });
    }

    private function whereSelectable(Builder $query): Builder
    {
        $query = $this->whereAvailable($query);

        return app(SpkStoneAllocationService::class)->applyReservedScope($query);
    }

    private function filteredQuery(
        string $search,
        ?string $status,
        ?string $diamondType = null,
        ?int $shapeId = null,
        ?string $supplier = null,
    ): Builder {
        $query = DB::connection('third')
            ->table('trdiamond as diamond')
            ->leftJoin('msshape as shape', 'shape.row_id', '=', 'diamond.shape_id')
            ->leftJoin('diamondmounting as mounting_document', function ($join): void {
                $join->on('mounting_document.row_id', '=', 'diamond.diamondmounting_id')
                    ->where('mounting_document.is_deleted', 0);
            })
            ->leftJoin('spk as production', 'production.row_id', '=', 'mounting_document.spk_id')
            ->where('diamond.is_deleted', 0);

        if (app(SpkStoneAllocationService::class)->tableAvailable()) {
            $query
                ->leftJoin('spkstone_allocation as active_allocation', function ($join): void {
                    $join->on('active_allocation.diamond_id', '=', 'diamond.row_id')
                        ->where('active_allocation.is_deleted', 0)
                        ->where('active_allocation.status', SpkStoneAllocationService::STATUS_RESERVED)
                        ->where('active_allocation.source', SpkStoneAllocationService::SOURCE_DOSSIER);
                })
                ->leftJoin('spkstone as reserved_stone_line', 'reserved_stone_line.line_id', '=', 'active_allocation.line_id')
                ->leftJoin('spk as reserved_production', function ($join): void {
                    $join->on('reserved_production.row_id', '=', 'reserved_stone_line.row_id')
                        ->where('reserved_production.is_deleted', 0);
                });
        }

        $query = $query
            ->when($status === self::STATUS_AVAILABLE, fn (Builder $builder) => $builder
                ->where(fn (Builder $innerQuery) => $this->whereSelectable($innerQuery)))
            ->when($status === self::STATUS_RESERVED && app(SpkStoneAllocationService::class)->tableAvailable(), fn (Builder $builder) => $builder
                ->whereNotNull('active_allocation.row_id')
                ->where(fn (Builder $innerQuery) => $this->whereAvailable($innerQuery)))
            ->when($status === self::STATUS_USED, fn (Builder $builder) => $builder
                ->where('diamond.is_used', 1))
            ->when($status === self::STATUS_ACTUAL_AVAILABLE, fn (Builder $builder) => $builder
                ->where('diamond.is_used', 0))
            ->when(filled($diamondType), fn (Builder $builder): Builder => $builder
                ->where('diamond.diamond_type', $diamondType))
            ->when($shapeId !== null && $shapeId > 0, fn (Builder $builder): Builder => $builder
                ->where('diamond.shape_id', $shapeId))
            ->when(filled($supplier), fn (Builder $builder): Builder => $builder
                ->where('diamond.supplier', $supplier))
            ->when($search !== '', function (Builder $builder) use ($search): void {
                $like = '%'.$search.'%';

                $builder->where(function (Builder $innerQuery) use ($like): void {
                    $innerQuery->where('diamond.doc_no', 'like', $like)
                        ->orWhere('diamond.diamond_type', 'like', $like)
                        ->orWhere('diamond.certificate', 'like', $like)
                        ->orWhere('diamond.color', 'like', $like)
                        ->orWhere('diamond.supplier', 'like', $like)
                        ->orWhere('shape.name', 'like', $like)
                        ->orWhere('mounting_document.doc_no', 'like', $like)
                        ->orWhere('production.spk_no', 'like', $like);

                    if (app(SpkStoneAllocationService::class)->tableAvailable()) {
                        $innerQuery->orWhere('reserved_production.spk_no', 'like', $like);
                    }
                });
            });

        $select = [
            'diamond.row_id',
            'diamond.doc_no',
            'diamond.diamond_type',
            'diamond.shape_id',
            'shape.name as shape_name',
            'shape.code as shape_code',
            'diamond.color',
            'diamond.crt',
            'diamond.certificate',
            'diamond.supplier',
            'diamond.entry_date',
            'diamond.out_date',
            'diamond.is_used',
            'mounting_document.doc_no as mounting_doc_no',
            'mounting_document.row_id as mounting_doc_id',
            'production.spk_no as mounting_spk_no',
            'diamond.created_by',
            'diamond.created_date',
            'diamond.modified_by',
            'diamond.modified_date',
        ];

        if (app(SpkStoneAllocationService::class)->tableAvailable()) {
            $select[] = 'active_allocation.row_id as allocation_id';
            $select[] = 'reserved_production.spk_no as reserved_spk_no';
        } else {
            $select[] = DB::raw('NULL as allocation_id');
            $select[] = DB::raw('NULL as reserved_spk_no');
        }

        return $query->select($select);
    }

    /**
     * @return list<array<string, int|string|null>>
     */
    public function listForMatrixCell(
        ?int $shapeId,
        ?string $crtMin,
        ?string $crtMax,
        ?string $status = null,
        ?string $diamondType = null,
    ): array {
        if (! Schema::connection('third')->hasTable('trdiamond')) {
            return [];
        }

        $query = $this->filteredQuery(
            '',
            $status,
            $diamondType,
            $shapeId !== null && $shapeId > 0 ? $shapeId : null,
            null,
        );

        if ($crtMin !== null && $crtMax !== null) {
            $query->whereBetween('diamond.crt', [$crtMin, $crtMax]);
        }

        return array_values($query
            ->orderBy('diamond.crt')
            ->orderByDesc('diamond.row_id')
            ->get()
            ->map(fn (object $diamond): array => $this->toRow($diamond))
            ->all());
    }

    /**
     * @return list<array{
     *     id: int,
     *     code: string|null,
     *     diamondType: string|null,
     *     shape: string|null,
     *     color: string|null,
     *     crt: string|null,
     *     certificate: string|null,
     *     supplier: string|null,
     *     entryDate: string|null,
     *     outDate: string|null,
     *     status: string,
     *     reservedSpkNo: string|null,
     *     spkNo: string|null,
     *     mountingDocumentNo: string|null,
     *     mountingDocumentId: int|null,
     *     createdBy: string|null,
     *     createdDate: string|null,
     *     modifiedBy: string|null,
     *     modifiedDate: string|null
     * }>
     */
    public function listForStockReportShapeExport(
        ?string $status,
        ?string $diamondType,
        int $shapeId,
    ): array {
        if (! Schema::connection('third')->hasTable('trdiamond') || $shapeId <= 0) {
            return [];
        }

        $query = $this->filteredQuery('', $status, $diamondType, $shapeId, null);
        $this->applyIndexSort($query, 'entry_date', 'desc');

        return $query
            ->get()
            ->map(fn (object $diamond): array => $this->toRow($diamond))
            ->all();
    }

    public static function inventoryStatusLabel(string $status, ?string $reservedSpkNo): string
    {
        if ($status === self::STATUS_USED) {
            return 'Terpakai';
        }

        if ($status === self::STATUS_RESERVED) {
            return filled($reservedSpkNo)
                ? 'Reserved ('.$reservedSpkNo.')'
                : 'Reserved';
        }

        return 'Tersedia';
    }

    /**
     * Rekap stok batu dossier per sel matrix CRT (per shape dan range).
     *
     * @return array{
     *     sections: list<array{
     *         shapeId: int,
     *         shapeLabel: string,
     *         shapeCode: string|null,
     *         rows: list<array{
     *             rangeNo: int,
     *             crtMin: string,
     *             crtMax: string,
     *             available: array{pcs: int, crt: string},
     *             reserved: array{pcs: int, crt: string},
     *             used: array{pcs: int, crt: string},
     *             total: array{pcs: int, crt: string},
     *         }>,
     *         totals: array{
     *             available: array{pcs: int, crt: string},
     *             reserved: array{pcs: int, crt: string},
     *             used: array{pcs: int, crt: string},
     *             total: array{pcs: int, crt: string},
     *         },
     *     }>,
     *     grandTotals: array{
     *         available: array{pcs: int, crt: string},
     *         reserved: array{pcs: int, crt: string},
     *         used: array{pcs: int, crt: string},
     *         total: array{pcs: int, crt: string},
     *     },
     *     unmapped: array{pcs: int, crt: string},
     * }
     */
    public function matrixStockReport(?string $status = null, ?string $diamondType = null): array
    {
        $emptyMetric = static fn (): array => ['pcs' => 0, 'crt' => '0.000'];

        if (! Schema::connection('third')->hasTable('diamond_crt_matrix')
            || ! Schema::connection('third')->hasTable('trdiamond')) {
            return [
                'sections' => [],
                'grandTotals' => [
                    'available' => $emptyMetric(),
                    'reserved' => $emptyMetric(),
                    'used' => $emptyMetric(),
                    'actualAvailable' => $emptyMetric(),
                    'total' => $emptyMetric(),
                ],
                'unmapped' => $emptyMetric(),
            ];
        }

        /** @var array<string, array{
         *     shapeId: int,
         *     crtMin: string,
         *     crtMax: string,
         *     sortOrder: int,
         *     available: array{pcs: int, crt: float},
         *     reserved: array{pcs: int, crt: float},
         *     used: array{pcs: int, crt: float},
         * }> $cells
         */
        $cells = [];

        $matrixRows = DiamondCrtMatrix::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        foreach ($matrixRows as $range) {
            $key = $this->matrixCellKey($range->shape_id, (string) $range->crt_min, (string) $range->crt_max);
            $cells[$key] = [
                'shapeId' => $range->shape_id,
                'crtMin' => number_format((float) $range->crt_min, 3, '.', ''),
                'crtMax' => number_format((float) $range->crt_max, 3, '.', ''),
                'sortOrder' => $range->sort_order,
                'available' => ['pcs' => 0, 'crt' => 0.0],
                'reserved' => ['pcs' => 0, 'crt' => 0.0],
                'used' => ['pcs' => 0, 'crt' => 0.0],
            ];
        }

        $unmapped = ['pcs' => 0, 'crt' => 0.0];

        foreach ($this->filteredQuery('', null, $diamondType, null, null)->get() as $diamond) {
            $inventoryStatus = $this->resolveInventoryStatus($diamond);

            if ($status !== null && $inventoryStatus !== $status) {
                continue;
            }

            $shapeId = filled($diamond->shape_id ?? null) ? (int) $diamond->shape_id : 0;
            $crt = (float) ($diamond->crt ?? 0);

            if ($shapeId <= 0) {
                $unmapped['pcs']++;
                $unmapped['crt'] += $crt;

                continue;
            }

            $cellKey = $this->matchingMatrixCellKey($cells, $shapeId, $crt);

            if ($cellKey === null) {
                $unmapped['pcs']++;
                $unmapped['crt'] += $crt;

                continue;
            }

            $cells[$cellKey][$inventoryStatus]['pcs']++;
            $cells[$cellKey][$inventoryStatus]['crt'] += $crt;
        }

        $shapeIds = array_values(array_unique(array_map(
            fn (array $cell): int => $cell['shapeId'],
            $cells,
        )));

        $shapesById = $shapeIds === []
            ? collect()
            : DB::connection('third')
                ->table('msshape')
                ->whereIn('row_id', $shapeIds)
                ->get(['row_id', 'name', 'code'])
                ->keyBy('row_id');

        $sections = [];
        $sectionIndex = [];

        foreach ($cells as $cell) {
            $shapeId = $cell['shapeId'];

            if (! isset($sectionIndex[$shapeId])) {
                $shape = $shapesById->get($shapeId);
                $sectionIndex[$shapeId] = count($sections);
                $sections[] = [
                    'shapeId' => $shapeId,
                    'shapeLabel' => self::formatShapeLabel(
                        $shape ? $this->nullableString($shape->name) : null,
                        $shape ? $this->nullableString($shape->code) : null,
                    ),
                    'shapeCode' => $shape ? $this->nullableString($shape->code) : null,
                    'rows' => [],
                    'totals' => [
                        'available' => ['pcs' => 0, 'crt' => 0.0],
                        'reserved' => ['pcs' => 0, 'crt' => 0.0],
                        'used' => ['pcs' => 0, 'crt' => 0.0],
                        'actualAvailable' => ['pcs' => 0, 'crt' => 0.0],
                        'total' => ['pcs' => 0, 'crt' => 0.0],
                    ],
                ];
            }

            $available = $cell['available'];
            $reserved = $cell['reserved'];
            $used = $cell['used'];
            $actualAvailablePcs = $available['pcs'] + $reserved['pcs'];
            $actualAvailableCrt = $available['crt'] + $reserved['crt'];
            $totalPcs = $available['pcs'] + $reserved['pcs'] + $used['pcs'];
            $totalCrt = $available['crt'] + $reserved['crt'] + $used['crt'];

            $sectionKey = $sectionIndex[$shapeId];
            $rangeNo = count($sections[$sectionKey]['rows']) + 1;

            $sections[$sectionKey]['rows'][] = [
                'rangeNo' => $rangeNo,
                'crtMin' => $cell['crtMin'],
                'crtMax' => $cell['crtMax'],
                'available' => [
                    'pcs' => $available['pcs'],
                    'crt' => $this->formatMatrixCrtTotal($available['crt']),
                ],
                'reserved' => [
                    'pcs' => $reserved['pcs'],
                    'crt' => $this->formatMatrixCrtTotal($reserved['crt']),
                ],
                'used' => [
                    'pcs' => $used['pcs'],
                    'crt' => $this->formatMatrixCrtTotal($used['crt']),
                ],
                'actualAvailable' => [
                    'pcs' => $actualAvailablePcs,
                    'crt' => $this->formatMatrixCrtTotal($actualAvailableCrt),
                ],
                'total' => [
                    'pcs' => $totalPcs,
                    'crt' => $this->formatMatrixCrtTotal($totalCrt),
                ],
            ];

            foreach (['available', 'reserved', 'used', 'actualAvailable', 'total'] as $bucket) {
                if ($bucket === 'actualAvailable') {
                    $sections[$sectionKey]['totals']['actualAvailable']['pcs'] += $actualAvailablePcs;
                    $sections[$sectionKey]['totals']['actualAvailable']['crt'] += $actualAvailableCrt;

                    continue;
                }

                if ($bucket === 'total') {
                    $sections[$sectionKey]['totals'][$bucket]['pcs'] += $totalPcs;
                    $sections[$sectionKey]['totals'][$bucket]['crt'] += $totalCrt;

                    continue;
                }

                $sections[$sectionKey]['totals'][$bucket]['pcs'] += $cell[$bucket]['pcs'];
                $sections[$sectionKey]['totals'][$bucket]['crt'] += $cell[$bucket]['crt'];
            }
        }

        $grandTotals = [
            'available' => ['pcs' => 0, 'crt' => 0.0],
            'reserved' => ['pcs' => 0, 'crt' => 0.0],
            'used' => ['pcs' => 0, 'crt' => 0.0],
            'actualAvailable' => ['pcs' => 0, 'crt' => 0.0],
            'total' => ['pcs' => 0, 'crt' => 0.0],
        ];

        foreach ($sections as $sectionIndex => $section) {
            foreach (['available', 'reserved', 'used', 'actualAvailable', 'total'] as $bucket) {
                $grandTotals[$bucket]['pcs'] += $section['totals'][$bucket]['pcs'];
                $grandTotals[$bucket]['crt'] += $section['totals'][$bucket]['crt'];
                $sections[$sectionIndex]['totals'][$bucket] = [
                    'pcs' => $section['totals'][$bucket]['pcs'],
                    'crt' => $this->formatMatrixCrtTotal($section['totals'][$bucket]['crt']),
                ];
            }
        }

        foreach (['available', 'reserved', 'used', 'actualAvailable', 'total'] as $bucket) {
            $grandTotals[$bucket] = [
                'pcs' => $grandTotals[$bucket]['pcs'],
                'crt' => $this->formatMatrixCrtTotal($grandTotals[$bucket]['crt']),
            ];
        }

        return [
            'sections' => array_values($sections),
            'grandTotals' => $grandTotals,
            'unmapped' => [
                'pcs' => $unmapped['pcs'],
                'crt' => $this->formatMatrixCrtTotal($unmapped['crt']),
            ],
        ];
    }

    private function matrixCellKey(int $shapeId, string $crtMin, string $crtMax): string
    {
        return $shapeId.'|'.$crtMin.'|'.$crtMax;
    }

    /**
     * @param  array<string, array{shapeId: int, crtMin: string, crtMax: string, sortOrder: int}>  $cells
     */
    private function matchingMatrixCellKey(array $cells, int $shapeId, float $crt): ?string
    {
        $bestKey = null;
        $bestSort = PHP_INT_MAX;

        foreach ($cells as $key => $cell) {
            if ($cell['shapeId'] !== $shapeId) {
                continue;
            }

            $min = (float) $cell['crtMin'];
            $max = (float) $cell['crtMax'];

            if ($crt < $min || $crt > $max) {
                continue;
            }

            if ($cell['sortOrder'] < $bestSort) {
                $bestSort = $cell['sortOrder'];
                $bestKey = $key;
            }
        }

        return $bestKey;
    }

    private function formatMatrixCrtTotal(float $value): string
    {
        return number_format($value, 3, '.', '');
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value !== '' ? $value : null;
    }
}
