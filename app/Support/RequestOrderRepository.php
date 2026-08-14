<?php

namespace App\Support;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use stdClass;

class RequestOrderRepository
{
    /**
     * Search request orders for the SPK create selector.
     *
     * Uses the same joins as legacy vw_request_orderlist because the view
     * may be unavailable when the MySQL definer account is missing.
     *
     * @return Collection<int, array{rowId: int, docNo: string, customer: string, item: string, refSku: string|null}>
     */
    public function search(string $search = '', int $limit = 25): Collection
    {
        $limit = max(1, min($limit, 50));

        $query = $this->baseQuery()
            ->orderByDesc('ro.row_id')
            ->limit($limit);

        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function ($builder) use ($like): void {
                $builder->where('ro.doc_no', 'like', $like)
                    ->orWhere('c.name', 'like', $like)
                    ->orWhere('i.name', 'like', $like)
                    ->orWhere('ro.ref_sku', 'like', $like)
                    ->orWhere('ro.nama_item', 'like', $like);
            });
        }

        return $query->get()->map(fn (stdClass $row): array => $this->toSelectorItem($row));
    }

    /**
     * Find a single request order by document number.
     *
     * @return array{rowId: int, docNo: string, customer: string|null, item: string|null, itemId: int|null, refSku: string|null}|null
     */
    public function findByDocNo(string $docNo): ?array
    {
        $row = $this->baseQuery()
            ->where('ro.doc_no', $docNo)
            ->first();

        if ($row === null) {
            return null;
        }

        return [
            'rowId' => (int) $row->row_id,
            'docNo' => (string) $row->doc_no,
            'customer' => filled($row->customer_name) ? (string) $row->customer_name : null,
            'item' => filled($row->item_name) ? (string) $row->item_name : null,
            'itemId' => $row->item_id !== null ? (int) $row->item_id : null,
            'refSku' => filled($row->ref_sku) ? (string) $row->ref_sku : null,
        ];
    }

    public function existsByDocNo(string $docNo): bool
    {
        return $this->findByDocNo($docNo) !== null;
    }

    /**
     * Mark request order as ON GOING after SPK final approval.
     */
    public function markOngoing(string $docNo): bool
    {
        if ($docNo === '') {
            return false;
        }

        return DB::connection('second')
            ->table('request_order')
            ->where('doc_no', $docNo)
            ->where('is_deleted', 0)
            ->update(['status' => 'ON GOING']) > 0;
    }

    /**
     * @return Builder
     */
    private function baseQuery()
    {
        return DB::connection('second')
            ->table('request_order as ro')
            ->leftJoin('customer as c', 'c.row_id', '=', 'ro.customer_id')
            ->leftJoin('item as i', 'i.row_id', '=', 'ro.item_id')
            ->where('ro.is_deleted', 0)
            ->select([
                'ro.row_id',
                'ro.doc_no',
                'ro.item_id',
                'ro.ref_sku',
                'c.name as customer_name',
                DB::raw('COALESCE(i.name, ro.nama_item) as item_name'),
            ]);
    }

    /**
     * @return array{rowId: int, docNo: string, customer: string, item: string, refSku: string|null}
     */
    private function toSelectorItem(stdClass $row): array
    {
        return [
            'rowId' => (int) $row->row_id,
            'docNo' => (string) $row->doc_no,
            'customer' => filled($row->customer_name) ? (string) $row->customer_name : '—',
            'item' => filled($row->item_name) ? (string) $row->item_name : '—',
            'refSku' => filled($row->ref_sku) ? (string) $row->ref_sku : null,
        ];
    }
}
