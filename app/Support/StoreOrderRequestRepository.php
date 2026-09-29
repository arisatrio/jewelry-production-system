<?php

namespace App\Support;

use App\Models\Production;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use stdClass;
use Throwable;

class StoreOrderRequestRepository
{
    public const TAB_PENDING = 'pending';

    public const TAB_WITH_SPK = 'with_spk';

    public const TABS = [self::TAB_PENDING, self::TAB_WITH_SPK];

    private const PENDING_STATUS = 'ORDER';

    private const EXCLUDED_TYPE_ORDERS = ['REPARASI'];

    /**
     * Jumlah pesanan toko (request_order status ORDER, bukan reparasi) yang belum dibuatkan SPK.
     */
    public function pendingSpkCount(): int
    {
        return $this->tabQuery(self::TAB_PENDING, '', $this->spkNosByRequestOrderNo())->count();
    }

    /**
     * Daftar pesanan toko per tab (belum / sudah dibuatkan SPK) untuk modal di halaman index SPK.
     *
     * @return array{
     *     data: list<array{rowId: int, docNo: string, transDate: string, estimatedDate: string, targetDaysLeft: int|null, store: string, customer: string, item: string, refSku: string|null, typeOrder: string|null, paymentStatus: string|null, notes: string|null, imageUrl: string|null, goldInfo: string|null, createdBy: string|null, spks: list<array{spkNo: string, status: string}>}>,
     *     meta: array{currentPage: int, lastPage: int, perPage: int, total: int, tabCounts: array{pending: int, with_spk: int}}
     * }
     */
    public function paginate(string $tab = self::TAB_PENDING, string $search = '', int $page = 1, int $perPage = 25): array
    {
        $tab = in_array($tab, self::TABS, true) ? $tab : self::TAB_PENDING;
        $spkNosByRequestOrderNo = $this->spkNosByRequestOrderNo();

        $paginator = $this->tabQuery($tab, $search, $spkNosByRequestOrderNo)
            ->leftJoin('item as i', 'i.row_id', '=', 'ro.item_id')
            ->leftJoin('store as s', 's.row_id', '=', 'ro.store_id')
            ->leftJoin('sku_master as sm', 'sm.id', '=', 'ro.sku_id')
            ->orderByDesc('ro.trans_date')
            ->orderByDesc('ro.row_id')
            ->select([
                'ro.row_id',
                'ro.doc_no',
                'ro.trans_date',
                'ro.estimated_date',
                'ro.type_order',
                'ro.ref_sku',
                'ro.notes',
                'ro.photo_file',
                'ro.warna_emas',
                'ro.kadar_emas',
                'ro.berat_emas',
                'ro.is_fully_paid',
                'ro.created_by',
                'c.name as customer_name',
                's.name as store_name',
                'sm.image_url as sku_image_url',
                DB::raw('COALESCE(i.name, ro.nama_item) as item_name'),
            ])
            ->paginate($perPage, ['*'], 'page', max(1, $page));

        $spksByRequestOrderNo = $this->spksByRequestOrderNo(array_values(array_filter(
            array_map(fn (stdClass $row): string => trim((string) $row->doc_no), $paginator->items()),
            fn (string $docNo): bool => $docNo !== '' && isset($spkNosByRequestOrderNo[$docNo]),
        )));

        return [
            'data' => array_values(array_map(
                fn (stdClass $row): array => $this->toListRow($row, $spksByRequestOrderNo),
                $paginator->items(),
            )),
            'meta' => [
                'currentPage' => $paginator->currentPage(),
                'lastPage' => max(1, $paginator->lastPage()),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
                'tabCounts' => [
                    self::TAB_PENDING => $tab === self::TAB_PENDING
                        ? $paginator->total()
                        : $this->tabQuery(self::TAB_PENDING, $search, $spkNosByRequestOrderNo)->count(),
                    self::TAB_WITH_SPK => $tab === self::TAB_WITH_SPK
                        ? $paginator->total()
                        : $this->tabQuery(self::TAB_WITH_SPK, $search, $spkNosByRequestOrderNo)->count(),
                ],
            ],
        ];
    }

