<?php

namespace App\Http\Controllers;

use App\Models\Production;
use App\Support\FakturBarangJadiReceiptService;
use App\Support\InventoryPluSkuLookup;
use App\Support\RequestOrderRepository;
use App\Support\SpkDashboardAnalytics;
use App\Support\SpkOrderReference;
use App\Support\SpkProcessMapper;
use App\Support\SpkProductionListItemPresentation;
use App\Support\SpkService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class InventoryBarangJadiController extends Controller
{
    /**
     * Daftar SPK produksi selesai (DONE Rangka & DONE Barang Jadi).
     */
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();
        $perPage = $request->integer('per_page', 50);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 50;
        $statusKinds = $this->resolveStatusKinds($request->input('status'));
        $pluStatus = $this->resolvePluStatus($request->input('plu_status'));
        $skuStatus = $this->resolveSkuStatus($request->input('sku_status'));
        $analytics = new SpkDashboardAnalytics;
        $lookup = app(InventoryPluSkuLookup::class);
        $pluSummary = $this->pluSummaryCounts($analytics);
        $skuSummary = $this->skuSummaryCounts($analytics, $lookup);

        $page = $this->baseCompletedProductionQuery($analytics, $statusKinds)
            ->with(['sku', 'categoryPrefix'])
            ->tap(function (Builder $query) use ($pluStatus): void {
                if ($pluStatus === 'belum') {
                    $this->applyBelumDeliveryAndPluScope($query);
                } elseif ($pluStatus === 'sudah') {
                    $this->applySudahDeliveryOrPluSetScope($query);
                }
            })
            ->tap(function (Builder $query) use ($skuStatus, $lookup): void {
                if ($skuStatus === 'beda') {
                    $this->applySkuBedaScope($query, $lookup);
                } elseif ($skuStatus === 'sesuai') {
                    $this->applySkuSesuaiScope($query, $lookup);
                }
            })
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('spk_no', 'like', "%{$search}%")
                        ->orWhere('item_name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('plu', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('row_id')
            ->paginate($perPage)
            ->withQueryString();

        $productions = $page->getCollection();
        $skuFromPluByPlu = app(InventoryPluSkuLookup::class)->presentationsByPlu(
            $productions->map(fn (Production $production): ?string => filled($production->plu)
                ? trim((string) $production->plu)
                : null),
        );
        $requestOrdersByDocNo = app(RequestOrderRepository::class)->rowsByDocNos(array_values(
            $productions
                ->where('spk_type', 'Pesanan')
                ->pluck('request_order_no')
                ->map(fn (mixed $docNo): string => trim((string) $docNo))
                ->filter(fn (string $docNo): bool => $docNo !== '')
                ->all(),
        ));
        $lastProcessDates = SpkDashboardAnalytics::lastProcessDatesFor($productions);
        $processMapper = app(SpkProcessMapper::class);
        $doneKinds = SpkDashboardAnalytics::completedProductionKinds(
            $productions->pluck('row_id')->map(fn (mixed $id): int => (int) $id)->all(),
        );
        $statusLabels = SpkDashboardAnalytics::BACKLOG_STATUS_LABELS;
        $fakturDetailsByRowId = app(FakturBarangJadiReceiptService::class)->latestFakturDetailsBySpkRowIds(
            $productions->pluck('row_id')->map(fn (mixed $id): int => (int) $id)->all(),
        );

        $page->through(function (Production $production) use ($skuFromPluByPlu, $requestOrdersByDocNo, $lastProcessDates, $processMapper, $doneKinds, $statusLabels, $fakturDetailsByRowId): array {
            $item = SpkProductionListItemPresentation::forProduction($production);

            $plu = filled($production->plu) ? trim((string) $production->plu) : null;
            $skuFromPlu = $plu !== null && $plu !== ''
                ? ($skuFromPluByPlu[$plu] ?? [
                    'typeSkuLabel' => null,
                    'itemDescription' => null,
                    'skuAssigned' => false,
                    'skuId' => 0,
                ])
                : null;

            $rowId = (int) $production->row_id;
            $completedKind = $doneKinds[$rowId] ?? null;
            $lastProcessBadge = $processMapper->resolveLastProcessBadge(
                $rowId,
                $production->last_process,
            );

            return [
                'rowId' => $rowId,
                'spkNo' => (string) ($production->spk_no ?? ''),
                'tipeProduksi' => filled($production->spk_type)
                    ? trim((string) $production->spk_type)
                    : '-',
                ...$this->listOrderFields(
                    $production,
                    $requestOrdersByDocNo[trim((string) $production->request_order_no)] ?? null,
                ),
                'plu' => $plu,
                'skuFromPlu' => $skuFromPlu !== null
                    ? [
                        'typeSkuLabel' => $skuFromPlu['typeSkuLabel'],
                        'itemDescription' => $skuFromPlu['itemDescription'],
                        'skuAssigned' => $skuFromPlu['skuAssigned'],
                    ]
                    : null,
                'keterangan' => $this->resolvePluSkuKeterangan(
                    $item['skuAssigned'],
                    filled($production->sku_id) ? (int) $production->sku_id : null,
                    $skuFromPlu,
                ),
                'spkImageUrl' => $item['spkImageUrl'],
                'typeSkuLabel' => $item['typeSkuLabel'],
                'itemDescription' => $item['itemDescription'],
                'skuAssigned' => $item['skuAssigned'],
                'goldWeight' => filled($production->gold_weight)
                    ? number_format((float) $production->gold_weight, 2, '.', '')
                    : null,
                'lastWeight' => filled($production->last_weight)
                    ? number_format((float) $production->last_weight, 2, '.', '')
                    : null,
                'prosesTerakhir' => trim((string) ($production->last_process ?? '')),
                'prosesTerakhirDate' => $lastProcessDates[$rowId] ?? '',
                'prosesTerakhirDisplay' => $lastProcessBadge['label'] ?? null,
                'prosesTerakhirCompleted' => $lastProcessBadge['completed'] ?? null,
                'status' => $completedKind !== null
                    ? ($statusLabels[$completedKind] ?? 'DONE')
                    : '—',
                'deliveryDate' => $this->deliveryDateForListRow(
                    $production,
                    $fakturDetailsByRowId[$rowId] ?? null,
                ),
            ];
        });

        $doneStatusFilterOptions = [
            SpkDashboardAnalytics::BACKLOG_STATUS_LABELS[SpkDashboardAnalytics::KEY_DONE_RANGKA],
            SpkDashboardAnalytics::BACKLOG_STATUS_LABELS[SpkDashboardAnalytics::KEY_DONE_BARANG_JADI],
        ];

        return Inertia::render('inventory/barang-jadi/index', [
            'items' => $page,
            'pluSummary' => $pluSummary,
            'skuSummary' => $skuSummary,
            'skuCompareAvailable' => $lookup->canCompareSkuByPlu(),
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
                'plu_status' => $pluStatus,
                'sku_status' => $skuStatus,
                'status' => collect($statusKinds)
                    ->map(fn (string $kind): string => SpkDashboardAnalytics::BACKLOG_STATUS_LABELS[$kind] ?? $kind)
                    ->values()
                    ->all(),
            ],
            'filterOptions' => [
                'status' => collect($doneStatusFilterOptions)
                    ->map(fn (string $label): array => ['value' => $label, 'label' => $label])
                    ->all(),
                'per_page' => collect([10, 25, 50, 100])
                    ->map(fn (int $size): array => ['value' => (string) $size, 'label' => (string) $size])
                    ->all(),
            ],
        ]);
    }

    /**
     * @return array{belumDisetPlu: int, sudahDisetPlu: int}
     */
    private function pluSummaryCounts(SpkDashboardAnalytics $analytics): array
    {
        $base = $this->baseCompletedProductionQuery($analytics, []);

        return [
            'belumDisetPlu' => (clone $base)->tap(fn (Builder $query) => $this->applyBelumDeliveryAndPluScope($query))->count(),
            'sudahDisetPlu' => (clone $base)->tap(fn (Builder $query) => $this->applySudahDeliveryOrPluSetScope($query))->count(),
        ];
    }

    /**
     * @return array{skuTidakSama: int, skuSesuai: int}
     */
    private function skuSummaryCounts(SpkDashboardAnalytics $analytics, InventoryPluSkuLookup $lookup): array
    {
        if (! $lookup->canCompareSkuByPlu()) {
            return [
                'skuTidakSama' => 0,
                'skuSesuai' => 0,
            ];
        }

        $base = $this->baseCompletedProductionQuery($analytics, []);

        return [
            'skuTidakSama' => (clone $base)->tap(fn (Builder $query) => $this->applySkuBedaScope($query, $lookup))->count(),
            'skuSesuai' => (clone $base)->tap(fn (Builder $query) => $this->applySkuSesuaiScope($query, $lookup))->count(),
        ];
    }

    /**
     * @param  list<string>  $statusKinds
     * @return Builder<Production>
     */
    private function baseCompletedProductionQuery(SpkDashboardAnalytics $analytics, array $statusKinds): Builder
    {
        return Production::query()
            ->notDeleted()
            ->where('spk_type', '!=', SpkService::REPARATION_TYPE)
            ->tap(function (Builder $query) use ($analytics, $statusKinds): void {
                if (count($statusKinds) === 1) {
                    $analytics->constrainToCompletedProductionKind($query, $statusKinds[0]);
                } else {
                    $analytics->constrainToCompletedProduction($query);
                }
            });
    }

    /**
     * @param  Builder<Production>  $query
     */
    private function applyPluSetScope(Builder $query): void
    {
        $query->whereNotNull('plu')
            ->where('plu', '!=', '')
            ->whereRaw("TRIM(plu) <> ''");
    }

    /**
     * @param  Builder<Production>  $query
     */
    private function applyPluUnsetScope(Builder $query): void
    {
        $query->where(function (Builder $query): void {
            $query->whereNull('plu')
                ->orWhere('plu', '')
                ->orWhereRaw("TRIM(plu) = ''");
        });
    }

    /**
     * @param  Builder<Production>  $query
     */
    private function applySudahDeliveryOrPluSetScope(Builder $query): void
    {
        $query->where(function (Builder $query): void {
            $query->where(function (Builder $pluQuery): void {
                $this->applyPluSetScope($pluQuery);
            })->orWhere(function (Builder $deliveryQuery): void {
                $this->applyHasDeliveryScope($deliveryQuery);
            });
        });
    }

    /**
     * @param  Builder<Production>  $query
     */
    private function applyBelumDeliveryAndPluScope(Builder $query): void
    {
        $query->whereNot(function (Builder $sub): void {
            $this->applySudahDeliveryOrPluSetScope($sub);
        });
    }

    /**
     * @param  Builder<Production>  $query
     */
    private function applyHasDeliveryScope(Builder $query): void
    {
        $receipts = app(FakturBarangJadiReceiptService::class);
        $hasSendToMerchDate = Schema::connection('third')->hasColumn('spk', 'send_to_merch_date');
        $hasFakturTable = $receipts->tableAvailable();

        if (! $hasSendToMerchDate && ! $hasFakturTable) {
            $query->whereRaw('0 = 1');

            return;
        }

        $query->where(function (Builder $query) use ($hasSendToMerchDate, $hasFakturTable): void {
            $hasClause = false;

            if ($hasSendToMerchDate) {
                $query->whereNotNull('send_to_merch_date');
                $hasClause = true;
            }

            if ($hasFakturTable) {
                $exists = function ($sub): void {
                    $sub->selectRaw('1')
                        ->from('serah_terima_faktur_barang_jadi as fbj_delivery_scope')
                        ->whereNull('fbj_delivery_scope.deleted_at')
                        ->whereRaw('JSON_CONTAINS(fbj_delivery_scope.spk_row_ids, CAST(spk.row_id AS JSON), \'$\')');
                };

                if ($hasClause) {
                    $query->orWhereExists($exists);
                } else {
                    $query->whereExists($exists);
                }
            }
        });
    }

    /**
     * @param  Builder<Production>  $query
     */
    private function applySkuComparableBaseScope(Builder $query): void
    {
        $this->applyPluSetScope($query);

        $query->whereNotNull('sku_id')
            ->where('sku_id', '>', 0);
    }

    /**
     * @param  Builder<Production>  $query
     */
    private function applySkuBedaScope(Builder $query, InventoryPluSkuLookup $lookup): void
    {
        if (! $lookup->canCompareSkuByPlu()) {
            $query->whereRaw('0 = 1');

            return;
        }

        $this->applySkuComparableBaseScope($query);

        $this->applyInventorySkuCompareExists($query, $lookup, false);
    }

    /**
     * @param  Builder<Production>  $query
     */
    private function applySkuSesuaiScope(Builder $query, InventoryPluSkuLookup $lookup): void
    {
        if (! $lookup->canCompareSkuByPlu()) {
            $query->whereRaw('0 = 1');

            return;
        }

        $this->applySkuComparableBaseScope($query);

        $this->applyInventorySkuCompareExists($query, $lookup, true);
    }

    /**
     * @param  Builder<Production>  $query
     */
    private function applyInventorySkuCompareExists(
        Builder $query,
        InventoryPluSkuLookup $lookup,
        bool $mustMatch,
    ): void {
        $qualifiedTable = $lookup->qualifiedTableName();
        $pluColumn = $lookup->pluColumnName();

        if ($qualifiedTable === null || $pluColumn === null) {
            $query->whereRaw('0 = 1');

            return;
        }

        [$database, $table] = array_pad(explode('.', $qualifiedTable, 2), 2, null);

        if ($database === null || $table === null) {
            $query->whereRaw('0 = 1');

            return;
        }

        $query->whereExists(function ($sub) use ($database, $table, $pluColumn, $lookup, $mustMatch): void {
            $sub->selectRaw('1')
                ->from(DB::raw("`{$database}`.`{$table}` as inv"))
                ->whereRaw("TRIM(inv.`{$pluColumn}`) = TRIM(spk.plu)")
                ->whereNotNull('inv.sku_id')
                ->where('inv.sku_id', '>', 0);

            if ($lookup->hasColumn('is_deleted')) {
                $sub->where('inv.is_deleted', 0);
            }

            if ($mustMatch) {
                $sub->whereColumn('inv.sku_id', 'spk.sku_id');
            } else {
                $sub->whereColumn('inv.sku_id', '!=', 'spk.sku_id');
            }
        });
    }

    /**
     * @param  array{typeSkuLabel: string|null, itemDescription: string|null, skuAssigned: bool, skuId?: int}|null  $skuFromPlu
     */
    /**
     * @return array{
     *     customer: string,
     *     orderReference: string|null,
     *     requestStockNo: string|null,
     *     requestStockDrkNo: string|null,
     *     orderType: string|null
     * }
     */
    private function listOrderFields(Production $production, mixed $requestOrder = null): array
    {
        $customer = $this->customerListLabel($production);
        $orderReference = null;
        $orderType = null;

        if (SpkOrderReference::label($production) !== null) {
            $requestOrders = app(RequestOrderRepository::class);
            $customerName = filled($production->customer_name)
                ? (string) $production->customer_name
                : (filled($requestOrder?->customer_name) ? (string) $requestOrder->customer_name : '-');

            $orderReference = $requestOrders->pesananDisplayLabel(
                trim((string) $production->request_order_no),
                $customerName,
            );
            $orderType = $requestOrders->typeOrderLabel($requestOrder?->type_order ?? null);
        }

        return [
            'customer' => $customer,
            'orderReference' => $orderReference,
            'requestStockNo' => filled($production->request_stock_no)
                ? (string) $production->request_stock_no
                : null,
            'requestStockDrkNo' => filled($production->request_stock_drk_no)
                ? (string) $production->request_stock_drk_no
                : null,
            'orderType' => $orderType,
        ];
    }

    /**
     * @param  array{receiptId: int, docNo: string, tanggal: string, dari: string|null, untuk: string|null, workflowStatus: string, workflowStatusLabel: string}|null  $fakturDetail
     */
    private function deliveryDateForListRow(Production $production, ?array $fakturDetail): ?string
    {
        if (filled($production->send_to_merch_date)) {
            return $production->send_to_merch_date->format('d-M-Y');
        }

        $fakturDate = trim((string) ($fakturDetail['tanggal'] ?? ''));

        if ($fakturDate !== '' && $fakturDate !== '-') {
            return $fakturDate;
        }

        return null;
    }

    private function customerListLabel(Production $production): string
    {
        $customerName = filled($production->customer_name) ? (string) $production->customer_name : '';

        if ($production->spk_type !== 'Pesanan') {
            return $customerName;
        }

        $orderNo = filled($production->request_order_no) ? (string) $production->request_order_no : '';

        if ($orderNo === '') {
            return $customerName !== '' ? $customerName : '-';
        }

        return app(RequestOrderRepository::class)->displayLabelByDocNo(
            $orderNo,
            $customerName !== '' ? $customerName : null,
        );
    }

    private function resolvePluSkuKeterangan(
        bool $spkSkuAssigned,
        ?int $spkSkuId,
        ?array $skuFromPlu,
    ): ?string {
        if (! $spkSkuAssigned || $spkSkuId === null || $spkSkuId <= 0 || $skuFromPlu === null) {
            return null;
        }

        $inventorySkuId = (int) ($skuFromPlu['skuId'] ?? 0);

        if ($inventorySkuId <= 0) {
            return null;
        }

        return $spkSkuId !== $inventorySkuId ? 'SKU Tidak Sama' : null;
    }

    private function resolvePluStatus(mixed $input): ?string
    {
        if (! is_string($input)) {
            return null;
        }

        $value = trim($input);

        return in_array($value, ['belum', 'sudah'], true) ? $value : null;
    }

    private function resolveSkuStatus(mixed $input): ?string
    {
        if (! is_string($input)) {
            return null;
        }

        $value = trim($input);

        return in_array($value, ['beda', 'sesuai'], true) ? $value : null;
    }

    /**
     * @return list<string>
     */
    private function resolveStatusKinds(mixed $input): array
    {
        $values = match (true) {
            is_array($input) => $input,
            is_string($input) && trim($input) !== '' => [$input],
            default => [],
        };

        $kinds = [];

        foreach ($values as $value) {
            if (! is_string($value) && ! is_numeric($value)) {
                continue;
            }

            $kind = $this->resolveStatusKind((string) $value);

            if ($kind !== null) {
                $kinds[$kind] = true;
            }
        }

        return array_keys($kinds);
    }

    private function resolveStatusKind(string $status): ?string
    {
        $status = trim($status);

        if ($status === '') {
            return null;
        }

        if (array_key_exists($status, SpkDashboardAnalytics::BACKLOG_STATUS_LABELS)) {
            if (SpkDashboardAnalytics::isDoneStatusKey($status)) {
                return $status;
            }
        }

        $labels = SpkDashboardAnalytics::BACKLOG_STATUS_LABELS;

        foreach ([SpkDashboardAnalytics::KEY_DONE_RANGKA, SpkDashboardAnalytics::KEY_DONE_BARANG_JADI] as $key) {
            if (strcasecmp($status, $labels[$key]) === 0) {
                return $key;
            }
        }

        return null;
    }
}
