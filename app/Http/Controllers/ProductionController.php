<?php

namespace App\Http\Controllers;

use App\Exceptions\StoreStockSpkSyncException;
use App\Http\Requests\BulkUpdateSpkStatusRequest;
use App\Http\Requests\PrintSpkReceiptRequest;
use App\Http\Requests\SpkApprovalDecisionRequest;
use App\Http\Requests\StoreProductionRequest;
use App\Http\Requests\UpdateProductionRequest;
use App\Models\Employee;
use App\Models\MsPosition;
use App\Models\MsShape;
use App\Models\PolishFinishedGood;
use App\Models\Production;
use App\Models\SerahTerimaSpk;
use App\Models\SkuMaster;
use App\Models\SkuPrefixCategory;
use App\Models\SpkStone;
use App\Policies\ProductionPolicy;
use App\Support\CoranSpkEligibility;
use App\Support\DiamondMountingSpkEligibility;
use App\Support\FinishingSpkEligibility;
use App\Support\GoldColorOptions;
use App\Support\JewelCadSpkEligibility;
use App\Support\PolishFinishedGoodApprovalService;
use App\Support\PolishFinishedGoodSpkEligibility;
use App\Support\PolishFrameSpkEligibility;
use App\Support\ProductionOrderTypeLabel;
use App\Support\RequestOrderRepository;
use App\Support\ResinSpkEligibility;
use App\Support\SerahTerimaSpkDocNumberGenerator;
use App\Support\SkuMasterDescriptionExtractor;
use App\Support\SkuMasterDiamondMapper;
use App\Support\SpkApprovalRoles;
use App\Support\SpkApprovalService;
use App\Support\SpkCraftsmanReport;
use App\Support\SpkDashboardAnalytics;
use App\Support\SpkGoldReport;
use App\Support\SpkItemImageUrl;
use App\Support\SpkOrderPriorityResolver;
use App\Support\SpkOrderReference;
use App\Support\SpkProcessMapper;
use App\Support\SpkProductionControlReport;
use App\Support\SpkQtyUnit;
use App\Support\SpkService;
use App\Support\SpkShrinkSummary;
use App\Support\SpkStatusMapper;
use App\Support\SpkStatusOrder;
use App\Support\SpkStoneReport;
use App\Support\SpkStoneStockChecker;
use App\Support\StoreOrderRequestRepository;
use App\Support\StoreStockRequestRepository;
use Closure;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use RuntimeException;
use stdClass;

class ProductionController extends Controller
{
    /**
     * @var array<string, string>
     */
    private const INDEX_SORT_COLUMNS = [
        'id' => 'row_id',
        'date' => 'created_date',
        'spk' => 'spk_no',
        'order_date' => 'order_date',
        'estimated' => 'estimated_delivery_time',
    ];

    /**
     * @var array<string, string>
     */
    private const TARGET_PERIOD_LABELS = [
        'overdue' => 'Lewat target',
        'today' => 'Hari ini',
        'next_7_days' => '7 hari ke depan',
        'this_week' => 'Minggu ini',
        'next_week' => 'Minggu depan',
        'this_month' => 'Bulan ini',
        'next_month' => 'Bulan depan',
        'custom' => 'Rentang tanggal',
    ];

    public function __construct(
        private SpkStatusOrder $statusOrder,
        private SkuMasterDiamondMapper $diamondMapper,
        private SkuMasterDescriptionExtractor $descriptionExtractor,
        private SpkStoneStockChecker $stoneStockChecker,
    ) {}

    /**
     * Display a listing of SPK productions (excluding Reparasi).
     */
    public function index(Request $request): Response
    {
        return $this->renderSpkIndex($request, reparasiOnly: false);
    }

    /**
     * Display a listing of Reparasi SPK productions only.
     */
    public function reparasiIndex(Request $request): Response
    {
        return $this->renderSpkIndex($request, reparasiOnly: true);
    }