    /**
     * @param  array<string, list<string>>  $spkNosByRequestOrderNo
     */
    private function tabQuery(string $tab, string $search, array $spkNosByRequestOrderNo): Builder
    {
        $requestOrderNosWithSpk = array_map('strval', array_keys($spkNosByRequestOrderNo));

        return DB::connection('second')
            ->table('request_order as ro')
            ->leftJoin('customer as c', 'c.row_id', '=', 'ro.customer_id')
            ->where('ro.is_deleted', 0)
            ->when($tab === self::TAB_PENDING, function (Builder $query) use ($requestOrderNosWithSpk): void {
                $query->where('ro.status', self::PENDING_STATUS)
                    ->where(function (Builder $query): void {
                        $query->whereNull('ro.type_order')
                            ->orWhereNotIn('ro.type_order', self::EXCLUDED_TYPE_ORDERS);
                    })
                    ->when($requestOrderNosWithSpk !== [], function (Builder $query) use ($requestOrderNosWithSpk): void {
                        $query->whereNotIn('ro.doc_no', $requestOrderNosWithSpk);
                    });
            })
            ->when($tab === self::TAB_WITH_SPK, function (Builder $query) use ($requestOrderNosWithSpk): void {
                $query->whereIn('ro.doc_no', $requestOrderNosWithSpk === [] ? [''] : $requestOrderNosWithSpk);
            })
            ->when($search !== '', function (Builder $query) use ($search, $spkNosByRequestOrderNo): void {
                $like = '%'.$search.'%';
                $requestOrderNosMatchingSpk = array_map('strval', array_keys(array_filter(
                    $spkNosByRequestOrderNo,
                    fn (array $spkNos): bool => collect($spkNos)->contains(fn (string $spkNo): bool => stripos($spkNo, $search) !== false),
                )));

                $query->where(function (Builder $query) use ($like, $requestOrderNosMatchingSpk): void {
                    $query->where('ro.doc_no', 'like', $like)
                        ->orWhere('ro.type_order', 'like', $like)
                        ->orWhere('c.name', 'like', $like)
                        ->orWhereExists(fn (Builder $query) => $query->from('store as ss')
                            ->whereColumn('ss.row_id', 'ro.store_id')
                            ->where('ss.name', 'like', $like))
                        ->orWhereExists(fn (Builder $query) => $query->from('item as ii')
                            ->whereColumn('ii.row_id', 'ro.item_id')
                            ->where('ii.name', 'like', $like))
                        ->orWhere('ro.nama_item', 'like', $like)
                        ->orWhere('ro.ref_sku', 'like', $like)
                        ->orWhere('ro.notes', 'like', $like)
                        ->when($requestOrderNosMatchingSpk !== [], function (Builder $query) use ($requestOrderNosMatchingSpk): void {
                            $query->orWhereIn('ro.doc_no', $requestOrderNosMatchingSpk);
                        });
                });
            });
    }

    /**
     * No SPK aktif per nomor request order (koneksi third).
     *
     * @return array<string, list<string>>
     */
    private function spkNosByRequestOrderNo(): array
    {
        return Production::query()
            ->notDeleted()
            ->whereNotNull('request_order_no')
            ->where('request_order_no', '!=', '')
            ->orderBy('spk_no')
            ->get(['request_order_no', 'spk_no'])
            ->groupBy(fn (Production $production): string => trim((string) $production->request_order_no))
            ->reject(fn ($productions, int|string $requestOrderNo): bool => (string) $requestOrderNo === '')
            ->map(fn ($productions): array => $productions
                ->map(fn (Production $production): string => trim((string) $production->spk_no))
                ->filter(fn (string $spkNo): bool => $spkNo !== '')
                ->unique()
                ->values()
                ->all())
            ->all();
    }

