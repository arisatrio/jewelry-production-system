<?php

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class GoldMaterialLedger
{
    public const ADDITION_TRANSACTION_TYPE_ID = 12;

    public const DEDUCTION_TRANSACTION_TYPE_ID = 13;

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
    public function materialOptions(?int $periodId): array
    {
        if (
            $periodId === null
            || ! Schema::connection('third')->hasTable('msmaterialgold')
        ) {
            return [];
        }

        $stockQuery = DB::connection('third')
            ->table('trmaterialgold as gold_transaction')
            ->join(
                'mstranstype as transaction_type',
                'transaction_type.row_id',
                '=',
                'gold_transaction.transtype_id',
            )
            ->where('gold_transaction.period_id', $periodId)
            ->where('gold_transaction.is_deleted', 0)
            ->where('transaction_type.is_deleted', 0)
            ->select('gold_transaction.materialgold_id')
            ->selectRaw(
                "SUM(CASE
                    WHEN UPPER(TRIM(transaction_type.in_out)) = 'IN'
                        THEN COALESCE(gold_transaction.weight, 0)
                    WHEN UPPER(TRIM(transaction_type.in_out)) = 'OUT'
                        THEN -COALESCE(gold_transaction.weight, 0)
                    ELSE 0
                END) as stock"
            )
            ->groupBy('gold_transaction.materialgold_id');

        return DB::connection('third')
            ->table('msmaterialgold as material')
            ->leftJoinSub(
                $stockQuery,
                'material_stock',
                'material_stock.materialgold_id',
                '=',
                'material.row_id',
            )
            ->where('material.is_deleted', 0)
            ->whereNotNull('material.name')
            ->where('material.name', '!=', '')
            ->orderBy('material.name')
            ->get([
                'material.row_id',
                'material.name',
                'material_stock.stock',
            ])
            ->map(fn (object $material): array => [
                'value' => (string) $material->row_id,
                'label' => (string) $material->name,
                'stock' => number_format((float) ($material->stock ?? 0), 2, '.', ''),
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
            ->orderByDesc('gold_transaction.row_id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (object $transaction): array => [
                'id' => (int) $transaction->row_id,
                'period' => (string) $transaction->period,
                'direction' => (string) $transaction->in_out,
                'category' => strtoupper(trim((string) $transaction->in_out)) === 'OUT'
                    ? 'Pemakaian'
                    : 'Penambahan',
                'transactionType' => (string) $transaction->transaction_type,
                'material' => (string) $transaction->material_name,
                'weight' => number_format((float) $transaction->weight, 2, '.', ''),
                'signedWeight' => number_format(
                    strtoupper(trim((string) $transaction->in_out)) === 'OUT'
                        ? -(float) $transaction->weight
                        : (float) $transaction->weight,
                    2,
                    '.',
                    '',
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
            ]);
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
                        THEN -COALESCE(gold_transaction.weight, 0)
                    ELSE COALESCE(gold_transaction.weight, 0)
                END) as signed_total"
            )
            ->value('signed_total');

        return number_format((float) ($total ?? 0), 2, '.', '');
    }

    public function store(
        int $periodId,
        string $transactionKind,
        int $materialId,
        string $weight,
        ?string $notes,
        string $actor,
    ): void {
        $transactionTypeId = $transactionKind === 'addition'
            ? self::ADDITION_TRANSACTION_TYPE_ID
            : self::DEDUCTION_TRANSACTION_TYPE_ID;

        DB::connection('third')->table('trmaterialgold')->insert([
            'period_id' => $periodId,
            'transtype_id' => $transactionTypeId,
            'ref_row_id' => 0,
            'materialgold_id' => $materialId,
            'spk_id' => 0,
            'weight' => number_format((float) $weight, 2, '.', ''),
            'txt' => null,
            'notes' => $notes,
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
            ->table('trmaterialgold as gold_transaction')
            ->join(
                'msmaterialgold as material',
                'material.row_id',
                '=',
                'gold_transaction.materialgold_id',
            )
            ->join(
                'mstranstype as transaction_type',
                'transaction_type.row_id',
                '=',
                'gold_transaction.transtype_id',
            )
            ->join('msperiod as period', 'period.row_id', '=', 'gold_transaction.period_id')
            ->leftJoin('coran as coran_document', function ($join): void {
                $join->on('coran_document.row_id', '=', 'gold_transaction.ref_row_id')
                    ->whereIn('gold_transaction.transtype_id', [1, 2, 3, 4, 10, 11])
                    ->where('coran_document.is_deleted', 0);
            })
            ->leftJoin('finishinghandmade as finishing_document', function ($join): void {
                $join->on('finishing_document.row_id', '=', 'gold_transaction.ref_row_id')
                    ->whereIn('gold_transaction.transtype_id', [5, 6])
                    ->where('finishing_document.is_deleted', 0);
            })
            ->leftJoin('spk as production', function ($join): void {
                $join->on('production.row_id', '=', 'finishing_document.spk_id')
                    ->where('production.is_deleted', 0);
            })
            ->where('gold_transaction.period_id', $periodId)
            ->where('gold_transaction.is_deleted', 0)
            ->where('material.is_deleted', 0)
            ->where('transaction_type.is_deleted', 0)
            ->when($dateFrom !== null, fn (Builder $query) => $query
                ->whereDate('gold_transaction.created_date', '>=', $dateFrom))
            ->when($dateTo !== null, fn (Builder $query) => $query
                ->whereDate('gold_transaction.created_date', '<=', $dateTo))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $like = '%'.$search.'%';

                $query->where(function (Builder $innerQuery) use ($like): void {
                    $innerQuery->where('material.name', 'like', $like)
                        ->orWhere('transaction_type.name', 'like', $like)
                        ->orWhere('coran_document.doc_no', 'like', $like)
                        ->orWhere('finishing_document.doc_no', 'like', $like)
                        ->orWhere('production.spk_no', 'like', $like)
                        ->orWhere('gold_transaction.notes', 'like', $like)
                        ->orWhere('gold_transaction.txt', 'like', $like)
                        ->orWhere('gold_transaction.created_by', 'like', $like);
                });
            })
            ->select([
                'gold_transaction.row_id',
                'period.period',
                'transaction_type.in_out',
                'transaction_type.name as transaction_type',
                'material.name as material_name',
                'gold_transaction.weight',
                'production.spk_no',
                'gold_transaction.notes',
                'gold_transaction.txt',
                'gold_transaction.created_by',
                'gold_transaction.created_date',
            ])
            ->selectRaw(
                'COALESCE(coran_document.doc_no, finishing_document.doc_no) as document_no'
            );
    }
}