    /**
     * Display a listing of SPK productions.
     */
    private function renderSpkIndex(Request $request, bool $reparasiOnly): Response
    {
        $allowedTypes = $reparasiOnly
            ? [SpkService::REPARATION_TYPE]
            : SpkService::standardIndexTypes();
        $search = $request->string('search')->trim()->toString();
        $typeFilters = $this->resolveIndexMultiFilter($request->input('type'), $allowedTypes);

        if ($reparasiOnly) {
            $typeFilters = $typeFilters === [] ? [SpkService::REPARATION_TYPE] : $typeFilters;
        }
        $statusLabels = array_values(SpkDashboardAnalytics::BACKLOG_STATUS_LABELS);
        array_splice($statusLabels, 4, 0, ['Done']);
        $statusFilters = $this->resolveIndexMultiFilter($request->input('status'), $statusLabels);
        $sort = $request->string('sort')->toString();
        $sort = array_key_exists($sort, self::INDEX_SORT_COLUMNS) ? $sort : 'id';
        $direction = $request->string('direction')->toString();
        $direction = in_array($direction, ['asc', 'desc'], true) ? $direction : 'desc';
        $dateFrom = $this->resolveIndexDate($request->string('date_from')->toString());
        $dateTo = $this->resolveIndexDate($request->string('date_to')->toString());
        $targetFrom = $this->resolveIndexDate($request->string('target_from')->toString());
        $targetTo = $this->resolveIndexDate($request->string('target_to')->toString());
        $targetPeriod = $request->string('target_period')->trim()->toString();

        if (! array_key_exists($targetPeriod, self::TARGET_PERIOD_LABELS)) {
            $targetPeriod = $targetFrom !== null || $targetTo !== null ? 'custom' : '';
        }

        if ($targetPeriod === 'custom' && $targetFrom === null && $targetTo === null) {
            $targetPeriod = '';
        }

        if ($targetPeriod !== 'custom') {
            [$targetFrom, $targetTo] = $this->targetPeriodRange($targetPeriod);
        }

        $perPage = $request->integer('per_page', 50);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 50;
        $typeCounts = $this->activeTypeCounts($reparasiOnly);
        $statusCounts = $this->activeStatusCounts($reparasiOnly);

        if ($dateFrom !== null && $dateTo !== null && $dateTo < $dateFrom) {
            $dateTo = $dateFrom;
        }

        if ($targetFrom !== null && $targetTo !== null && $targetTo < $targetFrom) {
            $targetTo = $targetFrom;
        }

        $productions = Production::query()
            ->with(['sku', 'categoryPrefix'])
            ->notDeleted()
            ->tap(fn (Builder $query) => $this->applyIndexTypeScope($query, $reparasiOnly))
            ->when($dateFrom !== null, function ($query) use ($dateFrom): void {
                $query->whereDate('created_date', '>=', $dateFrom);
            })
            ->when($dateTo !== null, function ($query) use ($dateTo): void {
                $query->whereDate('created_date', '<=', $dateTo);
            })
            ->when($targetFrom !== null, function ($query) use ($targetFrom): void {
                $query->whereDate('estimated_delivery_time', '>=', $targetFrom);
            })
            ->when($targetTo !== null, function ($query) use ($targetTo): void {
                $query->whereDate('estimated_delivery_time', '<=', $targetTo);
            })
            ->when($typeFilters !== [], function ($query) use ($typeFilters): void {
                $query->whereIn('spk_type', $typeFilters);
            })
            ->when($statusFilters !== [], function ($query) use ($statusFilters): void {
                $statusKeys = collect($statusFilters)
                    ->map(fn (string $status): ?string => $this->normalizedBacklogStatusKey($status))
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();

                if ($statusKeys === []) {
                    return;
                }

                $doneKinds = SpkDashboardAnalytics::completedProductionKinds(
                    Production::query()->notDeleted()->pluck('row_id')->all(),
                );

                $query->where(function ($statusQuery) use ($statusKeys, $doneKinds): void {
                    foreach ($statusKeys as $statusKey) {
                        $statusQuery->orWhere(function ($statusScope) use ($statusKey, $doneKinds): void {
                            $this->applyBacklogStatusFilter($statusScope, $statusKey, $doneKinds);
                        });
                    }
                });
            })
            ->when($search !== '', function ($query) use ($search): void {
                $matchingSkuIds = $this->matchingSkuIds($search);

                $query->where(function ($query) use ($search, $matchingSkuIds): void {
                    $query->where('spk_no', 'like', "%{$search}%")
                        ->orWhere('spk_type', 'like', "%{$search}%")
                        ->orWhere('request_order_no', 'like', "%{$search}%")
                        ->orWhere('request_stock_no', 'like', "%{$search}%")
                        ->orWhere('customer_name', 'like', "%{$search}%")
                        ->orWhere('item_name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('status', 'like', "%{$search}%")
                        ->orWhere('last_process', 'like', "%{$search}%")
                        ->when($matchingSkuIds !== [], function ($query) use ($matchingSkuIds): void {
                            $query->orWhereIn('sku_id', $matchingSkuIds);
                        });
                });
            })
            ->tap(function ($query) use ($sort, $direction): void {
                if ($sort !== 'id') {
                    $query->orderBy(self::INDEX_SORT_COLUMNS[$sort], $direction);
                }

                $query->orderBy('row_id', $direction);
            })
            ->paginate($perPage)
            ->withQueryString();

        $productions = $productions->through(
            $this->indexRowMapper($productions->getCollection()),
        );

        $user = $request->user();

        return Inertia::render($reparasiOnly ? 'reparasi/index' : 'spk/index', [
            'indexContext' => $reparasiOnly ? 'reparasi' : 'spk',
            'productions' => $productions,
            'types' => $allowedTypes,
            'typeCounts' => $typeCounts,
            'statusCounts' => $statusCounts,
            'statusLabels' => SpkDashboardAnalytics::BACKLOG_STATUS_LABELS,
            'statuses' => $statusLabels,
            'filters' => [
                'search' => $search,
                'type' => $typeFilters,
                'status' => $statusFilters,
                'sort' => $sort,
                'direction' => $direction,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'target_period' => $targetPeriod,
                'target_from' => $targetPeriod === 'custom' ? $targetFrom : null,
                'target_to' => $targetPeriod === 'custom' ? $targetTo : null,
                'per_page' => $perPage,
            ],
            'filterOptions' => [
                'target_period' => collect(self::TARGET_PERIOD_LABELS)
                    ->map(fn (string $label, string $value): array => [
                        'value' => $value,
                        'label' => $label,
                    ])
                    ->values()
                    ->all(),
                'per_page' => [
                    ['value' => '10', 'label' => '10'],
                    ['value' => '25', 'label' => '25'],
                    ['value' => '50', 'label' => '50'],
                    ['value' => '100', 'label' => '100'],
                ],
                'sort' => [
                    ['value' => 'id', 'label' => 'ID'],
                    ['value' => 'date', 'label' => 'Tanggal SPK'],
                    ['value' => 'spk', 'label' => 'No SPK'],
                    ['value' => 'order_date', 'label' => 'Tanggal Permintaan'],
                    ['value' => 'estimated', 'label' => 'Target Selesai'],
                ],
                'direction' => [
                    ['value' => 'asc', 'label' => 'A–Z'],
                    ['value' => 'desc', 'label' => 'Z–A'],
                ],
            ],
            'receiptEmployeeOptions' => Inertia::once(fn (): array => $this->receiptEmployeeOptions()),
            'storeStockRequestCount' => Inertia::defer(fn (): ?int => app(StoreStockRequestRepository::class)->pendingSpkCount()),
            'storeOrderRequestCount' => Inertia::defer(fn (): int => app(StoreOrderRequestRepository::class)->pendingSpkCount()),
            'bulkActions' => [
                'canSubmit' => SpkApprovalRoles::canSubmit($user),
                'canApprove' => SpkApprovalRoles::canApprove($user),
                'canManagerApprove' => SpkApprovalRoles::canManagerApprove($user),
                'canDelete' => SpkApprovalRoles::canEditDraft($user),
            ],
        ]);
    }

    /**
     * Ubah status beberapa SPK sekaligus dari halaman index.
     */
    public function bulkUpdateStatus(
        BulkUpdateSpkStatusRequest $request,
        SpkApprovalService $approvalService,
        SpkService $spkService,
        ProductionPolicy $policy,
    ): RedirectResponse {
        $validated = $request->validated();
        /** @var list<int> $ids */
        $ids = array_map(intval(...), $validated['ids']);
        /** @var 'submit'|'approve'|'manager_approve'|'delete' $action */
        $action = $validated['action'];
        $actor = $this->actorName($request);
        $user = $request->user();

        $productions = Production::query()
            ->notDeleted()
            ->whereIn('row_id', $ids)
            ->get()
            ->keyBy('row_id');

        $updated = 0;
        $skipped = 0;

        foreach ($ids as $id) {
            $production = $productions->get($id);

            if ($production === null) {
                $skipped++;

                continue;
            }

            $allowed = match ($action) {
                'submit' => $policy->submit($user, $production),
                'approve' => $policy->approve($user, $production),
                'manager_approve' => $policy->managerApprove($user, $production),
                'delete' => $policy->delete($user, $production),
            };

            if (! $allowed) {
                $skipped++;

                continue;
            }

            try {
                match ($action) {
                    'submit' => $approvalService->submit($production, $actor),
                    'approve' => $approvalService->approve($production, $actor),
                    'manager_approve' => $approvalService->managerApprove($production, $actor),
                    'delete' => $spkService->softDelete($production, $actor),
                };
                $updated++;
            } catch (InvalidArgumentException) {
                $skipped++;
            }
        }

        $actionLabel = match ($action) {
            'submit' => 'Kirim ke Manager',
            'approve' => 'Kirim ke Produksi',
            'manager_approve' => 'Approve',
            'delete' => 'Hapus',
        };

        $verb = $action === 'delete' ? 'dihapus' : 'diperbarui';

        $message = $updated > 0
            ? "{$updated} SPK berhasil {$verb} ({$actionLabel})."
            : "Tidak ada SPK yang dapat {$verb} ({$actionLabel}).";

        if ($skipped > 0) {
            $message .= " {$skipped} dilewati.";
        }

        Inertia::flash('toast', [
            'type' => $updated > 0 ? 'success' : 'warning',
            'message' => $message,
        ]);

        return back();
    }

    /**
     * @return list<string>
     */
    private function receiptEmployeeOptions(): array
    {
        return Employee::query()
            ->active()
            ->whereNotNull('nama_lengkap')
            ->where('nama_lengkap', '!=', '')
            ->orderBy('nama_lengkap')
            ->pluck('nama_lengkap')
            ->map(fn (mixed $name): string => trim((string) $name))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function targetPeriodRange(string $period): array
    {
        $today = Carbon::today();

        [$from, $to] = match ($period) {
            'overdue' => [null, $today->copy()->subDay()],
            'today' => [$today, $today],
            'next_7_days' => [$today, $today->copy()->addDays(7)],
            'this_week' => [
                $today->copy()->startOfWeek(Carbon::MONDAY),
                $today->copy()->endOfWeek(Carbon::SUNDAY),
            ],
            'next_week' => [
                $today->copy()->addWeek()->startOfWeek(Carbon::MONDAY),
                $today->copy()->addWeek()->endOfWeek(Carbon::SUNDAY),
            ],
            'this_month' => [
                $today->copy()->startOfMonth(),
                $today->copy()->endOfMonth(),
            ],
            'next_month' => [
                $today->copy()->startOfMonth()->addMonthNoOverflow(),
                $today->copy()->startOfMonth()->addMonthNoOverflow()->endOfMonth(),
            ],
            default => [null, null],
        };

        return [$from?->toDateString(), $to?->toDateString()];
    }

    /**
     * ID SKU master (koneksi second) yang kode atau namanya cocok dengan kata kunci pencarian SPK.
     *
     * @return list<int>
     */
    private function matchingSkuIds(string $search): array
    {
        return SkuMaster::query()
            ->where(function ($query) use ($search): void {
                $query->where('sku_code', 'like', "%{$search}%")
                    ->orWhere('item_original', 'like', "%{$search}%");
            })
            ->limit(1000)
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->values()
            ->all();
    }

    private function resolveIndexDate(string $date): ?string
    {
        $trimmed = trim($date);

        if ($trimmed === '') {
            return null;
        }

        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $trimmed);

        if ($parsed === false || $parsed->format('Y-m-d') !== $trimmed) {
            return null;
        }

        return $trimmed;
    }

    /**
     * @param  list<string>  $allowed
     * @return list<string>
     */
    private function resolveIndexMultiFilter(mixed $value, array $allowed): array
    {
        return collect(is_array($value) ? $value : (filled($value) ? [$value] : []))
            ->filter(fn (mixed $item): bool => is_scalar($item))
            ->map(fn (mixed $item): string => trim((string) $item))
            ->filter(fn (string $item): bool => in_array($item, $allowed, true))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Daftar SPK per status untuk modal ringkasan status di halaman index.
     */
    public function statusList(Request $request, string $statusKey): JsonResponse
    {
        $statusKey = $this->normalizedBacklogStatusKey($statusKey);

        if ($statusKey === null) {
            abort(404);
        }

        $reparasiOnly = $request->string('scope')->toString() === 'reparasi';

        $query = Production::query()
            ->notDeleted()
            ->tap(fn (Builder $builder) => $this->applyIndexTypeScope($builder, $reparasiOnly));

        $this->applyBacklogStatusFilter($query, $statusKey);

        return $this->spkListModalResponse(
            $request,
            $query,
            $this->backlogStatusFilterLabel($statusKey),
        );
    }

    /**
     * Daftar SPK per antrean proses modul (Belum / Sedang / Selesai) untuk modal ringkasan status.
     */
    public function processQueueList(Request $request, string $module, string $queue): JsonResponse
    {
        $eligibility = match ($module) {
            'jewelcad' => app(JewelCadSpkEligibility::class),
            'resin' => app(ResinSpkEligibility::class),
            'coran' => app(CoranSpkEligibility::class),
            'finishing' => app(FinishingSpkEligibility::class),
            'poles-rangka' => app(PolishFrameSpkEligibility::class),
            'pasang-batu' => app(DiamondMountingSpkEligibility::class),
            'poles-chrome' => app(PolishFinishedGoodSpkEligibility::class),
            default => abort(404),
        };

        $query = Production::query()->notDeleted();

        match ($queue) {
            'pending' => $eligibility->applyEligibleScope($query),
            'inProgress' => $eligibility->applyInProgressScope($query),
            'completed' => $eligibility->applyCompletedScope($query),
            default => abort(404),
        };

        return $this->spkListModalResponse(
            $request,
            $query,
            $queue,
            fn (array $spkIds): array => $this->processQueueDocumentRefs($eligibility, $queue, $spkIds),
        );
    }

    /**
     * Daftar SPK (format tabel index) untuk modal pilih SPK di form dokumen proses, termasuk berat terakhir.
     */
    public function selectList(Request $request): JsonResponse
    {
        $excludeInput = $request->input('exclude', []);
        $exclude = collect(is_array($excludeInput) ? $excludeInput : explode(',', (string) $excludeInput))
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->values()
            ->all();

        $query = Production::query()
            ->notDeleted()
            ->whereNotNull('spk_no')
            ->when($exclude !== [], fn (Builder $query) => $query->whereNotIn('row_id', $exclude));

        return $this->spkListModalResponse(
            $request,
            $query,
            'select',
            extraFields: fn (Production $production): array => [
                'lastWeight' => filled($production->last_weight)
                    ? number_format((float) $production->last_weight, 2, '.', '')
                    : null,
                'orderTypeLabel' => app(ProductionOrderTypeLabel::class)->forProduction($production),
                'satuan' => SpkQtyUnit::label($production->qty, $production->satuan),
            ],
        );
    }

    /**
     * Referensi dokumen modul (ID & nomor dokumen) per SPK untuk antrean yang sudah punya dokumen.
     *
     * @param  list<int>  $spkIds
     * @return array<int, array<string, int|string|null>>
     */
    private function processQueueDocumentRefs(
        JewelCadSpkEligibility|ResinSpkEligibility|CoranSpkEligibility|FinishingSpkEligibility|PolishFrameSpkEligibility|DiamondMountingSpkEligibility|PolishFinishedGoodSpkEligibility $eligibility,
        string $queue,
        array $spkIds,
    ): array {
        if ($queue === 'pending') {
            return [];
        }

        return match (true) {
            $eligibility instanceof JewelCadSpkEligibility => $eligibility->requestRefsBySpkIds(
                $spkIds,
                completed: $queue === 'completed',
            ),
            $eligibility instanceof ResinSpkEligibility => $eligibility->resinRefsBySpkIds($spkIds),
            $eligibility instanceof CoranSpkEligibility => $eligibility->coranRefsBySpkIds($spkIds),
            $eligibility instanceof FinishingSpkEligibility => $eligibility->finishingRefsBySpkIds($spkIds),
            $eligibility instanceof PolishFrameSpkEligibility => $eligibility->polishFrameRefsBySpkIds($spkIds),
            $eligibility instanceof DiamondMountingSpkEligibility => $eligibility->diamondMountingRefsBySpkIds($spkIds),
            $eligibility instanceof PolishFinishedGoodSpkEligibility => $eligibility->polishFinishedGoodRefsBySpkIds($spkIds),
        };
    }

    /**
     * Respons JSON baris SPK (format tabel index) dengan pencarian & paginasi untuk modal daftar SPK.
     *
     * @param  Builder<Production>  $query
     * @param  (Closure(list<int>): array<int, array<string, int|string|null>>)|null  $documentRefs
     * @param  (Closure(Production): array<string, mixed>)|null  $extraFields
     */
    private function spkListModalResponse(
        Request $request,
        Builder $query,
        string $label,
        ?Closure $documentRefs = null,
        ?Closure $extraFields = null,
    ): JsonResponse {
        $search = $request->string('search')->trim()->toString();

        $productions = $query
            ->with(['sku', 'categoryPrefix'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('spk_no', 'like', "%{$search}%")
                        ->orWhere('request_order_no', 'like', "%{$search}%")
                        ->orWhere('request_stock_no', 'like', "%{$search}%")
                        ->orWhere('customer_name', 'like', "%{$search}%")
                        ->orWhere('item_name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('row_id')
            ->paginate(25);

        $pageProductions = $productions->getCollection();
        $toIndexRow = $this->indexRowMapper($pageProductions);
        $refs = $documentRefs !== null
            ? $documentRefs(array_values(
                $pageProductions->pluck('row_id')->map(fn (mixed $id): int => (int) $id)->all(),
            ))
            : [];

        return response()->json([
            'label' => $label,
            'data' => $pageProductions
                ->map(function (Production $production) use ($toIndexRow, $refs, $extraFields): array {
                    $ref = $refs[(int) $production->row_id] ?? null;
                    $documentId = $ref !== null
                        ? collect($ref)->except('docNo')->first()
                        : null;

                    return [
                        ...$toIndexRow($production),
                        'documentId' => is_int($documentId) ? $documentId : null,
                        'documentNo' => $ref['docNo'] ?? null,
                        ...($extraFields !== null ? $extraFields($production) : []),
                    ];
                })
                ->values()
                ->all(),
            'meta' => [
                'currentPage' => $productions->currentPage(),
                'lastPage' => $productions->lastPage(),
                'perPage' => $productions->perPage(),
                'total' => $productions->total(),
            ],
        ]);
    }

    public function showByStatus(string $statusKey): RedirectResponse
    {
        $statusKey = $this->normalizedBacklogStatusKey($statusKey);

        if ($statusKey === null) {
            abort(404);
        }

        $query = Production::query()
            ->notDeleted()
            ->whereNotNull('spk_no')
            ->orderByDesc('row_id');

        $this->applyBacklogStatusFilter($query, $statusKey);

        $spkNo = $query->value('spk_no');

        if ($spkNo === null) {
            return to_route('spk.index', [
                'status' => $this->backlogStatusFilterLabel($statusKey),
            ]);
        }

        return to_route('spk.show', [
            'production' => $spkNo,
            'status' => $statusKey,
        ]);
    }

    /**
     * @param  Builder<Production>  $query
     */
    private function applyIndexTypeScope(Builder $query, bool $reparasiOnly): void
    {
        if ($reparasiOnly) {
            $query->where('spk_type', SpkService::REPARATION_TYPE);
        } else {
            $query->where('spk_type', '!=', SpkService::REPARATION_TYPE);
        }
    }

    /**
     * @return array{all: int, byType: array<string, int>}
     */
    private function activeTypeCounts(bool $reparasiOnly = false): array
    {
        $allIds = Production::query()
            ->notDeleted()
            ->tap(fn (Builder $query) => $this->applyIndexTypeScope($query, $reparasiOnly))
            ->pluck('row_id')
            ->all();

        $doneIds = SpkDashboardAnalytics::completedProductionSpkIds($allIds);

        $query = Production::query()
            ->notDeleted()
            ->tap(fn (Builder $builder) => $this->applyIndexTypeScope($builder, $reparasiOnly));

        if ($doneIds !== []) {
            $query->whereNotIn('row_id', $doneIds);
        }

        /** @var array<string, int> $countsByType */
        $countsByType = $query
            ->selectRaw('spk_type, COUNT(*) as aggregate')
            ->groupBy('spk_type')
            ->pluck('aggregate', 'spk_type')
            ->map(fn (mixed $count): int => (int) $count)
            ->all();

        $types = $reparasiOnly ? [SpkService::REPARATION_TYPE] : SpkService::standardIndexTypes();
        $byType = [];

        foreach ($types as $type) {
            $byType[$type] = $countsByType[$type] ?? 0;
        }

        return [
            'all' => array_sum($byType),
            'byType' => $byType,
        ];
    }

    /**
     * @return array{draft: int, pendingManager: int, confirmed: int, inProgress: int, done: int}
     */
    private function activeStatusCounts(bool $reparasiOnly = false): array
    {
        $allProductions = Production::query()
            ->notDeleted()
            ->tap(fn (Builder $query) => $this->applyIndexTypeScope($query, $reparasiOnly))
            ->get(['row_id', 'status', 'is_inprocess', 'last_process']);

        $doneIds = array_flip(SpkDashboardAnalytics::completedProductionSpkIds(
            $allProductions->pluck('row_id')->all(),
        ));

        $counts = [
            'draft' => 0,
            'pendingManager' => 0,
            'confirmed' => 0,
            'inProgress' => 0,
            'done' => count($doneIds),
        ];

        foreach ($allProductions as $production) {
            if (isset($doneIds[(int) $production->row_id])) {
                continue;
            }

            $key = SpkDashboardAnalytics::backlogStatusKey($production, false);

            if (isset($counts[$key])) {
                $counts[$key]++;
            }
        }

        return $counts;
    }

    /**
     * Panduan pengisian form create/edit SPK (tampilan web).
     */
    public function createGuide(): Response
    {
        return Inertia::render('spk/create-guide', [
            'formDocumentNo' => (string) config('spk.form_document_no'),
        ]);
    }

    /**
     * Show the form for creating a new SPK (nomor digenerate saat simpan).
     */
    public function create(Request $request): Response
    {
        [$production, $stones] = $this->createFormPayload($request);

        return Inertia::render('spk/form', [
            'production' => $production,
            'stones' => $stones,
            'options' => $this->formOptions(),
            'formDocumentNo' => (string) config('spk.form_document_no'),
            'productionImageBaseUrl' => (string) config('spk.production_image_base_url'),
            'approvalFooter' => $this->approvalFooter($request),
            'approval' => $this->emptyApprovalAbilities($request),
        ]);
    }

    /**
     * Standalone print/PDF preview (GET kosong / POST payload dari form).
     */
    public function printPreview(Request $request): View
    {
        $payload = [];

        if ($request->isMethod('post')) {
            $payload = $request->input('document', $request->json('document'));
            $payload = is_array($payload) ? $payload : [];
        }

        return view('spk.print', [
            'title' => 'Form SPK — Print',
            'header' => $this->documentHeader(),
            'document' => $this->normalizePrintDocument($payload, $request),
        ]);
    }

    /**
     * Blank SPK print page used as the official form template.
     */
    public function printTemplate(): View
    {
        return $this->blankPrintView('Form SPK — Template');
    }

    /**
     * Daftar request stok dari Store yang belum dibuatkan SPK untuk modal alert di halaman index.
     */
    public function storeStockRequests(Request $request, StoreStockRequestRepository $stockRequests): JsonResponse
    {
        try {
            return response()->json($stockRequests->paginatePendingSpk(
                $request->string('search')->trim()->toString(),
                max(1, $request->integer('page', 1)),
            ));
        } catch (RuntimeException $exception) {
            report($exception);

            return response()->json([
                'message' => 'Gagal mengambil data request stok dari Store. Silakan coba lagi.',
            ], 502);
        }
    }

    /**
     * Daftar pesanan toko (request order) per tab belum / sudah dibuatkan SPK untuk modal di halaman index.
     */
    public function storeOrderRequests(Request $request, StoreOrderRequestRepository $orderRequests): JsonResponse
    {
        return response()->json($orderRequests->paginate(
            $request->string('tab')->trim()->toString(),
            $request->string('search')->trim()->toString(),
            max(1, $request->integer('page', 1)),
        ));
    }

    /**
     * Riwayat tanda terima serah terima SPK dengan pencarian & paginasi untuk modal di halaman index.
     */
    public function receiptHistory(Request $request): JsonResponse
    {
        $search = $request->string('search')->trim()->toString();

        $receipts = SerahTerimaSpk::query()
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    foreach (['doc_no', 'dari', 'untuk', 'diserahkan_oleh', 'diketahui_oleh', 'diterima_oleh', 'created_by', 'items'] as $column) {
                        $query->orWhere($column, 'like', "%{$search}%");
                    }
                });
            })
            ->orderByDesc('id')
            ->paginate(20);

        return response()->json([
            'data' => $receipts->getCollection()
                ->map(fn (SerahTerimaSpk $receipt): array => [
                    'id' => $receipt->id,
                    'docNo' => $receipt->doc_no,
                    'tanggal' => $receipt->tanggal->format('d-M-Y'),
                    'dari' => $receipt->dari,
                    'untuk' => $receipt->untuk,
                    'jumlahSpk' => $receipt->jumlah_spk,
                    'spkNos' => array_values(array_column($receipt->items, 'spkNo')),
                    'diserahkanOleh' => $receipt->diserahkan_oleh,
                    'diketahuiOleh' => $receipt->diketahui_oleh,
                    'diterimaOleh' => $receipt->diterima_oleh,
                    'createdBy' => $receipt->created_by,
                    'createdAt' => $receipt->created_at?->format('d-M-Y H:i'),
                    'printUrl' => route('spk.print.receipt', $receipt),
                ])
                ->values(),
            'meta' => [
                'currentPage' => $receipts->currentPage(),
                'lastPage' => $receipts->lastPage(),
                'perPage' => $receipts->perPage(),
                'total' => $receipts->total(),
            ],
        ]);
    }

    /**
     * Simpan serah terima SPK yang dipilih di halaman index dan generate nomor form.
     */
    public function storeReceipt(
        PrintSpkReceiptRequest $request,
        SerahTerimaSpkDocNumberGenerator $docNumberGenerator,
    ): JsonResponse {
        /** @var list<int> $ids */
        $ids = array_map(intval(...), $request->validated('ids'));

        $productions = Production::query()
            ->with(['sku', 'categoryPrefix'])
            ->notDeleted()
            ->whereIn('row_id', $ids)
            ->get()
            ->sortBy(fn (Production $production): int|false => array_search((int) $production->row_id, $ids, true))
            ->values();

        abort_if($productions->isEmpty(), 404);

        $receiptDate = Carbon::createFromFormat(
            'Y-m-d',
            $request->validated('tanggal') ?? now()->toDateString(),
        )->startOfDay();

        $items = $productions->map(fn (Production $production): array => [
            'spkRowId' => (int) $production->row_id,
            'spkNo' => $production->spk_no ?? '-',
            'type' => $production->spk_type ?? '-',
            'item' => $this->listTypeSkuLabel($production) ?? ($production->item_name ?? '-'),
            'description' => $this->listItemDescriptionText($production) ?? '',
            'customer' => $this->customerListLabel($production),
            'targetDate' => $production->estimated_delivery_time?->format('d-M-Y') ?? '-',
        ])->all();

        $receipt = DB::connection('third')->transaction(fn (): SerahTerimaSpk => SerahTerimaSpk::query()->create([
            'doc_no' => $docNumberGenerator->generate($receiptDate),
            'tanggal' => $receiptDate->toDateString(),
            'dari' => $this->nullableTrimmed($request->validated('dari')),
            'untuk' => $this->nullableTrimmed($request->validated('untuk')),
            'diserahkan_oleh' => $this->nullableTrimmed($request->validated('diserahkan_oleh')),
            'diterima_oleh' => $this->nullableTrimmed($request->validated('diterima_oleh')),
            'diketahui_oleh' => $this->nullableTrimmed($request->validated('diketahui_oleh')),
            'jumlah_spk' => count($items),
            'spk_row_ids' => array_column($items, 'spkRowId'),
            'items' => $items,
            'created_by' => $this->actorName($request),
        ]));

        return response()->json([
            'id' => $receipt->id,
            'docNo' => $receipt->doc_no,
            'printUrl' => route('spk.print.receipt', $receipt),
        ], 201);
    }

    /**
     * Halaman print tanda terima serah terima SPK yang sudah disimpan.
     */
    public function printReceipt(SerahTerimaSpk $serahTerimaSpk): View
    {
        $header = [
            ...$this->documentHeader(),
            'formTitle' => 'Tanda Terima SPK',
            'docNo' => (string) config('spk.receipt_document_no', 'WHOJ-PRD-FRM-002'),
            'revision' => (string) config('spk.receipt_revision', '00'),
            'issueDate' => (string) config('spk.receipt_issue_date', now()->format('d/m/Y')),
        ];

        return view('spk.receipt', [
            'title' => "Tanda Terima SPK {$serahTerimaSpk->doc_no} — Print",
            'header' => $header,
            'receiptNo' => $serahTerimaSpk->doc_no,
            'printedAt' => ($serahTerimaSpk->created_at ?? now())->format('d-M-Y H:i'),
            'printedBy' => $serahTerimaSpk->created_by ?? '-',
            'receiptDate' => $serahTerimaSpk->tanggal->format('d-M-Y'),
            'receiptFrom' => (string) $serahTerimaSpk->dari,
            'receiptTo' => (string) $serahTerimaSpk->untuk,
            'signatures' => [
                ['title' => 'Diserahkan oleh', 'name' => (string) $serahTerimaSpk->diserahkan_oleh],
                ['title' => 'Diketahui oleh', 'name' => (string) $serahTerimaSpk->diketahui_oleh],
                ['title' => 'Diterima oleh', 'name' => (string) $serahTerimaSpk->diterima_oleh],
            ],
            'rows' => $serahTerimaSpk->items,
        ]);
    }

    /**
     * Soft delete tanda terima serah terima SPK dari modal riwayat.
     */
    public function destroyReceipt(Request $request, SerahTerimaSpk $serahTerimaSpk): JsonResponse
    {
        DB::connection('third')->transaction(function () use ($request, $serahTerimaSpk): void {
            $serahTerimaSpk->forceFill(['deleted_by' => $this->actorName($request)])->save();
            $serahTerimaSpk->delete();
        });

        return response()->json([
            'message' => "Tanda terima {$serahTerimaSpk->doc_no} berhasil dihapus.",
        ]);
    }

    private function nullableTrimmed(mixed $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * Standalone print/PDF view for an existing SPK.
     */
    public function print(Request $request, int $rowId): View
    {
        $production = $this->findActiveProduction($rowId);
        $production->loadMissing([
            'sku',
            'categoryPrefix',
            'stones' => fn ($query) => $query
                ->notDeleted()
                ->with('shape')
                ->orderBy('line_id'),
        ]);

        return view('spk.print', [
            'title' => 'Form SPK '.$production->spk_no.' — Print',
            'header' => $this->documentHeader(),
            'document' => $this->printDocumentFromProduction($production, $request),
        ]);
    }

    /**
     * Store a newly created SPK. Nomor SPK digenerate di sini.
     */
    public function store(StoreProductionRequest $request, SpkService $spkService): RedirectResponse
    {
        try {
            $production = $spkService->createWithDetails(
                $request->validated(),
                $this->actorName($request),
                $request->file('file'),
            );
        } catch (StoreStockSpkSyncException $exception) {
            return back()->withErrors([
                'request_stock_no' => $exception->getMessage(),
            ]);
        } catch (RuntimeException $exception) {
            return back()->withErrors([
                'file' => $exception->getMessage(),
            ]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "SPK {$production->spk_no} berhasil dibuat.",
        ]);

        return to_route('spk.show', $production->spk_no);
    }

    /**
     * Display the SPK edit form.
     */
    public function form(Request $request, int $rowId): Response
    {
        $production = $this->findActiveProduction($rowId);
        $production->loadMissing('item');

        $reference = null;

        if ($production->ref_spk_id !== null) {
            $reference = Production::query()
                ->notDeleted()
                ->where('row_id', $production->ref_spk_id)
                ->first();
        }

        $frameNo = null;

        if (filled($production->frame_id)) {
            $frameNo = DB::connection('third')
                ->table('trframe')
                ->where('row_id', $production->frame_id)
                ->value('doc_no');
        }

        $stones = $production->stones()
            ->notDeleted()
            ->with(['shape', 'position'])
            ->orderBy('line_id')
            ->get()
            ->map(function (SpkStone $stone): array {
                $pcs = (int) ($stone->pcs ?? 0);
                $totalCarat = (float) ($stone->carat ?? 0);

                return [
                    'id' => (string) $stone->line_id,
                    'positionId' => $stone->position_id !== null
                        ? (string) $stone->position_id
                        : '',
                    'positionName' => $stone->position?->nama ?? '',
                    'shape' => $stone->shape?->name ?? '-',
                    'shapeId' => $stone->shape_id !== null
                        ? (string) $stone->shape_id
                        : '',
                    'pcs' => $pcs,
                    'carat' => $pcs > 0 ? round($totalCarat / $pcs, 3) : 0,
                    'totalCarat' => $totalCarat,
                    'size' => $stone->size ?? '-',
                ];
            })
            ->values();

        return Inertia::render('spk/form', [
            'production' => $this->toFormData($production, $frameNo !== null ? (string) $frameNo : null, $reference),
            'stones' => $stones,
            'options' => $this->formOptions(),
            'formDocumentNo' => (string) config('spk.form_document_no'),
            'productionImageBaseUrl' => (string) config('spk.production_image_base_url'),
            'approvalFooter' => app(SpkApprovalService::class)->footerColumns(
                $production,
                $this->actorName($request),
            ),
            'approval' => $this->approvalAbilities($request, $production),
        ]);
    }

    /**
     * Update the SPK header.
     */
    public function update(
        UpdateProductionRequest $request,
        int $rowId,
        SpkService $spkService,
        ProductionPolicy $policy,
    ): RedirectResponse {
        $production = $this->findActiveProduction($rowId);

        if (! $policy->update($request->user(), $production)) {
            abort(403, 'Anda tidak memiliki izin untuk mengedit SPK ini.');
        }

        try {
            $spkService->saveHeader(
                $production,
                $request->validated(),
                $this->actorName($request),
                $request->file('file'),
            );
        } catch (RuntimeException $exception) {
            return back()->withErrors([
                'file' => $exception->getMessage(),
            ]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "SPK {$production->spk_no} berhasil disimpan.",
        ]);

        return to_route('spk.show', $production->spk_no);
    }

    /**
     * SPV mengirim SPK Draft ke antrean Manager (SPK010).
     */
    public function submit(
        Request $request,
        int $rowId,
        SpkApprovalService $approvalService,
        ProductionPolicy $policy,
    ): RedirectResponse {
        $production = $this->findActiveProduction($rowId);

        if (! $policy->submit($request->user(), $production)) {
            abort(403, 'Hanya SPV PRD yang dapat mengirim SPK Draft ke Manager.');
        }

        try {
            $approvalService->submit($production, $this->actorName($request));
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['approval' => $exception->getMessage()]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "SPK {$production->spk_no} dikirim ke Manager Produksi.",
        ]);

        return to_route('spk.show', $production->spk_no);
    }

    /**
     * User mengirim SPK ke produksi (mengisi Disetujui Oleh, bukan Manager Produksi).
     */
    public function approve(
        SpkApprovalDecisionRequest $request,
        int $rowId,
        SpkApprovalService $approvalService,
        ProductionPolicy $policy,
    ): RedirectResponse {
        $production = $this->findActiveProduction($rowId);

        if (! $policy->approve($request->user(), $production)) {
            abort(403, 'SPK ini tidak dapat di-approve.');
        }

        try {
            $approvalService->approve(
                $production,
                $this->actorName($request),
                $request->validated('notes'),
            );
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['approval' => $exception->getMessage()]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "SPK {$production->spk_no} dikirim ke produksi.",
        ]);

        return to_route('spk.show', $production->spk_no);
    }

