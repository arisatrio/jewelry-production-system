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

        $stockQuery = DB::connection('third')
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
                    WHEN UPPER(TRIM(transaction_type.in_out)) = 'OUT'
                        THEN -COALESCE(stone_transaction.pcs, 0)
                    ELSE 0
                END) as stock"
            )
            ->groupBy('stone_transaction.stone_id');

        return DB::connection('third')
            ->table('msstone as stone')
            ->leftJoinSub(
                $stockQuery,
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
                'stone_stock.stock',
            ])
            ->map(fn (object $stone): array => [
                'value' => (string) $stone->row_id,
                'label' => (string) $stone->name,
                'stock' => (string) (int) round((float) ($stone->stock ?? 0)),
            ])
            ->values()
            ->all();
    }

    /**
     * @return LengthAwarePaginator<int, array<string, int|string|null>>
     */
    public function paginate(
        int $periodId,
        string $search,
        ?string $dateFrom,
        ?string $dateTo,
        int $perPage,
    ): LengthAwarePaginator {
        return $this->filteredQuery($periodId, $search, $dateFrom, $dateTo)
            ->orderByDesc('stone_transaction.row_id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(function (object $transaction): array {
                $direction = strtoupper(trim((string) $transaction->in_out));
                $pcs = (float) ($transaction->pcs ?? 0);

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
    ): string {
        $query = $this->filteredQuery($periodId, $search, $dateFrom, $dateTo)
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

    private function formatPcs(float $value): string
    {
        if (abs($value - round($value)) < 0.00001) {
            return (string) (int) round($value);
        }

        return number_format($value, 2, '.', '');
    }
}