    /**
     * SPK aktif beserta label status (sama seperti daftar SPK) per nomor request order.
     *
     * @param  list<string>  $requestOrderNos
     * @return array<string, list<array{spkNo: string, status: string}>>
     */
    private function spksByRequestOrderNo(array $requestOrderNos): array
    {
        if ($requestOrderNos === []) {
            return [];
        }

        $productions = Production::query()
            ->notDeleted()
            ->whereIn('request_order_no', $requestOrderNos)
            ->orderBy('spk_no')
            ->get();
        $doneKinds = SpkDashboardAnalytics::completedProductionKinds($productions->pluck('row_id')->all());

        return $productions
            ->groupBy(fn (Production $production): string => trim((string) $production->request_order_no))
            ->map(fn ($productions): array => $productions
                ->map(fn (Production $production): array => [
                    'spkNo' => trim((string) $production->spk_no),
                    'status' => SpkDashboardAnalytics::backlogStatusLabel(
                        $production,
                        $doneKinds[(int) $production->row_id] ?? false,
                    ),
                ])
                ->values()
                ->all())
            ->all();
    }

    /**
     * @param  array<string, list<array{spkNo: string, status: string}>>  $spksByRequestOrderNo
     * @return array{rowId: int, docNo: string, transDate: string, estimatedDate: string, targetDaysLeft: int|null, store: string, customer: string, item: string, refSku: string|null, typeOrder: string|null, paymentStatus: string|null, notes: string|null, imageUrl: string|null, goldInfo: string|null, createdBy: string|null, spks: list<array{spkNo: string, status: string}>}
     */
    private function toListRow(stdClass $row, array $spksByRequestOrderNo): array
    {
        $docNo = trim((string) ($row->doc_no ?? ''));

        return [
            'rowId' => (int) $row->row_id,
            'docNo' => $docNo !== '' ? $docNo : '-',
            'transDate' => $this->displayDate($row->trans_date),
            'estimatedDate' => $this->displayDate($row->estimated_date),
            'targetDaysLeft' => $this->daysLeft($row->estimated_date),
            'store' => $this->filledString($row->store_name) ?? '-',
            'customer' => $this->filledString($row->customer_name) ?? '-',
            'item' => $this->filledString($row->item_name) ?? '-',
            'refSku' => $this->filledString($row->ref_sku),
            'typeOrder' => $this->filledString($row->type_order),
            'paymentStatus' => $row->is_fully_paid === null ? null : ((int) $row->is_fully_paid === 1 ? 'Lunas' : 'Belum Lunas'),
            'notes' => $this->filledString($row->notes),
            'imageUrl' => $this->filledString($row->photo_file) ?? $this->filledString($row->sku_image_url),
            'goldInfo' => $this->goldInfo($row),
            'createdBy' => $this->filledString($row->created_by),
            'spks' => $spksByRequestOrderNo[$docNo] ?? [],
        ];
    }

    private function goldInfo(stdClass $row): ?string
    {
        $parts = array_filter([
            $this->filledString($row->warna_emas),
            $this->filledString($row->kadar_emas),
            $this->filledString($row->berat_emas) !== null ? $row->berat_emas.' gr' : null,
        ]);

        return $parts === [] ? null : implode(' · ', $parts);
    }

    private function filledString(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function daysLeft(mixed $value): ?int
    {
        $date = $this->filledString($value);

        if ($date === null) {
            return null;
        }

        try {
            return (int) now()->startOfDay()->diffInDays(Carbon::parse($date)->startOfDay(), false);
        } catch (Throwable) {
            return null;
        }
    }

    private function displayDate(mixed $value): string
    {
        $date = $this->filledString($value);

        if ($date === null) {
            return '-';
        }

        try {
            return Carbon::parse($date)->format('d-M-Y');
        } catch (Throwable) {
            return $date;
        }
    }
}
