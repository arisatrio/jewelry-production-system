<?php

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class StoneLedger
{
    public const ADDITION_TRANSACTION_TYPE_ID = 14;

    public const DEDUCTION_TRANSACTION_TYPE_ID = 15;

    public const STOCK_STATUS_AVAILABLE = 'available';

    public const STOCK_STATUS_EMPTY = 'empty';

    public const TRANSACTION_EVENT_ENTRY = 'entry';

    public const TRANSACTION_EVENT_OUT = 'out';

    public const DEFAULT_STOCK_SORT = 'name';

    public const DEFAULT_STOCK_DIRECTION = 'asc';

    public const DEFAULT_TRANSACTION_SORT = 'created_date';

    public const DEFAULT_TRANSACTION_DIRECTION = 'desc';

    /**
     * @return list<string>
     */
    public static function stockSorts(): array
    {
        return [
            'name',
            'parcel',
            'size',
            'shape',
            'balance_pcs',
            'reserved_pcs',
            'available_pcs',
            'balance_crt',
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function stockSortOptions(): array
    {
        return [
            ['value' => 'name', 'label' => 'Batu'],
            ['value' => 'parcel', 'label' => 'Parcel'],
            ['value' => 'size', 'label' => 'Ukuran'],
            ['value' => 'shape', 'label' => 'Bentuk'],
            ['value' => 'balance_pcs', 'label' => 'Saldo (pcs)'],
            ['value' => 'reserved_pcs', 'label' => 'Reserved (pcs)'],
            ['value' => 'available_pcs', 'label' => 'Tersedia (pcs)'],
            ['value' => 'balance_crt', 'label' => 'Saldo (crt)'],
        ];
    }

    /**
     * @return list<string>
     */
    public static function transactionSorts(): array
    {
        return [
            'created_date',
            'stone',
            'category',
            'pcs',
            'crt',
            'transaction_type',
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function transactionSortOptions(): array
    {
        return [
            ['value' => 'created_date', 'label' => 'Tanggal'],
            ['value' => 'stone', 'label' => 'Batu'],
            ['value' => 'category', 'label' => 'Jenis'],
            ['value' => 'pcs', 'label' => 'Pcs'],
            ['value' => 'crt', 'label' => 'CRT'],
            ['value' => 'transaction_type', 'label' => 'Tipe transaksi'],
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function directionSortOptions(): array
    {
        return [
            ['value' => 'asc', 'label' => 'A–Z / terkecil'],
            ['value' => 'desc', 'label' => 'Z–A / terbesar'],
        ];
    }

    /**
     * @param  list<string>  $allowed
     */
    public static function resolveSort(string $sort, array $allowed, string $default): string
    {
        if ($sort === '') {
            return $default;
        }

        return in_array($sort, $allowed, true) ? $sort : $default;
    }

    public static function resolveDirection(string $direction): string
    {
        return in_array($direction, ['asc', 'desc'], true) ? $direction : 'desc';
    }

    /**
     * @return array{id: int, label: string}|null
     */
    public function activePeriod(): ?array
    {
        if (! Schema::connection('third')->hasTable('msperiod')) {
            return null;
        }

        $period = DB::connection('third')
            ->table('msperiod')
            ->where('is_active', 'YES')
            ->where('is_deleted', 0)
            ->orderByDesc('row_id')
            ->first(['row_id', 'period']);

        if ($period === null) {
            return null;
        }

        return [
            'id' => (int) $period->row_id,
            'label' => (string) $period->period,
        ];
    }

    /**
     * @return list<array{value: string, label: string, stock: string}>
     */
    public function stoneOptions(?int $periodId): array
    {
        if (
            $periodId === null
            || ! Schema::connection('third')->hasTable('msstone')
        ) {
            return [];
        }

        return DB::connection('third')
            ->table('msstone as stone')
            ->leftJoinSub(
                $this->stockSubquery($periodId),
                'stone_stock',
                'stone_stock.stone_id',
                '=',
                'stone.row_id',
            )
            ->where('stone.is_deleted', 0)
            ->whereNotNull('stone.name')
            ->where('stone.name', '!=', '')
            ->orderBy('stone.name')
            ->get([
                'stone.row_id',
                'stone.name',
                'stone_stock.balance_pcs',
            ])
            ->map(fn (object $stone): array => [
                'value' => (string) $stone->row_id,
                'label' => (string) $stone->name,
                'stock' => (string) (int) round((float) ($stone->balance_pcs ?? 0)),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  list<int>  $stoneIds
     * @return array<int, float> stone_id => saldo pcs pada periode
     */
    public function balancePcsByStone(int $periodId, array $stoneIds): array
    {
        if ($stoneIds === []) {
            return [];
        }

        return DB::connection('third')
            ->query()
            ->fromSub($this->stockSubquery($periodId), 'stone_stock')
            ->whereIn('stone_stock.stone_id', $stoneIds)
            ->pluck('stone_stock.balance_pcs', 'stone_stock.stone_id')
            ->mapWithKeys(fn (mixed $balance, int|string $stoneId): array => [
                (int) $stoneId => (float) $balance,
            ])
            ->all();
    }

    public function availablePcsForStone(int $periodId, int $stoneId): float
    {
        if ($stoneId <= 0) {
            return 0.0;
        }

        $balance = $this->balancePcsByStone($periodId, [$stoneId])[$stoneId] ?? 0.0;

        $reserved = DB::connection('third')
            ->query()
            ->fromSub($this->microReservedSubquery($periodId), 'stone_reserved')
            ->where('stone_reserved.msstone_id', $stoneId)
            ->value('reserved_pcs');

        return max(0.0, $balance - (float) ($reserved ?? 0));
    }

    /**
     * @return LengthAwarePaginator<int, array<string, int|string|null>>
     */
    public function paginateStock(
        int $periodId,
        string $search,
        ?string $stockStatus,
        int $perPage,
        string $sort = self::DEFAULT_STOCK_SORT,
        string $direction = self::DEFAULT_STOCK_DIRECTION,
    ): LengthAwarePaginator {
        $query = $this->stockQuery($periodId, $search, $stockStatus);
        $this->applyStockSort($query, $sort, $direction);

        return $query
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (object $stone): array => $this->toStockRow($stone));
    }

    /**
     * @param  list<int>  $stoneIds
     * @return list<array<string, int|string|null>>
     */
    public function stockByStoneIds(int $periodId, array $stoneIds): array
    {
        if ($stoneIds === []) {
            return [];
        }

        return array_values($this->stockQuery($periodId, '', null)
            ->whereIn('stone.row_id', $stoneIds)
            ->orderBy('stone.name')
            ->get()
            ->map(fn (object $stone): array => $this->toStockRow($stone))
            ->all());
    }

    /**
     * @return array<string, int|string|null>
     */
    private function toStockRow(object $stone): array
    {
        $balancePcs = (float) ($stone->balance_pcs ?? 0);
        $reservedPcs = (float) ($stone->reserved_pcs ?? 0);
        $availablePcs = max(0, $balancePcs - $reservedPcs);

        return [
            'id' => (int) $stone->row_id,
            'name' => (string) $stone->name,
            'parcel' => filled($stone->parcel) ? (string) $stone->parcel : null,
            'size' => filled($stone->stone_size) ? (string) $stone->stone_size : null,
            'shape' => filled($stone->shape_name) ? (string) $stone->shape_name : null,
            'pcsIn' => $this->formatPcs((float) ($stone->pcs_in ?? 0)),
            'pcsOut' => $this->formatPcs((float) ($stone->pcs_out ?? 0)),
            'balancePcs' => $this->formatPcs($balancePcs),
            'reservedPcs' => $this->formatPcs($reservedPcs),
            'availablePcs' => $this->formatPcs($availablePcs),
            'balanceCrt' => number_format((float) ($stone->balance_crt ?? 0), 3, '.', ''),
        ];
    }

    /**
     * @return array{pcs: string, crt: string}
     */
    public function stockTotals(int $periodId, string $search, ?string $stockStatus): array
    {
        $totals = $this->stockSummaryAggregate($periodId, $search, $stockStatus);

        return [
            'pcs' => $this->formatPcs((float) ($totals->total_pcs ?? 0)),
            'crt' => number_format((float) ($totals->total_crt ?? 0), 4, '.', ''),
        ];
    }

    /**
     * @return array{
     *     balance: array{stoneCount: int, pcs: string, crt: string, reservedPcs: string},
     *     available: array{stoneCount: int, pcs: string, crt: string, reservedPcs: string},
     *     empty: array{stoneCount: int, pcs: string, crt: string, reservedPcs: string}
     * }
     */
    public function stockSummaries(int $periodId, string $search): array
    {
        return [
            'balance' => $this->toStockSummarySlice(
                $this->stockSummaryAggregate($periodId, $search, null),
            ),
            'available' => $this->toStockSummarySlice(
                $this->stockSummaryAggregate($periodId, $search, self::STOCK_STATUS_AVAILABLE),
            ),
            'empty' => $this->toStockSummarySlice(
                $this->stockSummaryAggregate($periodId, $search, self::STOCK_STATUS_EMPTY),
            ),
        ];
    }

    /**
     * @return object{stone_count: int|string|null, total_pcs: int|float|string|null, total_crt: int|float|string|null, total_reserved: int|float|string|null}|null
     */
    private function stockSummaryAggregate(
        int $periodId,
        string $search,
        ?string $stockStatus,
    ): ?object {
        return $this->stockQuery($periodId, $search, $stockStatus)
            ->cloneWithout(['columns', 'orders'])
            ->selectRaw(
                'COUNT(*) as stone_count, '
                .'SUM(COALESCE(stone_stock.balance_pcs, 0)) as total_pcs, '
                .'SUM(COALESCE(stone_stock.balance_crt, 0)) as total_crt, '
                .'SUM(COALESCE(stone_reserved.reserved_pcs, 0)) as total_reserved'
            )
            ->first();
    }

    /**
     * @param  object{stone_count: int|string|null, total_pcs: int|float|string|null, total_crt: int|float|string|null, total_reserved: int|float|string|null}|null  $totals
     * @return array{stoneCount: int, pcs: string, crt: string, reservedPcs: string}
     */
    private function toStockSummarySlice(?object $totals): array
    {
        return [
            'stoneCount' => (int) ($totals->stone_count ?? 0),
            'pcs' => $this->formatPcs((float) ($totals->total_pcs ?? 0)),
            'crt' => number_format((float) ($totals->total_crt ?? 0), 4, '.', ''),
            'reservedPcs' => $this->formatPcs((float) ($totals->total_reserved ?? 0)),
        ];
    }

    /**
     * @return LengthAwarePaginator<int, array<string, int|string|null>>
     */
    public function paginate(
        int $periodId,
        string $search,
        ?string $dateFrom,
        ?string $dateTo,
        ?string $eventType,
        int $perPage,
        string $sort = self::DEFAULT_TRANSACTION_SORT,
        string $direction = self::DEFAULT_TRANSACTION_DIRECTION,
    ): LengthAwarePaginator {
        $query = $this->filteredQuery($periodId, $search, $dateFrom, $dateTo, $eventType);
        $this->applyTransactionSort($query, $sort, $direction);

        return $query
            ->paginate($perPage)
            ->withQueryString()
            ->through(function (object $transaction): array {
                $direction = strtoupper(trim((string) $transaction->in_out));
                $pcs = (float) ($transaction->pcs ?? 0);
                $transactionTypeId = (int) $transaction->transtype_id;
                $refRowId = (int) $transaction->ref_row_id;

                return [
                    'id' => (int) $transaction->row_id,
                    'period' => (string) $transaction->period,
                    'direction' => (string) $transaction->in_out,
                    'category' => match ($direction) {
                        'OUT' => 'Pemakaian',
                        'IN' => 'Penambahan',
                        default => 'Lainnya',
                    },
                    'transactionType' => (string) $transaction->transaction_type,
                    'stone' => (string) $transaction->stone_name,
                    'pcs' => $this->formatPcs($pcs),
                    'crt' => filled($transaction->crt)
                        ? number_format((float) $transaction->crt, 4, '.', '')
                        : null,
                    'signedPcs' => $this->formatPcs(
                        $direction === 'OUT' ? -$pcs : $pcs,
                    ),
                    'productionNo' => filled($transaction->spk_no)
                        ? (string) $transaction->spk_no
                        : null,
                    'documentNo' => filled($transaction->document_no)
                        ? (string) $transaction->document_no
                        : null,
                    'mountingDocumentId' => in_array($transactionTypeId, [7, 8, 9], true)
                        && $refRowId > 0
                        ? $refRowId
                        : null,
                    'notes' => filled($transaction->notes)
                        ? (string) $transaction->notes
                        : (filled($transaction->txt) ? (string) $transaction->txt : null),
                    'createdBy' => filled($transaction->created_by)
                        ? (string) $transaction->created_by
                        : null,
                    'createdDate' => filled($transaction->created_date)
                        ? (string) $transaction->created_date
                        : null,
                ];
            });
    }

    public function total(
        int $periodId,
        string $search,
        ?string $dateFrom,
        ?string $dateTo,
        ?string $eventType,
    ): string {
        $query = $this->filteredQuery($periodId, $search, $dateFrom, $dateTo, $eventType)
            ->cloneWithout(['columns', 'orders']);

        $total = $query
            ->selectRaw(
                "SUM(CASE
                    WHEN UPPER(TRIM(transaction_type.in_out)) = 'OUT'
                        THEN -COALESCE(stone_transaction.pcs, 0)
                    WHEN UPPER(TRIM(transaction_type.in_out)) = 'IN'
                        THEN COALESCE(stone_transaction.pcs, 0)
                    ELSE 0
                END) as signed_total"
            )
            ->value('signed_total');

        return $this->formatPcs((float) ($total ?? 0));
    }

    public function store(
        int $periodId,
        string $transactionKind,
        int $stoneId,
        string $pcs,
        ?string $crt,
        ?string $notes,
        string $actor,
    ): void {
        $transactionTypeId = $transactionKind === 'addition'
            ? self::ADDITION_TRANSACTION_TYPE_ID
            : self::DEDUCTION_TRANSACTION_TYPE_ID;

        DB::connection('third')->table('trstone')->insert([
            'period_id' => $periodId,
            'transtype_id' => $transactionTypeId,
            'ref_row_id' => 0,
            'stone_id' => $stoneId,
            'spk_id' => 0,
            'pcs' => (float) $pcs,
            'crt' => filled($crt) ? number_format((float) $crt, 4, '.', '') : null,
            'txt' => null,
            'notes' => $notes,
            'is_used' => 0,
            'is_deleted' => 0,
            'created_date' => now(),
            'created_by' => $actor,
            'modified_date' => now(),
            'modified_by' => $actor,
            'deleted_date' => null,
            'deleted_by' => null,
        ]);
    }

    private function filteredQuery(
        int $periodId,
        string $search,
        ?string $dateFrom,
        ?string $dateTo,
        ?string $eventType,
    ): Builder {
        return DB::connection('third')
            ->table('trstone as stone_transaction')
            ->join(
                'msstone as stone',
                'stone.row_id',
                '=',
                'stone_transaction.stone_id',
            )
            ->join(
                'mstranstype as transaction_type',
                'transaction_type.row_id',
                '=',
                'stone_transaction.transtype_id',
            )
            ->join('msperiod as period', 'period.row_id', '=', 'stone_transaction.period_id')
            ->leftJoin('diamondmounting as mounting_document', function ($join): void {
                $join->on('mounting_document.row_id', '=', 'stone_transaction.ref_row_id')
                    ->whereIn('stone_transaction.transtype_id', [7, 8, 9])
                    ->where('mounting_document.is_deleted', 0);
            })
            ->leftJoin('spk as production', function ($join): void {
                $join->on('production.row_id', '=', 'stone_transaction.spk_id')
                    ->where('production.is_deleted', 0);
            })
            ->where('stone_transaction.period_id', $periodId)
            ->where('stone_transaction.is_deleted', 0)
            ->where('stone.is_deleted', 0)
            ->where('transaction_type.is_deleted', 0)
            ->when($dateFrom !== null, fn (Builder $query) => $query
                ->whereDate('stone_transaction.created_date', '>=', $dateFrom))
            ->when($dateTo !== null, fn (Builder $query) => $query
                ->whereDate('stone_transaction.created_date', '<=', $dateTo))
            ->when(
                $eventType === self::TRANSACTION_EVENT_ENTRY,
                fn (Builder $query) => $query->whereRaw(
                    "UPPER(TRIM(transaction_type.in_out)) = 'IN'",
                ),
            )
            ->when(
                $eventType === self::TRANSACTION_EVENT_OUT,
                fn (Builder $query) => $query->whereRaw(
                    "UPPER(TRIM(transaction_type.in_out)) = 'OUT'",
                ),
            )
            ->when($search !== '', function (Builder $query) use ($search): void {
                $like = '%'.$search.'%';

                $query->where(function (Builder $innerQuery) use ($like): void {
                    $innerQuery->where('stone.name', 'like', $like)
                        ->orWhere('transaction_type.name', 'like', $like)
                        ->orWhere('mounting_document.doc_no', 'like', $like)
                        ->orWhere('production.spk_no', 'like', $like)
                        ->orWhere('stone_transaction.notes', 'like', $like)
                        ->orWhere('stone_transaction.txt', 'like', $like)
                        ->orWhere('stone_transaction.created_by', 'like', $like);
                });
            })
            ->select([
                'stone_transaction.row_id',
                'stone_transaction.ref_row_id',
                'stone_transaction.transtype_id',
                'period.period',
                'transaction_type.in_out',
                'transaction_type.name as transaction_type',
                'stone.name as stone_name',
                'stone_transaction.pcs',
                'stone_transaction.crt',
                'production.spk_no',
                'mounting_document.doc_no as document_no',
                'stone_transaction.notes',
                'stone_transaction.txt',
                'stone_transaction.created_by',
                'stone_transaction.created_date',
            ]);
    }

    private function stockQuery(int $periodId, string $search, ?string $stockStatus): Builder
    {
        $reservedSubquery = $this->microReservedSubquery($periodId);

        return DB::connection('third')
            ->table('msstone as stone')
            ->leftJoinSub(
                $this->stockSubquery($periodId),
                'stone_stock',
                'stone_stock.stone_id',
                '=',
                'stone.row_id',
            )
            ->leftJoinSub(
                $reservedSubquery,
                'stone_reserved',
                'stone_reserved.msstone_id',
                '=',
                'stone.row_id',
            )
            ->leftJoin('msshape as shape', 'shape.row_id', '=', 'stone.shape_id')
            ->where('stone.is_deleted', 0)
            ->whereNotNull('stone.name')
            ->where('stone.name', '!=', '')
            ->when($stockStatus === self::STOCK_STATUS_AVAILABLE, fn (Builder $query) => $query
                ->whereRaw('(COALESCE(stone_stock.balance_pcs, 0) - COALESCE(stone_reserved.reserved_pcs, 0)) > 0'))
            ->when($stockStatus === self::STOCK_STATUS_EMPTY, fn (Builder $query) => $query
                ->whereRaw('(COALESCE(stone_stock.balance_pcs, 0) - COALESCE(stone_reserved.reserved_pcs, 0)) <= 0'))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $like = '%'.$search.'%';

                $query->where(function (Builder $innerQuery) use ($like): void {
                    $innerQuery->where('stone.name', 'like', $like)
                        ->orWhere('stone.parcel', 'like', $like)
                        ->orWhere('shape.name', 'like', $like);
                });
            })
            ->select([
                'stone.row_id',
                'stone.name',
                'stone.parcel',
                'stone.stone_size',
                'shape.name as shape_name',
                'stone_stock.pcs_in',
                'stone_stock.pcs_out',
                'stone_stock.balance_pcs',
                'stone_stock.balance_crt',
                'stone_reserved.reserved_pcs',
            ]);
    }

    private function microReservedSubquery(int $periodId): Builder
    {
        if (! app(SpkStoneAllocationService::class)->tableAvailable()) {
            return DB::connection('third')
                ->table('msstone')
                ->whereRaw('1 = 0')
                ->selectRaw('row_id as msstone_id, 0 as reserved_pcs');
        }

        return DB::connection('third')
            ->table(SpkStoneAllocationService::TABLE)
            ->where('is_deleted', 0)
            ->where('status', SpkStoneAllocationService::STATUS_RESERVED)
            ->where('source', SpkStoneAllocationService::SOURCE_MICRO)
            ->where('period_id', $periodId)
            ->select('msstone_id')
            ->selectRaw('SUM(COALESCE(reserved_pcs, 0)) as reserved_pcs')
            ->groupBy('msstone_id');
    }

    private function stockSubquery(int $periodId): Builder
    {
        return DB::connection('third')
            ->table('trstone as stone_transaction')
            ->join(
                'mstranstype as transaction_type',
                'transaction_type.row_id',
                '=',
                'stone_transaction.transtype_id',
            )
            ->where('stone_transaction.period_id', $periodId)
            ->where('stone_transaction.is_deleted', 0)
            ->where('transaction_type.is_deleted', 0)
            ->select('stone_transaction.stone_id')
            ->selectRaw(
                "SUM(CASE
                    WHEN UPPER(TRIM(transaction_type.in_out)) = 'IN'
                        THEN COALESCE(stone_transaction.pcs, 0)
                    ELSE 0
                END) as pcs_in,
                SUM(CASE
                    WHEN UPPER(TRIM(transaction_type.in_out)) = 'OUT'
                        THEN COALESCE(stone_transaction.pcs, 0)
                    ELSE 0
                END) as pcs_out,
                SUM(CASE
                    WHEN UPPER(TRIM(transaction_type.in_out)) = 'IN'
                        THEN COALESCE(stone_transaction.pcs, 0)
                    WHEN UPPER(TRIM(transaction_type.in_out)) = 'OUT'
                        THEN -COALESCE(stone_transaction.pcs, 0)
                    ELSE 0
                END) as balance_pcs,
                SUM(CASE
                    WHEN UPPER(TRIM(transaction_type.in_out)) = 'IN'
                        THEN COALESCE(stone_transaction.crt, 0)
                    WHEN UPPER(TRIM(transaction_type.in_out)) = 'OUT'
                        THEN -COALESCE(stone_transaction.crt, 0)
                    ELSE 0
                END) as balance_crt"
            )
            ->groupBy('stone_transaction.stone_id');
    }

    private function applyStockSort(Builder $query, string $sort, string $direction): void
    {
        $direction = self::resolveDirection($direction);
        $sort = self::resolveSort($sort, self::stockSorts(), self::DEFAULT_STOCK_SORT);

        $column = match ($sort) {
            'parcel' => 'stone.parcel',
            'size' => 'stone.stone_size',
            'shape' => 'shape.name',
            'balance_pcs' => 'stone_stock.balance_pcs',
            'reserved_pcs' => 'stone_reserved.reserved_pcs',
            'available_pcs' => DB::raw(
                '(COALESCE(stone_stock.balance_pcs, 0) - COALESCE(stone_reserved.reserved_pcs, 0))',
            ),
            'balance_crt' => 'stone_stock.balance_crt',
            default => 'stone.name',
        };

        $query->orderBy($column, $direction);

        if ($sort !== 'name') {
            $query->orderBy('stone.name');
        }
    }

    private function applyTransactionSort(Builder $query, string $sort, string $direction): void
    {
        $direction = self::resolveDirection($direction);
        $sort = self::resolveSort(
            $sort,
            self::transactionSorts(),
            self::DEFAULT_TRANSACTION_SORT,
        );

        $column = match ($sort) {
            'stone' => 'stone.name',
            'category' => 'transaction_type.in_out',
            'pcs' => 'stone_transaction.pcs',
            'crt' => 'stone_transaction.crt',
            'transaction_type' => 'transaction_type.name',
            default => 'stone_transaction.created_date',
        };

        $query->orderBy($column, $direction);
        $query->orderByDesc('stone_transaction.row_id');
    }

    private function formatPcs(float $value): string
    {
        if (abs($value - round($value)) < 0.00001) {
            return (string) (int) round($value);
        }

        return number_format($value, 2, '.', '');
    }
}