    /**
     * Manager Produksi meng-approve SPK — status berubah ke SPKDONE.
     */
    public function managerApprove(
        SpkApprovalDecisionRequest $request,
        int $rowId,
        SpkApprovalService $approvalService,
        ProductionPolicy $policy,
    ): RedirectResponse {
        $production = $this->findActiveProduction($rowId);

        if (! $policy->managerApprove($request->user(), $production)) {
            abort(403, 'Hanya Manager Produksi yang dapat approve SPK.');
        }

        try {
            $approvalService->managerApprove(
                $production,
                $this->actorName($request),
                $request->validated('notes'),
            );
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['approval' => $exception->getMessage()]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "SPK {$production->spk_no} approved by Manager Produksi.",
        ]);

        return to_route('spk.show', $production->spk_no);
    }

    /**
     * Manager menolak SPK — kembali ke Draft.
     */
    public function reject(
        SpkApprovalDecisionRequest $request,
        int $rowId,
        SpkApprovalService $approvalService,
        ProductionPolicy $policy,
    ): RedirectResponse {
        $production = $this->findActiveProduction($rowId);

        if (! $policy->reject($request->user(), $production)) {
            abort(403, 'Hanya Manager Produksi yang dapat reject SPK.');
        }

        try {
            $approvalService->reject(
                $production,
                $this->actorName($request),
                (string) $request->validated('notes'),
            );
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['approval' => $exception->getMessage()]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "SPK {$production->spk_no} ditolak dan dikembalikan ke Draft.",
        ]);

        return to_route('spk.form', $production->row_id);
    }

    /**
     * Soft-delete the SPK.
     */
    public function destroy(
        Request $request,
        int $rowId,
        SpkService $spkService,
        ProductionPolicy $policy,
    ): RedirectResponse {
        $production = $this->findActiveProduction($rowId);

        if (! $policy->delete($request->user(), $production)) {
            abort(403, 'SPK ini tidak dapat dihapus.');
        }

        $spkService->softDelete($production, $this->actorName($request));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "SPK {$production->spk_no} berhasil dihapus.",
        ]);

        return to_route('spk.index');
    }

    /**
     * Search frames for the form selector.
     */
    public function searchFrames(Request $request, SpkService $spkService): JsonResponse
    {
        $search = $request->string('search')->trim()->toString();

        return response()->json([
            'status' => true,
            'data' => $spkService->searchFrames($search),
        ]);
    }

    /**
     * Search request orders for the create selector popup.
     */
    public function searchRequestOrders(Request $request, RequestOrderRepository $requestOrders): JsonResponse
    {
        $search = $request->string('search')->trim()->toString();

        return response()->json([
            'status' => true,
            'data' => $requestOrders->search($search)->values()->all(),
        ]);
    }

    /**
     * Search approved SPKs for Exchange/Refund/Reparasi references.
     */
    public function searchReferenceSpks(Request $request, SpkService $spkService): JsonResponse
    {
        $search = $request->string('search')->trim()->toString();

        return response()->json([
            'status' => true,
            'data' => $spkService->searchReferenceSpks($search),
        ]);
    }

    /**
     * Global shell search suggestions for SPK.
     */
    public function searchSuggestions(Request $request): JsonResponse
    {
        $search = $request->string('search')->trim()->toString();
        $limit = max(1, min($request->integer('limit', 8), 20));

        if ($search === '') {
            return response()->json([
                'status' => true,
                'data' => [],
            ]);
        }

        $like = '%'.$search.'%';

        $productions = Production::query()
            ->notDeleted()
            ->whereNotNull('spk_no')
            ->where('spk_no', '!=', '')
            ->where(function ($query) use ($like): void {
                $query->where('spk_no', 'like', $like)
                    ->orWhere('spk_type', 'like', $like)
                    ->orWhere('request_order_no', 'like', $like)
                    ->orWhere('request_stock_no', 'like', $like)
                    ->orWhere('customer_name', 'like', $like)
                    ->orWhere('item_name', 'like', $like)
                    ->orWhere('description', 'like', $like)
                    ->orWhere('last_process', 'like', $like);
            })
            ->orderByDesc('row_id')
            ->limit($limit)
            ->get([
                'row_id',
                'spk_no',
                'spk_type',
                'customer_name',
                'item_name',
                'last_process',
                'status',
            ]);

        return response()->json([
            'status' => true,
            'data' => $productions->map(fn (Production $production): array => [
                'rowId' => (int) $production->row_id,
                'spkNo' => (string) $production->spk_no,
                'spkType' => filled($production->spk_type)
                    ? (string) $production->spk_type
                    : null,
                'customer' => filled($production->customer_name)
                    ? (string) $production->customer_name
                    : null,
                'item' => filled($production->item_name)
                    ? (string) $production->item_name
                    : null,
                'lastProcess' => filled($production->last_process)
                    ? (string) $production->last_process
                    : null,
            ])->values()->all(),
        ]);
    }

    /**
     * Display the specified SPK production.
     */
    public function show(
        Request $request,
        Production $production,
        SpkProcessMapper $processMapper,
        SpkShrinkSummary $shrinkSummary,
        SpkCraftsmanReport $craftsmanReport,
        SpkGoldReport $goldReport,
        SpkStoneReport $stoneReport,
        SpkProductionControlReport $productionControlReport,
        SpkStatusMapper $statusMapper,
    ): Response {
        abort_if($production->is_deleted === 1 || blank($production->spk_no), 404);

        $production->loadMissing(['item', 'sku', 'categoryPrefix']);
        $statusKey = $this->normalizedBacklogStatusKey(
            $request->string('status')->trim()->toString(),
        );

        $navigation = $this->buildStatusScopedNavigation(
            $production,
            $statusKey,
            fn (int $targetRowId): string => route('spk.show', [
                'production' => Production::query()->where('row_id', $targetRowId)->value('spk_no'),
                'status' => $statusKey,
            ]),
        );

        $processes = $processMapper->forProduction((int) $production->row_id);
        $approvalService = app(SpkApprovalService::class);
        $approval = $this->approvalAbilities($request, $production);

        return Inertia::render('spk/show', [
            'production' => $this->toDetail($production, $statusMapper),
            'item' => $this->toItemDetail($production),
            'stones' => $this->toShowStones($production),
            'processes' => $processes,
            'defaultProcessSelection' => $processMapper->resolveDefaultSelection($production->last_process),
            'shrinkReport' => $shrinkSummary->forProduction($production),
            'craftsmanReport' => $craftsmanReport->forProduction($production),
            'goldReport' => $goldReport->forProduction($production),
            'stoneReport' => $stoneReport->forProduction($production),
            'productionControlReport' => $productionControlReport->forProduction($production, $goldReport),
            'navigation' => $navigation,
            'detailUrl' => route('spk.show', $production, absolute: true),
            'approval' => $approval,
            'approvalTimeline' => $approvalService->mergeTimeline($approval['history'], $processes),
            'approvalFooter' => $approvalService->footerColumns(
                $production,
                $this->actorName($request),
            ),
            'polesChromeComplete' => $this->polesChromeCompleteAction(
                $request,
                $production,
                $processMapper,
            ),
        ]);
    }

    /**
     * @return array{
     *     documentId: int,
     *     docNo: string|null,
     *     completeUrl: string
     * }|null
     */
    private function polesChromeCompleteAction(
        Request $request,
        Production $production,
        SpkProcessMapper $processMapper,
    ): ?array {
        if ($processMapper->processKeyForLastProcess($production->last_process) !== 'Poles Chrome') {
            return null;
        }

        $document = PolishFinishedGood::query()
            ->notDeleted()
            ->where('spk_id', $production->row_id)
            ->orderByDesc('row_id')
            ->first();

        if ($document === null) {
            return null;
        }

        $approvalService = app(PolishFinishedGoodApprovalService::class);

        if (! $approvalService->abilitiesFor($document, $request->user())['canComplete']) {
            return null;
        }

        return [
            'documentId' => (int) $document->row_id,
            'docNo' => filled($document->doc_no) ? (string) $document->doc_no : null,
            'completeUrl' => route('poles-chrome.complete', $document),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function toItemDetail(Production $production): array
    {
        $production->loadMissing(['sku', 'categoryPrefix']);

        $ukuran = $this->ukuranFromProduction($production);
        $qtyLabel = $production->qty !== null
            ? SpkQtyUnit::label((int) $production->qty, $production->satuan)
            : '-';
        $jwcad3d = filled($production->jwcad_3d) ? (string) $production->jwcad_3d : '-';
        $itemTypeName = $this->itemTypeLabel($production);
        $productItemLabel = $this->productItemLabel($production);
        $typeCode = trim((string) ($production->categoryPrefix?->prefix ?? ''));
        $productItemName = trim((string) ($production->sku?->item_original ?? ''));
        $skuCode = trim((string) ($production->sku?->sku_code ?? ''));
        $typeVariant = $this->joinTypeVariantLabel($typeCode, $productItemName);

        return [
            'id' => $production->category_prefix_id !== null
                ? (string) $production->category_prefix_id
                : null,
            'name' => $typeVariant !== '-'
                ? $typeVariant
                : $this->joinTypeVariantLabel($itemTypeName, $productItemLabel),
            'typeCode' => $typeCode !== '' ? $typeCode : '-',
            'productItemName' => $productItemName !== '' ? $productItemName : '-',
            'skuCode' => $skuCode !== '' ? $skuCode : '-',
            'itemType' => $itemTypeName !== '' ? $itemTypeName : '-',
            'itemVariance' => $productItemLabel !== '' ? $productItemLabel : '-',
            'statusOrderLabel' => $this->statusOrder->displayLabel(
                $production->sku_id,
                $production->row_id,
            ),
            'qty' => $qtyLabel,
            'diameter' => $ukuran['diameter'],
            'dimensi' => $ukuran['dimensi'],
            'ringSize' => $ukuran['ringSize'],
            'diameterLengthRingSize' => filled($production->diameter_length_ringsize)
                ? $production->diameter_length_ringsize
                : '-',
            'goldWeight' => filled($production->gold_weight)
                ? number_format((float) $production->gold_weight, 2, '.', '')
                : '-',
            'masterGoldWeight' => $this->skuMasterGoldWeight($production->sku),
            'goldColor' => $production->gold_color ?: '-',
            'jwcad3d' => $jwcad3d,
            'description' => filled($production->description) ? (string) $production->description : '-',
            'imageUrl' => $this->itemImageUrl($production),
            'finishingType' => $jwcad3d,
        ];
    }

    /**
     * @param  Closure(int): string  $urlForRowId
     * @return array{
     *     position: int,
     *     total: int,
     *     previousUrl: string|null,
     *     nextUrl: string|null,
     *     backUrl: string
     * }
     */
    private function buildStatusScopedNavigation(
        Production $production,
        ?string $statusKey,
        Closure $urlForRowId,
    ): array {
        $baseQuery = Production::query()->notDeleted()->whereNotNull('spk_no');

        if ($statusKey !== null) {
            $this->applyBacklogStatusFilter($baseQuery, $statusKey);
        }

        $total = (clone $baseQuery)->count();
        $position = (clone $baseQuery)->where('row_id', '>', $production->row_id)->count() + 1;
        $previousRowId = (clone $baseQuery)
            ->where('row_id', '>', $production->row_id)
            ->orderBy('row_id')
            ->value('row_id');
        $nextRowId = (clone $baseQuery)
            ->where('row_id', '<', $production->row_id)
            ->orderByDesc('row_id')
            ->value('row_id');

        return [
            'position' => $position,
            'total' => $total,
            'previousUrl' => $previousRowId !== null ? $urlForRowId((int) $previousRowId) : null,
            'nextUrl' => $nextRowId !== null ? $urlForRowId((int) $nextRowId) : null,
            'backUrl' => $statusKey !== null
                ? route($this->indexRouteNameFor($production), ['status' => $this->backlogStatusFilterLabel($statusKey)])
                : route($this->indexRouteNameFor($production)),
        ];
    }

    private function indexRouteNameFor(Production $production): string
    {
        return $production->spk_type === SpkService::REPARATION_TYPE
            ? 'reparasi.index'
            : 'spk.index';
    }

    private function normalizedBacklogStatusKey(?string $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $value = trim($value);
        $labels = SpkDashboardAnalytics::BACKLOG_STATUS_LABELS;

        if (array_key_exists($value, $labels)) {
            return $value;
        }

        if ($value === 'done' || strcasecmp($value, 'Done') === 0) {
            return 'done';
        }

        if (
            $value === 'DONE RANGKA'
            || strcasecmp($value, 'DONE (Rangka)') === 0
        ) {
            return SpkDashboardAnalytics::KEY_DONE_RANGKA;
        }

        if (
            $value === 'DONE BARANG JADI'
            || strcasecmp($value, 'DONE (Barang Jadi)') === 0
        ) {
            return SpkDashboardAnalytics::KEY_DONE_BARANG_JADI;
        }

        $key = array_search($value, $labels, true);

        return is_string($key) ? $key : null;
    }

    private function backlogStatusFilterLabel(string $statusKey): string
    {
        if ($statusKey === 'done') {
            return 'Done';
        }

        return SpkDashboardAnalytics::BACKLOG_STATUS_LABELS[$statusKey] ?? $statusKey;
    }

    /**
     * @param  array<int, string>|null  $doneKinds
     */
    private function applyBacklogStatusFilter(Builder $query, string $statusKey, ?array $doneKinds = null): void
    {
        $doneKinds ??= SpkDashboardAnalytics::completedProductionKinds(
            Production::query()->notDeleted()->pluck('row_id')->all(),
        );

        if ($statusKey === 'done' || SpkDashboardAnalytics::isDoneStatusKey($statusKey)) {
            $doneIds = $statusKey === 'done'
                ? array_keys($doneKinds)
                : collect($doneKinds)
                    ->filter(fn (string $kind): bool => $kind === $statusKey)
                    ->keys()
                    ->all();

            $query->whereIntegerInRaw('row_id', $doneIds);

            return;
        }

        if ($doneKinds !== []) {
            $query->whereIntegerNotInRaw('row_id', array_keys($doneKinds));
        }

        if ($statusKey === 'inProgress') {
            $query->where(function ($builder): void {
                $builder->whereNotNull('last_process')
                    ->orWhere('is_inprocess', '!=', 0);
            });

            return;
        }

        if ($statusKey === 'confirmed') {
            $query->where('status', SpkApprovalService::STATUS_DONE)
                ->where(function ($builder): void {
                    $builder->where('is_inprocess', 0)->orWhereNull('is_inprocess');
                })
                ->whereNull('last_process');

            return;
        }

        if ($statusKey === 'pendingManager') {
            $query->where('status', SpkApprovalService::STATUS_PENDING)
                ->where(function ($builder): void {
                    $builder->where('is_inprocess', 0)->orWhereNull('is_inprocess');
                })
                ->whereNull('last_process');

            return;
        }

        if ($statusKey === 'draft') {
            $query->where(function ($builder): void {
                $builder->whereNull('status')
                    ->orWhereNotIn('status', [
                        SpkApprovalService::STATUS_DONE,
                        SpkApprovalService::STATUS_PENDING,
                    ]);
            })
                ->where(function ($builder): void {
                    $builder->where('is_inprocess', 0)->orWhereNull('is_inprocess');
                })
                ->whereNull('last_process');
        }
    }

    private function itemTypeLabel(Production $production): string
    {
        $production->loadMissing('categoryPrefix');

        if ($production->categoryPrefix !== null) {
            return $production->categoryPrefix->displayName();
        }

        return filled($production->item_name) ? (string) $production->item_name : '';
    }

    private function productItemLabel(Production $production): string
    {
        $production->loadMissing('sku');

        if ($production->sku !== null) {
            return $production->sku->displayName();
        }

        return '';
    }

    private function joinTypeVariantLabel(string $itemTypeName, string $varianceName): string
    {
        $parts = array_values(array_filter(
            [trim($itemTypeName), trim($varianceName)],
            fn (string $part): bool => $part !== '',
        ));

        return $parts === [] ? '-' : implode(' | ', $parts);
    }

    private function listTypeSkuLabel(Production $production): ?string
    {
        if (! $this->listHasAssignedSku($production)) {
            return null;
        }

        $production->loadMissing(['categoryPrefix', 'sku']);

        $typeCode = trim((string) ($production->categoryPrefix?->prefix ?? ''));
        $skuCode = trim((string) ($production->sku?->sku_code ?? ''));

        $parts = array_values(array_filter(
            [$typeCode, $skuCode],
            fn (string $part): bool => $part !== '',
        ));

        return $parts !== [] ? implode(' | ', $parts) : null;
    }

    private function listItemDescriptionText(Production $production): ?string
    {
        if (! filled($production->description)) {
            return null;
        }

        $description = trim((string) $production->description);

        return $description !== '' ? $description : null;
    }

    private function listSearchDescription(Production $production): string
    {
        $parts = array_values(array_filter([
            $this->listTypeSkuLabel($production),
            $this->listItemDescriptionText($production),
            $this->listHasAssignedSku($production) ? null : 'belum assign SKU',
        ], fn (?string $part): bool => filled($part)));

        return $parts !== [] ? implode(' ', $parts) : '-';
    }

    private function listHasAssignedSku(Production $production): bool
    {
        if (! filled($production->sku_id) || (int) $production->sku_id === 0) {
            return false;
        }

        $production->loadMissing('sku');

        return $production->sku !== null;
    }

    /**
     * @return array{diameter: string, dimensi: string, ringSize: string}
     */
    private function ukuranFieldsForForm(?string $label): array
    {
        $parsed = app(SpkService::class)->parseUkuranLabel($label);

        return [
            'diameter' => $parsed['diameter'] ?? '',
            'dimensi' => $parsed['dimensi'] ?? '',
            'ringSize' => $parsed['ring_size'] ?? '',
        ];
    }

    /**
     * @return array{diameter: string, dimensi: string, ringSize: string}
     */
    private function splitUkuranLabel(?string $value): array
    {
        $parsed = app(SpkService::class)->parseUkuranLabel($value);

        $normalize = static fn (?string $part): string => filled($part) ? (string) $part : '-';

        return [
            'diameter' => $normalize($parsed['diameter']),
            'dimensi' => $normalize($parsed['dimensi']),
            'ringSize' => $normalize($parsed['ring_size']),
        ];
    }

    /**
     * @return array{diameter: string, dimensi: string, ringSize: string}
     */
    private function ukuranFromProduction(Production $production): array
    {
        return $this->splitUkuranLabel($production->diameter_length_ringsize);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function toShowStones(Production $production): array
    {
        $production->loadMissing([
            'sku.diamonds' => fn ($query) => $query->notDeleted()->orderBy('line_id'),
        ]);

        $masterStones = $production->sku !== null
            ? $this->diamondMapper->toFormStones($production->sku->diamonds)
            : [];

        $stones = $production->stones()
            ->notDeleted()
            ->with(['shape', 'position'])
            ->orderBy('line_id')
            ->get()
            ->values();

        $stockByLineId = $this->stoneStockChecker->forStones($stones);

        return $stones
            ->map(function (SpkStone $stone, int $index) use ($masterStones, $stockByLineId): array {
                $item = $this->toStoneItem($stone);
                $item['stock'] = $stockByLineId[(int) $stone->line_id] ?? null;
                $master = $masterStones[$index] ?? null;

                $item['master'] = $master === null
                    ? null
                    : [
                        'positionName' => filled($master['positionNama']) ? $master['positionNama'] : null,
                        'shapeName' => filled($master['shapeName']) ? $master['shapeName'] : null,
                        'shapeId' => filled($master['shapeId']) ? $master['shapeId'] : null,
                        'size' => filled($master['size']) ? $master['size'] : null,
                        'caratPerPcs' => filled($master['caratPerPcs']) ? $master['caratPerPcs'] : null,
                        'pcs' => filled($master['pcs']) ? $master['pcs'] : null,
                    ];

                return $item;
            })
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function toStoneItem(SpkStone $stone): array
    {
        $pcs = (int) ($stone->pcs ?? 0);
        $totalCarat = (float) ($stone->carat ?? 0);
        $caratPerPcs = $pcs > 0 ? round($totalCarat / $pcs, 3) : 0;
        $shapeName = $this->shapeDisplayName($stone->shape);

        return [
            'id' => (string) $stone->line_id,
            'positionId' => $stone->position_id !== null
                ? (string) $stone->position_id
                : '',
            'positionName' => $stone->position?->nama ?? '-',
            'shape' => $stone->shape?->name ?? '-',
            'shapeCode' => $stone->shape?->code ?? '-',
            'shapeName' => $shapeName,
            'pcs' => $pcs,
            'carat' => $caratPerPcs,
            'caratPerPcs' => number_format($caratPerPcs, 3, '.', ''),
            'totalCarat' => number_format($totalCarat, 3, '.', ''),
            'size' => $stone->size ?? '-',
        ];
    }

    private function customerName(Production $production): string
    {
        return filled($production->customer_name) ? $production->customer_name : '-';
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

    private function requestOrderLabel(Production $production): string
    {
        if ($production->spk_type !== 'Pesanan' || blank($production->request_order_no)) {
            return '-';
        }

        return app(RequestOrderRepository::class)->displayLabelByDocNo(
            (string) $production->request_order_no,
            filled($production->customer_name) ? (string) $production->customer_name : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function toListItem(
        Production $production,
        bool|string|null $completed = null,
        ?string $lastProcessDate = null,
    ): array {
        return [
            'id' => (string) $production->row_id,
            'produksiNo' => $production->spk_no ?? '-',
            'tipeProduksi' => $production->spk_type ?? '-',
            'customer' => $this->customerListLabel($production),
            'item' => $production->relationLoaded('item') && $production->item !== null
                ? ($production->item->name ?? '-')
                : ($production->item_name ?? '-'),
            'typeSkuLabel' => $this->listTypeSkuLabel($production),
            'itemDescription' => $this->listItemDescriptionText($production),
            'description' => $this->listSearchDescription($production),
            'skuAssigned' => $this->listHasAssignedSku($production),
            'itemId' => $production->item_id !== null ? (string) $production->item_id : null,
            'orderDate' => $production->order_date?->format('d-M-Y') ?? '-',
            'createdDate' => $production->created_date?->format('d-M-Y') ?? '-',
            'estimatedDelivery' => $production->estimated_delivery_time?->format('d-M-Y') ?? '-',
            'status' => SpkDashboardAnalytics::backlogStatusLabel($production, $completed),
            'prosesTerakhir' => $production->last_process ?? '',
            'prosesTerakhirDate' => $lastProcessDate ?? '',
        ];
    }

    /**
     * Mapper baris index untuk satu halaman SPK; data proses & request order dimuat sekali per halaman.
     *
     * @param  Collection<int, Production>  $pageProductions
     * @return Closure(Production): array<string, mixed>
     */
    private function indexRowMapper(Collection $pageProductions): Closure
    {
        $doneKinds = SpkDashboardAnalytics::completedProductionKinds(
            $pageProductions->pluck('row_id')->all(),
        );
        $lastProcessDates = SpkDashboardAnalytics::lastProcessDatesFor($pageProductions);
        $requestOrders = app(RequestOrderRepository::class)->rowsByDocNos(array_values(
            $pageProductions
                ->where('spk_type', 'Pesanan')
                ->pluck('request_order_no')
                ->map(fn (mixed $docNo): string => trim((string) $docNo))
                ->all(),
        ));

        $latestReceipts = $this->latestReceiptsBySpkRowId(array_values(
            $pageProductions->pluck('row_id')->map(fn (mixed $id): int => (int) $id)->all(),
        ));

        return function (Production $production) use ($doneKinds, $lastProcessDates, $requestOrders, $latestReceipts): array {
            $row = $this->toIndexRow(
                $production,
                $doneKinds[(int) $production->row_id] ?? false,
                $lastProcessDates[(int) $production->row_id] ?? null,
                $requestOrders[trim((string) $production->request_order_no)] ?? null,
            );
            $receipt = $latestReceipts[(int) $production->row_id] ?? null;

            if ($receipt !== null && trim((string) $row['prosesTerakhir']) === '') {
                $destination = trim((string) $receipt->untuk);
                $row['prosesTerakhir'] = 'Diserahkan ke '.($destination !== '' ? $destination : 'Workshop');
                $row['prosesTerakhirDate'] = $receipt->tanggal->format('d-M-Y');
            }

            return $row;
        };
    }

    /**
     * Tanda terima serah terima terbaru (belum dihapus) per SPK row id.
     *
     * @param  list<int>  $spkRowIds
     * @return array<int, SerahTerimaSpk>
     */
    private function latestReceiptsBySpkRowId(array $spkRowIds): array
    {
        if ($spkRowIds === []) {
            return [];
        }

        $wantedIds = array_flip($spkRowIds);
        $latest = [];

        SerahTerimaSpk::query()
            ->where(function ($query) use ($spkRowIds): void {
                foreach ($spkRowIds as $spkRowId) {
                    $query->orWhereJsonContains('spk_row_ids', $spkRowId);
                }
            })
            ->orderByDesc('id')
            ->get(['id', 'doc_no', 'tanggal', 'untuk', 'spk_row_ids'])
            ->each(function (SerahTerimaSpk $receipt) use ($wantedIds, &$latest): void {
                foreach ($receipt->spk_row_ids as $spkRowId) {
                    $spkRowId = (int) $spkRowId;

                    if (isset($wantedIds[$spkRowId]) && ! isset($latest[$spkRowId])) {
                        $latest[$spkRowId] = $receipt;
                    }
                }
            });

        return $latest;
    }

    /**
     * Baris tabel index SPK: data list ditambah kolom Item (gambar, SKU) dan referensi pesanan.
     *
     * @return array<string, mixed>
     */
    private function toIndexRow(
        Production $production,
        bool|string|null $completed = null,
        ?string $lastProcessDate = null,
        ?stdClass $requestOrder = null,
    ): array {
        $row = $this->toListItem($production, $completed, $lastProcessDate);
        $typeCode = trim((string) ($production->categoryPrefix?->prefix ?? ''));
        $productItemName = trim((string) ($production->sku?->item_original ?? ''));

        if ($productItemName === '') {
            $productItemName = trim((string) ($production->item_name ?? ''));
        }

        $orderReference = null;
        $paymentStatus = null;
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
            $paymentStatus = $requestOrders->paymentStatusLabel($requestOrder?->is_fully_paid);
            $orderType = $requestOrders->typeOrderLabel($requestOrder?->type_order);
        }

        return [
            ...$row,
            'rowId' => (int) $production->row_id,
            'orderReference' => $orderReference,
            'requestStockNo' => filled($production->request_stock_no)
                ? (string) $production->request_stock_no
                : null,
            'paymentStatus' => $paymentStatus,
            'orderType' => $orderType,
            'skuCode' => filled($production->sku?->sku_code)
                ? (string) $production->sku->sku_code
                : null,
            'typeCode' => $typeCode !== '' ? $typeCode : null,
            'productItemName' => $productItemName !== '' ? $productItemName : null,
            'spkImageUrl' => SpkItemImageUrl::fromFileName($production->file_name),
            'createdBy' => filled($production->created_by) ? (string) $production->created_by : null,
            'targetDaysLeft' => $production->estimated_delivery_time !== null
                ? (int) now()->startOfDay()->diffInDays(
                    $production->estimated_delivery_time->copy()->startOfDay(),
                    false,
                )
                : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function toDetail(Production $production, SpkStatusMapper $statusMapper): array
    {
        $completedKind = SpkDashboardAnalytics::completedProductionKind((int) $production->row_id);
        $refSpkNo = '-';

        if ($production->ref_spk_id !== null) {
            $resolvedRefSpkNo = Production::query()
                ->notDeleted()
                ->where('row_id', $production->ref_spk_id)
                ->value('spk_no');

            if (filled($resolvedRefSpkNo)) {
                $refSpkNo = (string) $resolvedRefSpkNo;
            }
        }

        return [
            ...$this->toListItem($production, $completedKind ?? false),
            'customer' => $this->customerName($production),
            'status' => $production->status ?: '-',
            'requestOrderNo' => $production->request_order_no ?? '-',
            'requestStockNo' => filled($production->request_stock_no)
                ? (string) $production->request_stock_no
                : null,
            'requestOrderLabel' => $this->requestOrderLabel($production),
            'requestOrderCreatedDate' => $this->requestOrderCreatedDate($production),
            'refSpkNo' => $refSpkNo,
            'description' => $production->description ?? '-',
            'qty' => $production->qty ?? '-',
            'goldWeight' => filled($production->gold_weight)
                ? number_format((float) $production->gold_weight, 2, '.', '')
                : '-',
            'goldColor' => $production->gold_color ?? '-',
            'goldContent' => $production->gold_content ?? '-',
            'priority' => $production->priority ?? '-',
            ...$this->orderPriorityFields($production),
            'statusOrder' => $this->formatStatusOrder($production->status_order),
            'notes' => $production->notes ?? '-',
            'frameId' => $production->frame_id ?? '-',
            'fileName' => $production->file_name ?? '-',
            'lastWeight' => $production->last_weight ?? '-',
            'receivedByProductionDate' => app(SpkApprovalService::class)
                ->managerApprovedAt($production),
            'createdDate' => $production->created_date?->format('d-M-Y H:i') ?? '-',
            'createdBy' => $production->created_by ?? '-',
            'modifiedDate' => $production->modified_date?->format('d-M-Y H:i') ?? '-',
            'modifiedBy' => $production->modified_by ?? '-',
            'workflowStatus' => $statusMapper->map($production, $completedKind ?? false),
        ];
    }

    /**
     * @return array{orderPriorityLevel: string|null, orderPriorityLabel: string|null}
     */
    private function orderPriorityFields(Production $production): array
    {
        $orderPriority = app(SpkOrderPriorityResolver::class)->resolve(
            $production->spk_type,
            $production->request_order_no,
        );

        return [
            'orderPriorityLevel' => $orderPriority['level'] ?? null,
            'orderPriorityLabel' => $orderPriority['label'] ?? null,
        ];
    }

    private function requestOrderCreatedDate(Production $production, string $format = 'd-M-Y'): string
    {
        if ($production->spk_type !== 'Pesanan' || blank($production->request_order_no)) {
            return '-';
        }

        $transDate = app(RequestOrderRepository::class)
            ->transDateByDocNo((string) $production->request_order_no);

        if ($transDate === null) {
            return '-';
        }

        try {
            return Carbon::parse($transDate)->format($format);
        } catch (\Throwable) {
            return '-';
        }
    }

    private function formatStatusOrder(?string $statusOrder): string
    {
        $normalized = $this->normalizeStatusOrderCode($statusOrder);

        return match ($normalized) {
            'RO' => 'Repeat Order',
            'NO' => 'New Order',
            'PO' => 'PO',
            '' => '-',
            default => filled($statusOrder) ? trim((string) $statusOrder) : '-',
        };
    }

    private function normalizeStatusOrderCode(?string $statusOrder): string
    {
        $normalized = strtoupper(trim((string) $statusOrder));

        return match ($normalized) {
            'RO', 'REPEAT ORDER' => 'RO',
            'NO', 'NEW ORDER' => 'NO',
            'PO' => 'PO',
            default => $normalized,
        };
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function resolvePrintStatusOrderLabel(array $item): string
    {
        if (filled($item['statusOrderLabel'] ?? null)) {
            return $this->printText($item['statusOrderLabel']);
        }

        $skuId = isset($item['skuId']) && is_numeric($item['skuId'])
            ? (int) $item['skuId']
            : null;
        $productionId = isset($item['productionId']) && is_numeric($item['productionId'])
            ? (int) $item['productionId']
            : null;

        return $this->statusOrder->displayLabel($skuId, $productionId);
    }

    private function findActiveProduction(int $rowId): Production
    {
        return Production::query()
            ->notDeleted()
            ->where('row_id', $rowId)
            ->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'spkTypes' => SpkService::TYPES,
            'units' => SpkService::UNITS,
            'qtyUnitOptions' => SpkQtyUnit::options(),
            'statusOrders' => [
                ['value' => 'RO', 'label' => 'Repeat Order'],
                ['value' => 'NO', 'label' => 'New Order'],
                ['value' => 'PO', 'label' => 'PO'],
            ],
            'goldColors' => $this->goldColorOptions(),
            'shapeOptions' => MsShape::query()
                ->notDeleted()
                ->orderBy('name')
                ->get(['row_id', 'name', 'code'])
                ->map(fn (MsShape $shape): array => [
                    'value' => (string) $shape->row_id,
                    'label' => $this->shapeLabel($shape),
                    'name' => $this->shapeDisplayName($shape),
                ])
                ->values()
                ->all(),
            'positionOptions' => MsPosition::query()
                ->orderBy('nama')
                ->get(['id', 'nama'])
                ->map(fn (MsPosition $position): array => [
                    'value' => (string) $position->id,
                    'label' => (string) $position->nama,
                ])
                ->values()
                ->all(),
            'categories' => SkuPrefixCategory::query()
                ->active()
                ->orderBy('category')
                ->get(['id', 'category', 'prefix'])
                ->map(fn (SkuPrefixCategory $category): array => [
                    'value' => (string) $category->id,
                    'label' => $category->displayName(),
                    'prefix' => trim((string) $category->prefix),
                ])
                ->values()
                ->all(),
            'skus' => $this->skuFormOptions(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function skuFormOptions(): array
    {
        return SkuMaster::query()
            ->active()
            ->with([
                'categoryPrefix',
                'namePrefix',
                'sizePrefix',
                'stoneShapePrefix',
                'stoneTypePrefix',
                'diamondTypePrefix',
                'goldColorPrefix',
                'diamonds' => fn ($query) => $query->notDeleted()->orderBy('line_id'),
            ])
            ->orderBy('sku_code')
            ->get([
                'id',
                'sku_code',
                'item_original',
                'name_prefix_id',
                'category_prefix_id',
                'gold_prefix_id',
                'size_prefix_id',
                'stone_shape_prefix_id',
                'stone_type_prefix_id',
                'diamond_type_prefix_id',
                'crt',
                'gold_weight',
                'design_image',
                'file_jwlcad',
                'image_url',
                'catalog_image',
                'image_filename',
            ])
            ->map(fn (SkuMaster $sku): array => [
                'value' => (string) $sku->id,
                'label' => $sku->displayName(),
                'skuCode' => (string) $sku->sku_code,
                'itemOriginal' => (string) ($sku->item_original ?? ''),
                'categoryPrefixId' => $sku->category_prefix_id !== null
                    ? (string) $sku->category_prefix_id
                    : '',
                'description' => $this->descriptionExtractor->extract($sku),
                'goldColor' => (string) ($sku->resolvedGoldColor() ?? ''),
                'goldWeight' => $this->skuMasterGoldWeight($sku),
                'jwcad3d' => (string) ($sku->resolvedJwcadFile() ?? ''),
                'imageUrl' => $sku->resolvedImageUrl(),
                'stones' => $this->diamondMapper->toFormStones($sku->diamonds),
            ])
            ->values()
            ->all();
    }

    private function skuMasterGoldWeight(?SkuMaster $sku): ?string
    {
        if ($sku === null || $sku->gold_weight === null) {
            return null;
        }

        $weight = (float) $sku->gold_weight;

        if ($weight <= 0) {
            return null;
        }

        return number_format($weight, 2, '.', '');
    }

    /**
     * @return array{0: array<string, mixed>, 1: list<array<string, mixed>>}
     */
    private function createFormPayload(Request $request): array
    {
        $production = $this->emptyFormData();
        $orderDate = $this->queryDate($request, 'order_date');
        $estimatedDelivery = $this->queryDate($request, 'estimated_delivery_time');

        if ($orderDate !== null) {
            $production['orderDate'] = $orderDate;
        }

        if ($estimatedDelivery !== null) {
            $production['estimatedDeliveryTime'] = $estimatedDelivery;
        }

        $requestStockNo = $this->queryRequestStockNo($request);

        if ($requestStockNo !== null) {
            $production['requestStockNo'] = $requestStockNo;
        }

        $storeNotes = $this->queryStoreNotes($request);

        if ($storeNotes !== null) {
            $production['notes'] = $storeNotes;
        }

        $sku = $this->skuFromCreateQuery($request);

        if ($sku === null) {
            return [$production, []];
        }

        $categoryId = $sku->category_prefix_id !== null
            ? (string) $sku->category_prefix_id
            : '';
        $description = trim($this->descriptionExtractor->extract($sku));
        $production['itemTypeId'] = $categoryId;
        $production['categoryPrefixId'] = $categoryId;
        $production['skuId'] = (string) $sku->id;
        $production['description'] = $description !== '' ? $description : $sku->displayName();
        $production['goldColor'] = (string) ($sku->resolvedGoldColor() ?? '');
        $production['goldWeight'] = $this->skuMasterGoldWeight($sku)
            ?? $this->queryGoldWeight($request)
            ?? '0';
        $production['jwcad3d'] = (string) ($sku->resolvedJwcadFile() ?? '');

        return [$production, $this->stoneRowsFromSku($sku)];
    }

    private function queryDate(Request $request, string $key): ?string
    {
        $value = $request->string($key)->trim()->toString();

        if (! Carbon::hasFormat($value, 'Y-m-d')) {
            return null;
        }

        $date = Carbon::createFromFormat('!Y-m-d', $value);

        if ($date === false || $date->format('Y-m-d') !== $value) {
            return null;
        }

        return $value;
    }

    private function queryStoreNotes(Request $request): ?string
    {
        $notes = trim($request->string('store_notes')->toString());

        if ($notes === '') {
            return null;
        }

        return mb_substr('Catatan dari Toko: '.$notes, 0, 4000);
    }

    private function queryRequestStockNo(Request $request): ?string
    {
        $value = strtoupper($request->string('request_stock_no')->trim()->toString());

        if (preg_match('/^RS-[A-Z0-9]+$/', $value) !== 1 || strlen($value) > 40) {
            return null;
        }

        return $value;
    }

    private function queryGoldWeight(Request $request): ?string
    {
        $value = $request->string('gold_weight')->trim()->toString();

        if (! is_numeric($value) || (float) $value <= 0) {
            return null;
        }

        return number_format((float) $value, 2, '.', '');
    }

    private function skuFromCreateQuery(Request $request): ?SkuMaster
    {
        $code = strtoupper($request->string('sku')->trim()->toString());

        if ($code === '') {
            return null;
        }

        return SkuMaster::query()
            ->active()
            ->with([
                'categoryPrefix',
                'namePrefix',
                'sizePrefix',
                'stoneShapePrefix',
                'stoneTypePrefix',
                'diamondTypePrefix',
                'goldColorPrefix',
                'diamonds' => fn ($query) => $query->notDeleted()->orderBy('line_id'),
            ])
            ->whereRaw('UPPER(TRIM(sku_code)) = ?', [$code])
            ->first();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function stoneRowsFromSku(SkuMaster $sku): array
    {
        return collect($this->diamondMapper->toFormStones($sku->diamonds))
            ->values()
            ->map(function (array $stone, int $index): array {
                $pcs = is_numeric($stone['pcs']) ? (int) $stone['pcs'] : 0;
                $carat = is_numeric($stone['caratPerPcs']) ? (float) $stone['caratPerPcs'] : 0.0;

                return [
                    'id' => 'sku-stone-'.$index,
                    'positionId' => $stone['positionId'],
                    'positionName' => $stone['positionNama'],
                    'shape' => $stone['shapeName'] !== '' ? $stone['shapeName'] : '-',
                    'shapeId' => $stone['shapeId'],
                    'pcs' => $pcs,
                    'carat' => $carat,
                    'totalCarat' => round($pcs * $carat, 3),
                    'size' => $stone['size'] !== '' ? $stone['size'] : '-',
                ];
            })
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyFormData(): array
    {
        return [
            'id' => null,
            'isNew' => true,
            'spkNo' => null,
            'spkType' => 'Stock',
            'requestOrderNo' => null,
            'requestStockNo' => null,
            'customerName' => null,
            'itemName' => null,
            'refSpkId' => null,
            'refSpkNo' => null,
            'orderDate' => now()->toDateString(),
            'priority' => 'NO',
            'description' => '',
            'workEstimated' => null,
            'estimatedDeliveryTime' => '',
            'itemTypeId' => '',
            'categoryPrefixId' => '',
            'skuId' => '',
            'frameId' => '',
            'frameNo' => '',
            'qty' => '1',
            'satuan' => SpkService::DEFAULT_UNIT,
            'statusOrder' => '',
            'diameterLengthRingsize' => '',
            'diameter' => '',
            'dimensi' => '',
            'ringSize' => '',
            'goldWeight' => '0.000',
            'goldColor' => '',
            'goldContent' => '',
            'jwcad3d' => '',
            'notes' => '',
            'fileName' => null,
            'status' => '',
            'hasRequestOrder' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function toFormData(Production $production, ?string $frameNo, ?Production $reference): array
    {
        return [
            'id' => (int) $production->row_id,
            'isNew' => false,
            'spkNo' => (string) $production->spk_no,
            'spkType' => (string) ($production->spk_type ?? ''),
            'requestOrderNo' => $production->request_order_no,
            'requestStockNo' => filled($production->request_stock_no)
                ? (string) $production->request_stock_no
                : null,
            'requestOrderLabel' => $this->requestOrderLabel($production),
            'customerName' => $production->customer_name,
            'itemName' => $production->item_name,
            'refSpkId' => $production->ref_spk_id,
            'refSpkNo' => $reference?->spk_no,
            'orderDate' => $production->order_date?->format('Y-m-d') ?? '',
            'priority' => $production->priority ?? '',
            'description' => $production->description ?? '',
            'workEstimated' => $production->work_estimated,
            'estimatedDeliveryTime' => $production->estimated_delivery_time?->format('Y-m-d') ?? '',
            'itemTypeId' => $production->category_prefix_id !== null
                ? (string) $production->category_prefix_id
                : '',
            'categoryPrefixId' => $production->category_prefix_id !== null
                ? (string) $production->category_prefix_id
                : '',
            'skuId' => $production->sku_id !== null
                ? (string) $production->sku_id
                : '',
            'frameId' => $production->frame_id !== null ? (string) $production->frame_id : '',
            'frameNo' => $frameNo ?? '',
            'qty' => $production->qty !== null ? (string) $production->qty : '',
            'satuan' => filled($production->satuan)
                ? (string) $production->satuan
                : SpkService::DEFAULT_UNIT,
            'statusOrder' => $production->status_order ?? '',
            'diameterLengthRingsize' => $production->diameter_length_ringsize ?? '',
            ...$this->ukuranFieldsForForm($production->diameter_length_ringsize),
            'goldWeight' => $production->gold_weight !== null
                ? number_format((float) $production->gold_weight, 2, '.', '')
                : '0',
            'goldColor' => $production->gold_color ?? '',
            'goldContent' => $production->gold_content ?? '',
            'jwcad3d' => $production->jwcad_3d ?? '',
            'notes' => $production->notes ?? '',
            'fileName' => $production->file_name,
            'status' => $production->status ?? '',
            'hasRequestOrder' => filled($production->request_order_no),
        ];
    }

    /**
     * @return list<string>
     */
    private function goldColorOptions(): array
    {
        return GoldColorOptions::all();
    }

    private function shapeLabel(?MsShape $shape): string
    {
        if ($shape === null) {
            return '-';
        }

        $parts = array_filter([
            $shape->name,
            $shape->code,
        ], fn ($value): bool => filled($value));

        return $parts !== []
            ? implode(' - ', $parts)
            : 'Shape #'.$shape->row_id;
    }

    private function shapeDisplayName(?MsShape $shape): string
    {
        if ($shape === null) {
            return '-';
        }

        $name = trim((string) ($shape->name ?? ''));

        if ($name !== '') {
            return $name;
        }

        $code = trim((string) ($shape->code ?? ''));

        return $code !== '' ? $code : 'Shape #'.$shape->row_id;
    }

    private function blankPrintView(string $title): View
    {
        $blank = '';

        return view('spk.print', [
            'title' => $title,
            'header' => $this->documentHeader(),
            'blankTemplate' => true,
            'document' => [
                'info' => [
                    'spkNo' => $blank,
                    'spkType' => $blank,
                    'requestOrderNo' => $blank,
                    'requestOrderCreatedDate' => $blank,
                    'refSpkNo' => $blank,
                    'customerName' => $blank,
                    'orderDate' => $blank,
                    'receivedByProductionDate' => $blank,
                    'workEstimated' => $blank,
                    'estimatedDelivery' => $blank,
                    'priority' => $blank,
                ],
                'item' => [
                    'typeVariant' => $blank,
                    'typeCode' => $blank,
                    'productItemName' => $blank,
                    'skuCode' => $blank,
                    'statusOrderLabel' => $blank,
                    'qty' => $blank,
                    'diameter' => $blank,
                    'dimensi' => $blank,
                    'ringSize' => $blank,
                    'goldWeight' => $blank,
                    'goldColor' => $blank,
                    'jwcad3d' => $blank,
                    'description' => $blank,
                    'imageUrl' => $blank,
                ],
                'stones' => [],
                'notes' => $blank,
                'approval' => [
                    ['title' => 'Dibuat Oleh', 'name' => $blank, 'date' => $blank],
                    ['title' => 'Disetujui Oleh', 'name' => $blank, 'date' => $blank],
                    ['title' => 'Manager Produksi', 'name' => $blank, 'date' => $blank],
                ],
                'detailUrl' => $blank,
            ],
        ]);
    }

    /**
     * @return array{
     *     logoUrl: string,
     *     companyName: string,
     *     formTitle: string,
     *     docNo: string,
     *     issueNo: string,
     *     revision: string,
     *     issueDate: string
     * }
     */
    private function documentHeader(): array
    {
        return [
            'logoUrl' => asset((string) config('spk.logo', 'images/logo.jpg')),
            'companyName' => (string) config('spk.company_name', 'Wanda House of Jewels'),
            'formTitle' => (string) config('spk.form_title', 'Form SPK'),
            'docNo' => (string) config('spk.form_document_no', 'WHOJ-PRD-FRM-001'),
            'issueNo' => (string) config('spk.issue_no', '01'),
            'revision' => (string) config('spk.revision', '00'),
            'issueDate' => $this->documentIssueDate(),
        ];
    }

    private function documentIssueDate(): string
    {
        $issueDate = config('spk.issue_date');

        if (is_string($issueDate) && trim($issueDate) !== '') {
            return trim($issueDate);
        }

        return now()->format('d-m-Y');
    }

    /**
     * @return list<array{title: string, name: string, date: string}>
     */
    private function approvalFooter(Request $request): array
    {
        $actor = $this->actorName($request);
        $now = now()->format('d/m/Y H:i');

        return [
            [
                'title' => 'Dibuat Oleh',
                'name' => $actor !== '' ? $actor : '-',
                'date' => $now,
            ],
            [
                'title' => 'Disetujui Oleh',
                'name' => '-',
                'date' => '-',
            ],
            [
                'title' => 'Manager Produksi',
                'name' => '-',
                'date' => '-',
            ],
        ];
    }

    /**
     * @return array{
     *     canEdit: bool,
     *     canSubmit: bool,
     *     canApprove: bool,
     *     canReject: bool,
     *     status: string,
     *     statusLabel: string,
     *     history: list<array{status: string, statusLabel: string, approve: string, notes: string|null, createdBy: string|null, createdAt: string|null}>,
     *     role: string
     * }
     */
    private function approvalAbilities(Request $request, Production $production): array
    {
        return app(SpkApprovalService::class)->abilitiesFor($production, $request->user());
    }

    /**
     * @return array{
     *     canEdit: bool,
     *     canSubmit: bool,
     *     canApprove: bool,
     *     canReject: bool,
     *     status: string,
     *     statusLabel: string,
     *     history: list<array<string, mixed>>,
     *     role: string,
     *     permissions: list<string>
     * }
     */
    private function emptyApprovalAbilities(Request $request): array
    {
        $user = $request->user();

        return [
            'canEdit' => SpkApprovalRoles::canEditDraft($user),
            'canSubmit' => false,
            'canApprove' => false,
            'canManagerApprove' => false,
            'canReject' => false,
            'canDelete' => false,
            'status' => 'DRAFT',
            'statusLabel' => 'Draft',
            'history' => [],
            'role' => SpkApprovalRoles::roleLabel($user),
            'permissions' => SpkApprovalRoles::permissionNames($user),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{
     *     info: array<string, string>,
     *     item: array<string, string>,
     *     stones: list<array{positionName: string, shapeName: string, size: string, caratPerPcs: string, pcs: string, totalCarat: string}>,
     *     notes: string,
     *     approval: list<array{title: string, name: string, date: string}>
     * }
     */
    private function normalizePrintDocument(array $payload, Request $request): array
    {
        $info = is_array($payload['info'] ?? null) ? $payload['info'] : [];
        $item = is_array($payload['item'] ?? null) ? $payload['item'] : [];
        $stones = is_array($payload['stones'] ?? null) ? $payload['stones'] : [];
        $approval = is_array($payload['approval'] ?? null) ? $payload['approval'] : [];

        $normalizedStones = [];

        foreach ($stones as $stone) {
            if (! is_array($stone)) {
                continue;
            }

            $normalizedStones[] = [
                'positionName' => $this->printText($stone['positionName'] ?? null),
                'shapeName' => $this->printText($stone['shapeName'] ?? null),
                'size' => $this->printSize($stone['size'] ?? null),
                'caratPerPcs' => $this->printDecimal3($stone['caratPerPcs'] ?? null),
                'pcs' => $this->printText($stone['pcs'] ?? null),
                'totalCarat' => $this->printDecimal3($stone['totalCarat'] ?? null),
            ];
        }

        $normalizedApproval = [];

        foreach ($approval as $column) {
            if (! is_array($column)) {
                continue;
            }

            $normalizedApproval[] = [
                'title' => $this->printText($column['title'] ?? null),
                'name' => $this->printText($column['name'] ?? null),
                'date' => $this->printText($column['date'] ?? null),
            ];
        }

        if ($normalizedApproval === []) {
            $normalizedApproval = $this->approvalFooter($request);
        }

        $rawSpkType = trim((string) ($info['spkType'] ?? ''));
        $rawRequestOrderNo = trim((string) ($info['requestOrderNo'] ?? ''));

        if (in_array($rawRequestOrderNo, ['-', '—'], true)) {
            $rawRequestOrderNo = '';
        }

        $orderPriority = app(SpkOrderPriorityResolver::class)->resolve(
            $rawSpkType,
            $rawRequestOrderNo,
        );

        $requestOrderCustomer = trim((string) ($info['customerName'] ?? ''));
        if (in_array($requestOrderCustomer, ['-', '—'], true)) {
            $requestOrderCustomer = '';
        }

        $requestOrderLabel = $rawSpkType === 'Pesanan' && $rawRequestOrderNo !== ''
            ? app(RequestOrderRepository::class)->displayLabelByDocNo(
                $rawRequestOrderNo,
                $requestOrderCustomer !== '' ? $requestOrderCustomer : null,
            )
            : '';

        return [
            'info' => [
                'spkNo' => $this->printText(
                    $info['spkNo'] ?? null,
                    now()->format('Y').'/PRD/00000',
                ),
                'spkType' => $this->printText($info['spkType'] ?? null),
                'requestOrderNo' => $this->printText($info['requestOrderNo'] ?? null),
                'requestOrderLabel' => $this->printText($requestOrderLabel !== '' ? $requestOrderLabel : null),
                'requestOrderCreatedDate' => $this->printText($info['requestOrderCreatedDate'] ?? null),
                'refSpkNo' => $this->printText($info['refSpkNo'] ?? null),
                'customerName' => $this->printText($info['customerName'] ?? null),
                'orderDate' => $this->printText($info['orderDate'] ?? null),
                'receivedByProductionDate' => $this->printText(
                    in_array(trim((string) ($info['receivedByProductionDate'] ?? '')), ['', '-'], true)
                        ? null
                        : ($info['receivedByProductionDate'] ?? null),
                    '',
                ),
                'workEstimated' => $this->printText($info['workEstimated'] ?? null),
                'estimatedDelivery' => $this->printText($info['estimatedDelivery'] ?? null),
                'priority' => $this->printText($info['priority'] ?? null),
                'orderPriorityLevel' => $orderPriority['level'] ?? '',
                'orderPriorityLabel' => $orderPriority['label'] ?? '',
                'statusOrder' => $this->printText($info['statusOrder'] ?? null),
                'itemType' => $this->printText($info['itemType'] ?? null),
                'itemVariance' => $this->printText($info['itemVariance'] ?? null),
                'qty' => $this->printText($info['qty'] ?? null),
            ],
            'item' => [
                'typeVariant' => $this->printText($item['typeVariant'] ?? null),
                'typeCode' => $this->printText($item['typeCode'] ?? null, ''),
                'productItemName' => $this->printText($item['productItemName'] ?? null, ''),
                'skuCode' => $this->printText($item['skuCode'] ?? null, ''),
                'statusOrderLabel' => $this->resolvePrintStatusOrderLabel($item),
                'qty' => $this->printText($item['qty'] ?? null),
                'diameter' => $this->printSize($item['diameter'] ?? null),
                'dimensi' => $this->printSize($item['dimensi'] ?? null),
                'ringSize' => $this->printText($item['ringSize'] ?? null),
                'goldWeight' => $this->printDecimal3($item['goldWeight'] ?? null),
                'goldColor' => $this->printText($item['goldColor'] ?? null),
                'jwcad3d' => $this->printText($item['jwcad3d'] ?? null),
                'description' => $this->printText($item['description'] ?? null),
                'imageUrl' => $this->absolutePrintImageUrl($item['imageUrl'] ?? null, $request),
            ],
            'stones' => $normalizedStones,
            'notes' => $this->printText($payload['notes'] ?? null, ''),
            'approval' => $normalizedApproval,
            'detailUrl' => $this->resolvePrintQrUrl(
                filled($payload['detailUrl'] ?? null)
                    ? (string) $payload['detailUrl']
                    : null,
            ),
        ];
    }

    /**
     * @return array{
     *     info: array<string, string>,
     *     item: array<string, string>,
     *     stones: list<array{positionName: string, shapeName: string, size: string, caratPerPcs: string, pcs: string, totalCarat: string}>,
     *     notes: string,
     *     approval: list<array{title: string, name: string, date: string}>
     * }
     */
    private function printDocumentFromProduction(Production $production, Request $request): array
    {
        $qtyLabel = $production->qty !== null
            ? SpkQtyUnit::label((int) $production->qty, $production->satuan)
            : '-';
        $production->loadMissing(['sku', 'categoryPrefix']);
        $itemName = $this->itemTypeLabel($production);
        $productItemName = $this->productItemLabel($production);
        $typeVariant = $this->joinTypeVariantLabel($itemName, $productItemName);
        $typeCode = trim((string) ($production->categoryPrefix?->prefix ?? ''));
        $productItemDisplayName = trim((string) ($production->sku?->item_original ?? ''));
        $skuCode = trim((string) ($production->sku?->sku_code ?? ''));
        $ukuran = $this->ukuranFromProduction($production);

        $stones = $production->stones()
            ->notDeleted()
            ->with(['shape', 'position'])
            ->orderBy('line_id')
            ->get()
            ->map(function (SpkStone $stone): array {
                $pcs = (int) ($stone->pcs ?? 0);
                $totalCarat = (float) ($stone->carat ?? 0);
                $caratPerPcs = $pcs > 0 ? round($totalCarat / $pcs, 3) : 0;

                return [
                    'positionName' => $this->printText($stone->position?->nama ?? null),
                    'shapeName' => $this->shapeDisplayName($stone->shape),
                    'size' => $this->printSize($stone->size ?? null),
                    'caratPerPcs' => $this->printDecimal3($caratPerPcs),
                    'pcs' => $this->printText($pcs),
                    'totalCarat' => $this->printDecimal3(
                        number_format($totalCarat, 3, '.', ''),
                    ),
                ];
            })
            ->values()
            ->all();

        return $this->normalizePrintDocument([
            'info' => [
                'spkNo' => $production->spk_no,
                'spkType' => $production->spk_type,
                'requestOrderNo' => $production->request_order_no,
                'requestOrderCreatedDate' => $this->requestOrderCreatedDate($production, 'd/m/Y'),
                'refSpkNo' => null,
                'customerName' => $production->customer_name,
                'orderDate' => $production->order_date?->format('d/m/Y'),
                'receivedByProductionDate' => app(SpkApprovalService::class)
                    ->managerApprovedAt($production, 'd/m/Y'),
                'workEstimated' => $production->estimated_delivery_time?->format('d/m/Y'),
                'estimatedDelivery' => $production->estimated_delivery_time?->format('d/m/Y'),
                'priority' => $production->priority,
                'statusOrder' => $this->formatStatusOrder($production->status_order),
                'itemType' => $itemName !== '' ? $itemName : null,
                'itemVariance' => $productItemName !== '' ? $productItemName : null,
                'qty' => $qtyLabel,
            ],
            'item' => [
                'typeVariant' => $typeVariant,
                'typeCode' => $typeCode !== '' ? $typeCode : null,
                'productItemName' => $productItemDisplayName !== '' ? $productItemDisplayName : null,
                'skuCode' => $skuCode !== '' ? $skuCode : null,
                'statusOrderLabel' => $this->statusOrder->displayLabel(
                    $production->sku_id,
                    $production->row_id,
                ),
                'qty' => $qtyLabel,
                'diameter' => $ukuran['diameter'] !== '-' ? $ukuran['diameter'] : null,
                'dimensi' => $ukuran['dimensi'] !== '-' ? $ukuran['dimensi'] : null,
                'ringSize' => $ukuran['ringSize'] !== '-' ? $ukuran['ringSize'] : null,
                'goldWeight' => $production->gold_weight !== null
                    ? number_format((float) $production->gold_weight, 2, '.', '')
                    : null,
                'goldColor' => $production->gold_color,
                'jwcad3d' => $production->jwcad_3d,
                'description' => $this->itemDescriptionForPrint($production),
                'imageUrl' => $this->itemImageUrl($production) ?? '',
            ],
            'stones' => $stones,
            'notes' => $production->notes,
            // URL dinamis detail SPK tetap dihitung; resolvePrintQrUrl bisa override via config.
            'detailUrl' => $this->resolvePrintQrUrl(
                route('spk.show', $production, absolute: true),
            ),
            'approval' => app(SpkApprovalService::class)->footerColumns($production),
        ], $request);
    }

    /**
     * URL untuk QR code cetak SPK.
     *
     * Jika config spk.print_qr_url terisi, pakai override sementara.
     * Jika dikosongkan, pakai $dynamicUrl (route detail SPK).
     */
    private function resolvePrintQrUrl(?string $dynamicUrl = null): string
    {
        $override = config('spk.print_qr_url');

        if (filled($override)) {
            return (string) $override;
        }

        return filled($dynamicUrl) ? (string) $dynamicUrl : '';
    }

    private function itemImageUrl(Production $production): ?string
    {
        return SpkItemImageUrl::fromFileName($production->file_name);
    }

    private function itemDescriptionForPrint(Production $production): ?string
    {
        if (filled($production->description)) {
            return trim((string) $production->description);
        }

        $production->loadMissing([
            'sku.categoryPrefix',
            'sku.namePrefix',
            'sku.sizePrefix',
            'sku.stoneShapePrefix',
            'sku.stoneTypePrefix',
            'sku.diamondTypePrefix',
            'sku.goldColorPrefix',
        ]);

        if ($production->sku === null) {
            return null;
        }

        $extracted = trim($this->descriptionExtractor->extract($production->sku));

        return $extracted !== '' ? $extracted : null;
    }

    private function absolutePrintImageUrl(mixed $url, Request $request): string
    {
        if (! filled($url)) {
            return '';
        }

        $imageUrl = trim((string) $url);

        if ($imageUrl === '') {
            return '';
        }

        if (
            str_starts_with($imageUrl, 'http://')
            || str_starts_with($imageUrl, 'https://')
            || str_starts_with($imageUrl, 'data:')
            || str_starts_with($imageUrl, 'blob:')
        ) {
            return $imageUrl;
        }

        if (str_starts_with($imageUrl, '//')) {
            return $request->getScheme().':'.$imageUrl;
        }

        if (str_starts_with($imageUrl, '/')) {
            return $request->getSchemeAndHttpHost().$imageUrl;
        }

        return $request->getSchemeAndHttpHost().'/'.ltrim($imageUrl, '/');
    }

    private function publicStorageUrl(?string $path): ?string
    {
        if (! filled($path)) {
            return null;
        }

        return '/storage/'.ltrim(str_replace('\\', '/', $path), '/');
    }

    private function printText(mixed $value, string $empty = '-'): string
    {
        if ($value === null) {
            return $empty;
        }

        $text = trim((string) $value);

        return $text !== '' ? $text : $empty;
    }

    private function printDecimal3(mixed $value, string $empty = '-'): string
    {
        $text = $this->printText($value, $empty);

        if ($text === $empty) {
            return $empty;
        }

        $normalized = str_replace(',', '.', $text);

        if (! is_numeric($normalized)) {
            return $text;
        }

        return number_format((float) $normalized, 3, '.', '');
    }

    private function printSize(mixed $value, string $empty = '-'): string
    {
        $text = $this->printText($value, $empty);

        if ($text === $empty) {
            return $empty;
        }

        $normalized = str_replace(',', '.', $text);

        if (! is_numeric($normalized)) {
            return $text;
        }

        return number_format((float) $normalized, 2, '.', '');
    }

    private function actorName(Request $request): string
    {
        return $request->user()?->name ?? 'system';
    }
}
