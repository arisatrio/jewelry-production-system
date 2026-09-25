<?php

namespace App\Http\Controllers;

use App\Http\Requests\BulkUpdateJewelCadStatusRequest;
use App\Http\Requests\StoreJewelCadRequestRequest;
use App\Http\Requests\SyncJewelCadSpkRequest;
use App\Http\Requests\UpdateJewelCadRequestRequest;
use App\Models\Employee;
use App\Models\JewelCadRequest;
use App\Models\JewelCadRequestDetail;
use App\Models\MsPosition;
use App\Models\MsShape;
use App\Models\Production;
use App\Models\SpkStone;
use App\Support\GoldColorOptions;
use App\Support\JewelCadApprovalService;
use App\Support\JewelCadDocNumberGenerator;
use App\Support\JewelCadSpkEligibility;
use App\Support\JewelCadStatusMapper;
use App\Support\ProductionOrderTypeLabel;
use App\Support\SkuMasterDiamondMapper;
use App\Support\SpkApprovalRoles;
use App\Support\SpkQtyUnit;
use App\Support\SpkService;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class JewelCadRequestController extends Controller
{
    /**
     * Display a listing of JewelCAD requests.
     */
    public function index(Request $request, JewelCadSpkEligibility $spkEligibility): Response
    {
        $search = $request->string('search')->trim()->toString();
        $sort = $this->resolveIndexSort($request->string('sort')->toString());
        $direction = $this->resolveIndexDirection($request->string('direction')->toString());
        $statusFilters = $this->resolveStatusFilters($request->input('status'));
        $dateFrom = $this->resolveIndexDate($request->string('date_from')->toString());
        $dateTo = $this->resolveIndexDate($request->string('date_to')->toString());
        $operator = $this->resolveOperatorFilter($request->string('operator')->toString());
        $perPage = $this->resolveIndexPerPage($request->integer('per_page', 50));

        $rows = JewelCadRequestDetail::query()
            ->notDeleted()
            ->whereHas('request', function ($query) use (
                $statusFilters,
                $dateFrom,
                $dateTo,
                $operator,
            ): void {
                $query->notDeleted()
                    ->when($statusFilters !== [], function ($statusScope) use ($statusFilters): void {
                        $statusCodes = collect($statusFilters)
                            ->flatMap(fn (string $statusKey): array => $this->statusFilterCodes()[$statusKey] ?? [])
                            ->unique()
                            ->values()
                            ->all();
                        $includeDraftUnset = in_array('draft', $statusFilters, true);

                        if ($statusCodes === [] && ! $includeDraftUnset) {
                            return;
                        }

                        $statusScope->where(function ($statusQuery) use ($statusCodes, $includeDraftUnset): void {
                            if ($statusCodes !== []) {
                                $statusQuery->whereIn('status', $statusCodes);
                            }

                            if ($includeDraftUnset) {
                                $statusQuery->orWhereNull('status')
                                    ->orWhere('status', '')
                                    ->orWhereRaw("UPPER(TRIM(status)) IN ('DRAFT', 'OPEN', '-')");
                            }
                        });
                    })
                    ->when($dateFrom !== null, function ($dateScope) use ($dateFrom): void {
                        $dateScope->whereDate('trans_date', '>=', $dateFrom);
                    })
                    ->when($dateTo !== null, function ($dateScope) use ($dateTo): void {
                        $dateScope->whereDate('trans_date', '<=', $dateTo);
                    })
                    ->when($operator !== null, function ($operatorScope) use ($operator): void {
                        $operatorScope->where('operator', $operator);
                    });
            })
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($innerQuery) use ($search): void {
                    $innerQuery->where('material', 'like', "%{$search}%")
                        ->orWhere('notes', 'like', "%{$search}%")
                        ->orWhereHas('production', function ($productionQuery) use ($search): void {
                            $productionQuery->notDeleted()
                                ->where(function ($productionInner) use ($search): void {
                                    $productionInner->where('spk_no', 'like', "%{$search}%")
                                        ->orWhere('item_name', 'like', "%{$search}%")
                                        ->orWhere('customer_name', 'like', "%{$search}%");
                                });
                        })
                        ->orWhereHas('request', function ($requestQuery) use ($search): void {
                            $requestQuery->notDeleted()
                                ->where(function ($requestInner) use ($search): void {
                                    $requestInner->where('doc_no', 'like', "%{$search}%")
                                        ->orWhere('notes', 'like', "%{$search}%")
                                        ->orWhere('operator', 'like', "%{$search}%")
                                        ->orWhere('status', 'like', "%{$search}%");
                                });
                        });
                });
            })
            ->with([
                'request',
                'production' => fn ($productionQuery) => $productionQuery
                    ->notDeleted()
                    ->select([
                        'row_id',
                        'spk_no',
                        'item_name',
                        'customer_name',
                        'gold_weight',
                        'qty',
                        'satuan',
                        'sku_id',
                        'category_prefix_id',
                        'description',
                        'jwcad_3d',
                    ])
                    ->with([
                        'sku' => fn ($skuQuery) => $skuQuery
                            ->select(['id', 'sku_code', 'item_original']),
                        'categoryPrefix' => fn ($prefixQuery) => $prefixQuery
                            ->select(['id', 'prefix', 'category']),
                    ]),
            ])
            ->tap(fn ($query) => $this->applyIndexSort($query, $sort, $direction))
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (JewelCadRequestDetail $detail): array => $this->toListItem($detail));

        return Inertia::render('jewelcad/index', [
            'requests' => $rows,
            'spkStatusCounts' => $this->spkStatusCounts($spkEligibility),
            'filters' => [
                'search' => $search,
                'sort' => $sort,
                'direction' => $direction,
                'status' => $statusFilters,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'operator' => $operator,
                'per_page' => $perPage,
            ],
            'filterOptions' => [
                'status' => [
                    ['value' => 'draft', 'label' => 'Draft'],
                    ['value' => 'submitted', 'label' => 'Pengajuan Approval'],
                    ['value' => 'manager', 'label' => 'Serahkan ke JWCAD'],
                    ['value' => 'done', 'label' => 'Done'],
                ],
                'operator' => $this->indexOperatorOptions(),
                'per_page' => [
                    ['value' => '10', 'label' => '10'],
                    ['value' => '25', 'label' => '25'],
                    ['value' => '50', 'label' => '50'],
                    ['value' => '100', 'label' => '100'],
                ],
                'sort' => [
                    ['value' => 'id', 'label' => 'ID'],
                    ['value' => 'date', 'label' => 'Tanggal'],
                    ['value' => 'spk', 'label' => 'No SPK'],
                    ['value' => 'operator', 'label' => 'Operator'],
                ],
                'direction' => [
                    ['value' => 'asc', 'label' => 'A–Z'],
                    ['value' => 'desc', 'label' => 'Z–A'],
                ],
            ],
            'bulkActions' => [
                'canSubmit' => SpkApprovalRoles::canEditDraft($request->user()),
                'canManagerApprove' => SpkApprovalRoles::canManagerApprove($request->user()),
                'canComplete' => SpkApprovalRoles::canEditDraft($request->user()),
                'canDelete' => SpkApprovalRoles::canEditDraft($request->user()),
            ],
        ]);
    }

    /**
     * Show the form for creating a new request.
     */
    public function create(Request $request, JewelCadApprovalService $approvalService): Response
    {
        return Inertia::render('jewelcad/create', [
            'formDocumentNo' => (string) config('spk.jewelcad_form_document_no'),
            'operatorOptions' => $this->operatorOptions(),
            'approvalFooter' => $approvalService->createFooterColumns($this->actorName($request)),
            'form' => [
                'operator' => $this->defaultOperatorName($request),
                'transDate' => now()->format('Y-m-d'),
                'notes' => '',
                'details' => [],
            ],
        ]);
    }

    /**
     * Search SPKs for the JewelCAD form selector.
     */
    public function searchSpks(Request $request): JsonResponse
    {
        $search = $request->string('search')->trim()->toString();
        $limit = max(1, min($request->integer('limit', 25), 50));
        $excludeInput = $request->input('exclude', []);
        $exclude = collect(is_array($excludeInput) ? $excludeInput : explode(',', (string) $excludeInput))
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->values()
            ->all();

        $query = Production::query()
            ->notDeleted()
            ->when($exclude !== [], fn ($builder) => $builder->whereNotIn('row_id', $exclude))
            ->orderByDesc('row_id')
            ->limit($limit);

        $queue = $request->string('queue')->trim()->toString();

        match ($queue) {
            'inProgress' => $query->tap(
                fn (Builder $builder) => app(JewelCadSpkEligibility::class)->applyInProgressScope($builder),
            ),
            'completed' => $query->tap(
                fn (Builder $builder) => app(JewelCadSpkEligibility::class)->applyCompletedScope($builder),
            ),
            default => $query->tap(
                fn (Builder $builder) => app(JewelCadSpkEligibility::class)->applyEligibleScope($builder),
            ),
        };

        $query->select([
            'row_id',
            'spk_no',
            'item_name',
            'customer_name',
            'gold_color',
            'gold_weight',
            'qty',
            'notes',
        ]);

        if ($search !== '') {
            $like = '%'.$search.'%';

            $query->where(function ($innerQuery) use ($like): void {
                $innerQuery->where('spk_no', 'like', $like)
                    ->orWhere('item_name', 'like', $like)
                    ->orWhere('customer_name', 'like', $like)
                    ->orWhere('gold_color', 'like', $like)
                    ->orWhere('notes', 'like', $like);
            });
        }

        $productions = $query->get();
        $spkEligibility = app(JewelCadSpkEligibility::class);
        $requestRefs = in_array($queue, ['inProgress', 'completed'], true)
            ? $spkEligibility->requestRefsBySpkIds(
                $productions->pluck('row_id')->map(fn (mixed $id): int => (int) $id)->all(),
                completed: $queue === 'completed',
            )
            : [];

        return response()->json([
            'status' => true,
            'data' => $productions->map(function (Production $production) use ($requestRefs): array {
                $spkId = (int) $production->row_id;
                $requestRef = $requestRefs[$spkId] ?? null;

                return [
                    'rowId' => $spkId,
                    'spkNo' => (string) $production->spk_no,
                    'requestId' => $requestRef['requestId'] ?? null,
                    'docNo' => $requestRef['docNo'] ?? null,
                    'customer' => filled($production->customer_name)
                        ? (string) $production->customer_name
                        : '—',
                    'item' => filled($production->item_name)
                        ? (string) $production->item_name
                        : '—',
                    'goldColor' => filled($production->gold_color)
                        ? (string) $production->gold_color
                        : '',
                    'goldWeight' => $production->gold_weight !== null
                        ? number_format((float) $production->gold_weight, 2, '.', '')
                        : '',
                    'qty' => $production->qty ?? 1,
                    'notes' => filled($production->notes)
                        ? (string) $production->notes
                        : '',
                ];
            })->values()->all(),
        ]);
    }

    /**
     * Load SPK detail for the JewelCAD add-SPK modal.
     */
    public function spkDetail(int $rowId, SkuMasterDiamondMapper $diamondMapper): JsonResponse
    {
        $production = Production::query()
            ->notDeleted()
            ->where('row_id', $rowId)
            ->whereNotNull('spk_no')
            ->firstOrFail();

        if (! app(JewelCadSpkEligibility::class)->isEligible($production)) {
            return response()->json([
                'status' => false,
                'message' => 'SPK harus sudah di-approve Manager Produksi dan belum masuk proses produksi.',
            ], 422);
        }

        $production->loadMissing([
            'sku.diamonds' => fn ($query) => $query->notDeleted()->orderBy('line_id'),
            'categoryPrefix',
        ]);

        $skuStones = $production->sku !== null
            ? $diamondMapper->toFormStones($production->sku->diamonds)
            : [];

        $stones = $production->stones()
            ->notDeleted()
            ->with(['shape', 'position'])
            ->orderBy('line_id')
            ->get()
            ->values()
            ->map(function (SpkStone $stone, int $index) use ($skuStones): array {
                $pcs = (int) ($stone->pcs ?? 0);
                $totalCarat = (float) ($stone->carat ?? 0);
                $positionNama = $stone->position?->nama ?? '';
                $shapeName = filled($stone->shape?->name)
                    ? (string) $stone->shape->name
                    : (string) ($stone->shape?->code ?? '');
                $size = filled($stone->size) ? (string) $stone->size : '';
                $pcsValue = $pcs > 0 ? (string) $pcs : '';
                $caratPerPcs = $pcs > 0
                    ? number_format(round($totalCarat / $pcs, 3), 3, '.', '')
                    : '';
                $shapeId = $stone->shape_id !== null
                    ? (string) $stone->shape_id
                    : '';
                $master = $skuStones[$index] ?? null;

                // Tanpa diamond Master SKU: pakai nilai SPK saat load sebagai baseline
                // agar hint "*Diubah dari Master SKU" tetap muncul saat user mengubah field.
                if ($master === null) {
                    $master = [
                        'positionNama' => $positionNama,
                        'shapeId' => $shapeId,
                        'shapeName' => $shapeName,
                        'size' => $size,
                        'pcs' => $pcsValue,
                        'caratPerPcs' => $caratPerPcs,
                    ];
                }

                return [
                    'id' => (string) $stone->line_id,
                    'positionId' => $stone->position_id !== null
                        ? (string) $stone->position_id
                        : '',
                    'positionName' => $positionNama,
                    'positionNama' => $positionNama,
                    'shapeId' => $shapeId,
                    'shapeName' => $shapeName,
                    'size' => $size,
                    'pcs' => $pcsValue,
                    'caratPerPcs' => $caratPerPcs,
                    'master' => [
                        'positionNama' => filled($master['positionNama'] ?? null)
                            ? (string) $master['positionNama']
                            : '',
                        'positionName' => filled($master['positionNama'] ?? null)
                            ? (string) $master['positionNama']
                            : '',
                        'shapeId' => filled($master['shapeId'] ?? null)
                            ? (string) $master['shapeId']
                            : '',
                        'shapeName' => filled($master['shapeName'] ?? null)
                            ? (string) $master['shapeName']
                            : '',
                        'size' => filled($master['size'] ?? null)
                            ? (string) $master['size']
                            : '',
                        'pcs' => filled($master['pcs'] ?? null)
                            ? (string) $master['pcs']
                            : '',
                        'caratPerPcs' => filled($master['caratPerPcs'] ?? null)
                            ? (string) $master['caratPerPcs']
                            : '',
                    ],
                ];
            })
            ->all();

        $ukuran = app(SpkService::class)->parseUkuranLabel($production->diameter_length_ringsize);
        $imageBase = rtrim((string) config('spk.production_image_base_url'), '/');
        $imageUrl = filled($production->file_name)
            ? $imageBase.'/'.ltrim((string) $production->file_name, '/')
            : null;

        $typeCode = trim((string) ($production->categoryPrefix?->prefix ?? ''));
        $productItemName = trim((string) ($production->sku?->item_original ?? ''));
        $skuCode = trim((string) ($production->sku?->sku_code ?? ''));

        $skuMasterGoldWeight = $production->sku?->gold_weight !== null
            && (float) $production->sku->gold_weight > 0
            ? number_format((float) $production->sku->gold_weight, 2, '.', '')
            : null;
        $productionGoldWeight = $production->gold_weight !== null
            && (float) $production->gold_weight > 0
            ? number_format((float) $production->gold_weight, 2, '.', '')
            : null;

        $skuDiamondCount = $production->sku !== null
            ? $production->sku->diamonds->count()
            : 0;

        return response()->json([
            'status' => true,
            'data' => [
                'production' => [
                    'id' => (int) $production->row_id,
                    'spkNo' => (string) $production->spk_no,
                    'spkType' => filled($production->spk_type) ? (string) $production->spk_type : '-',
                    'orderTypeLabel' => app(ProductionOrderTypeLabel::class)->forProduction($production),
                    'customer' => filled($production->customer_name)
                        ? (string) $production->customer_name
                        : '-',
                    'itemName' => filled($production->item_name)
                        ? (string) $production->item_name
                        : '-',
                    'requestOrderNo' => filled($production->request_order_no)
                        ? (string) $production->request_order_no
                        : '-',
                    'orderDate' => $production->order_date?->format('d/m/Y') ?? '-',
                    'estimatedDelivery' => $production->estimated_delivery_time?->format('d/m/Y') ?? '-',
                    'qty' => $production->qty ?? 1,
                    'notes' => filled($production->notes) ? (string) $production->notes : '',
                    'goldWeight' => $productionGoldWeight ?? '',
                    'goldColor' => filled($production->gold_color)
                        ? (string) $production->gold_color
                        : '',
                ],
                'item' => [
                    'typeCode' => $typeCode !== '' ? $typeCode : '-',
                    'productItemName' => $productItemName !== '' ? $productItemName : '-',
                    'skuCode' => $skuCode !== '' ? $skuCode : '-',
                    'qty' => $production->qty !== null
                        ? SpkQtyUnit::label((int) $production->qty, $production->satuan)
                        : '-',
                    'diameter' => filled($ukuran['diameter'] ?? null) ? (string) $ukuran['diameter'] : '-',
                    'dimensi' => filled($ukuran['dimensi'] ?? null) ? (string) $ukuran['dimensi'] : '-',
                    'ringSize' => filled($ukuran['ring_size'] ?? null) ? (string) $ukuran['ring_size'] : '-',
                    // Prefer gold Master SKU; fallback ke berat SPK agar hint tetap bisa muncul.
                    'masterGoldWeight' => $skuMasterGoldWeight ?? $productionGoldWeight,
                    'jwcad3d' => filled($production->jwcad_3d) ? (string) $production->jwcad_3d : '',
                    'description' => filled($production->description)
                        ? (string) $production->description
                        : '-',
                    'imageUrl' => $imageUrl,
                    'fileName' => filled($production->file_name)
                        ? (string) $production->file_name
                        : null,
                ],
                'masterStoneCount' => $skuDiamondCount > 0
                    ? $skuDiamondCount
                    : count($stones),
                'stones' => $stones,
                'options' => [
                    'goldColors' => GoldColorOptions::all(),
                    'shapeOptions' => MsShape::query()
                        ->notDeleted()
                        ->orderBy('name')
                        ->get(['row_id', 'name', 'code'])
                        ->map(fn (MsShape $shape): array => [
                            'value' => (string) $shape->row_id,
                            'label' => trim(implode(' - ', array_filter([
                                $shape->name,
                                $shape->code,
                            ], fn ($value): bool => filled($value)))) ?: 'Shape #'.$shape->row_id,
                            'name' => filled($shape->name)
                                ? (string) $shape->name
                                : (string) ($shape->code ?? ''),
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
                ],
            ],
        ]);
    }

    /**
     * Persist gold + stones to master SPK when JewelCAD request is saved.
     * Kept for API compatibility; prefer syncing via store/update.
     */
    public function syncSpk(
        SyncJewelCadSpkRequest $request,
        int $rowId,
        SpkService $spkService,
    ): JsonResponse {
        $production = Production::query()
            ->notDeleted()
            ->where('row_id', $rowId)
            ->whereNotNull('spk_no')
            ->firstOrFail();

        $validated = $request->validated();
        $actor = $this->actorName($request);

        $production = $spkService->updateGoldAndStones($production, [
            'gold_weight' => $validated['gold_weight'],
            'gold_color' => $validated['gold_color'],
            'jwcad_3d' => $validated['jwcad_3d'] ?? null,
            'stones' => $validated['stones'] ?? [],
        ], $actor, $request->file('file'));

        return response()->json([
            'status' => true,
            'message' => "SPK {$production->spk_no} berhasil diperbarui.",
            'data' => [
                'spkId' => (int) $production->row_id,
                'spkNo' => (string) $production->spk_no,
                'material' => (string) $production->gold_color,
                'goldWeight' => $production->gold_weight !== null
                    ? number_format((float) $production->gold_weight, 2, '.', '')
                    : '',
                'qty' => $production->qty ?? 1,
                'notes' => filled($production->notes) ? (string) $production->notes : '',
                'estimationBrj' => number_format((float) $validated['estimation_brj'], 3, '.', ''),
            ],
        ]);
    }

    /**
     * Store a newly created request in storage.
     */
    public function store(
        StoreJewelCadRequestRequest $request,
        SpkService $spkService,
        JewelCadDocNumberGenerator $docNumberGenerator,
        JewelCadSpkEligibility $spkEligibility,
    ): RedirectResponse {
        $validated = $request->validated();
        $actor = $this->actorName($request);

        $jewelCadRequest = DB::connection('third')->transaction(function () use ($validated, $actor, $spkService, $request, $docNumberGenerator, $spkEligibility): JewelCadRequest {
            $jewelCadRequest = JewelCadRequest::query()->create([
                'doc_no' => $docNumberGenerator->generate(
                    Carbon::parse($validated['trans_date']),
                ),
                'operator' => $validated['operator'],
                'trans_date' => $validated['trans_date'],
                'notes' => $validated['notes'] ?? null,
                'status' => 'DRAFT',
                'is_deleted' => 0,
                'created_date' => now(),
                'created_by' => $actor,
                'modified_date' => now(),
                'modified_by' => $actor,
            ]);

            $this->storeDetails($jewelCadRequest, $validated['details'], $actor, $spkService, $request, $spkEligibility);

            return $jewelCadRequest;
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Request JewelCAD berhasil ditambahkan.',
        ]);

        return to_route('jewelcad.show', $jewelCadRequest);
    }

    /**
     * Display the specified resource.
     */
    public function show(
        Request $request,
        JewelCadRequest $jewelcad,
        JewelCadApprovalService $approvalService,
        JewelCadStatusMapper $statusMapper,
    ): Response {
        abort_if($jewelcad->is_deleted === 1, 404);

        $jewelcad->load($this->jewelCadDetailEagerLoads());

        return Inertia::render('jewelcad/show', [
            'formDocumentNo' => (string) config('spk.jewelcad_form_document_no'),
            'approvalFooter' => $approvalService->footerColumns(
                $jewelcad,
                $this->actorName($request),
            ),
            'approvalHistory' => $approvalService->history($jewelcad),
            'approval' => $approvalService->abilitiesFor($jewelcad, $request->user()),
            'workflowStatus' => $statusMapper->map($jewelcad),
            'requestItem' => $this->toDetailItem($jewelcad),
        ]);
    }

    /**
     * Kirim request Draft ke Manager Produksi (JWD010).
     */
    public function submit(
        Request $request,
        JewelCadRequest $jewelcad,
        JewelCadApprovalService $approvalService,
    ): RedirectResponse {
        abort_if($jewelcad->is_deleted === 1, 404);

        if (! $approvalService->abilitiesFor($jewelcad, $request->user())['canSubmit']) {
            abort(403, 'Request ini tidak dapat dikirim ke Manager Produksi.');
        }

        try {
            $approvalService->submit($jewelcad, $this->actorName($request));
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['approval' => $exception->getMessage()]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Request JewelCAD dikirim ke Manager Produksi.',
        ]);

        return to_route('jewelcad.show', $jewelcad);
    }

    /**
     * Manager Produksi meng-approve request (JWD010 → JWD020).
     */
    public function managerApprove(
        Request $request,
        JewelCadRequest $jewelcad,
        JewelCadApprovalService $approvalService,
    ): RedirectResponse {
        abort_if($jewelcad->is_deleted === 1, 404);

        if (! $approvalService->abilitiesFor($jewelcad, $request->user())['canManagerApprove']) {
            abort(403, 'Request ini tidak dapat di-approve oleh Manager Produksi.');
        }

        try {
            $approvalService->managerApprove($jewelcad, $this->actorName($request));
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['approval' => $exception->getMessage()]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Request JewelCAD di-approve oleh Manager Produksi.',
        ]);

        return to_route('jewelcad.show', $jewelcad);
    }

    /**
     * Menyelesaikan request (JWD020 → JWDDONE).
     */
    public function complete(
        Request $request,
        JewelCadRequest $jewelcad,
        JewelCadApprovalService $approvalService,
    ): RedirectResponse {
        abort_if($jewelcad->is_deleted === 1, 404);

        if (! $approvalService->abilitiesFor($jewelcad, $request->user())['canComplete']) {
            abort(403, 'Request ini tidak dapat diselesaikan.');
        }

        try {
            $approvalService->complete($jewelcad, $this->actorName($request));
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['approval' => $exception->getMessage()]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Request JewelCAD diselesaikan.',
        ]);

        return to_route('jewelcad.show', $jewelcad);
    }

    /**
     * Show the form for editing the specified request.
     */
    public function edit(
        Request $request,
        JewelCadRequest $jewelcad,
        JewelCadApprovalService $approvalService,
    ): Response {
        abort_if($jewelcad->is_deleted === 1, 404);

        $jewelcad->load($this->jewelCadDetailEagerLoads());

        return Inertia::render('jewelcad/edit', [
            'formDocumentNo' => (string) config('spk.jewelcad_form_document_no'),
            'operatorOptions' => $this->operatorOptions(),
            'approvalFooter' => $approvalService->footerColumns(
                $jewelcad,
                $this->actorName($request),
            ),
            'approval' => $approvalService->abilitiesFor($jewelcad, $request->user()),
            'requestItem' => [
                ...$this->toFormItem($jewelcad),
                'operator' => filled($jewelcad->operator)
                    ? (string) $jewelcad->operator
                    : $this->defaultOperatorName($request),
            ],
        ]);
    }

    /**
     * Update the specified request in storage.
     */
    public function update(
        UpdateJewelCadRequestRequest $request,
        JewelCadRequest $jewelcad,
        SpkService $spkService,
        JewelCadSpkEligibility $spkEligibility,
    ): RedirectResponse {
        abort_if($jewelcad->is_deleted === 1, 404);

        $validated = $request->validated();
        $actor = $this->actorName($request);

        DB::connection('third')->transaction(function () use ($jewelcad, $validated, $actor, $spkService, $request, $spkEligibility): void {
            $jewelcad->update([
                'doc_no' => $validated['doc_no'],
                'operator' => $validated['operator'],
                'trans_date' => $validated['trans_date'],
                'notes' => $validated['notes'] ?? null,
                'modified_date' => now(),
                'modified_by' => $actor,
            ]);

            $jewelcad->details()
                ->notDeleted()
                ->update([
                    'is_deleted' => 1,
                    'deleted_date' => now(),
                    'deleted_by' => $actor,
                    'modified_date' => now(),
                    'modified_by' => $actor,
                ]);

            $this->storeDetails($jewelcad, $validated['details'], $actor, $spkService, $request, $spkEligibility);
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Request JewelCAD berhasil diperbarui.',
        ]);

        return to_route('jewelcad.show', $jewelcad);
    }

    /**
     * Bulk update / soft-delete selected JewelCAD requests.
     */
    public function bulkUpdateStatus(
        BulkUpdateJewelCadStatusRequest $request,
        JewelCadApprovalService $approvalService,
    ): RedirectResponse {
        $validated = $request->validated();
        /** @var list<int> $ids */
        $ids = array_values(array_unique(array_map(intval(...), $validated['ids'])));
        /** @var 'submit'|'manager_approve'|'complete'|'delete' $action */
        $action = $validated['action'];
        $actor = $this->actorName($request);
        $user = $request->user();

        $documents = JewelCadRequest::query()
            ->notDeleted()
            ->whereIn('row_id', $ids)
            ->get()
            ->keyBy('row_id');

        $updated = 0;
        $skipped = 0;

        foreach ($ids as $id) {
            $document = $documents->get($id);

            if ($document === null) {
                $skipped++;

                continue;
            }

            $abilities = $approvalService->abilitiesFor($document, $user);
            $allowed = match ($action) {
                'submit' => $abilities['canSubmit'],
                'manager_approve' => $abilities['canManagerApprove'],
                'complete' => $abilities['canComplete'],
                'delete' => $abilities['canDelete'],
            };

            if (! $allowed) {
                $skipped++;

                continue;
            }

            try {
                match ($action) {
                    'submit' => $approvalService->submit($document, $actor),
                    'manager_approve' => $approvalService->managerApprove($document, $actor),
                    'complete' => $approvalService->complete($document, $actor),
                    'delete' => $this->softDeleteJewelCad($document, $actor),
                };
                $updated++;
            } catch (InvalidArgumentException) {
                $skipped++;
            }
        }

        $actionLabel = match ($action) {
            'submit' => 'Kirim ke Manager Produksi',
            'manager_approve' => 'Approve',
            'complete' => 'Selesai',
            'delete' => 'Hapus',
        };

        $verb = $action === 'delete' ? 'dihapus' : 'diperbarui';

        $message = $updated > 0
            ? "{$updated} request berhasil {$verb} ({$actionLabel})."
            : "Tidak ada request yang dapat {$verb} ({$actionLabel}).";

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
     * Remove the specified request from storage.
     */
    public function destroy(
        Request $request,
        JewelCadRequest $jewelcad,
        JewelCadApprovalService $approvalService,
    ): RedirectResponse {
        abort_if($jewelcad->is_deleted === 1, 404);

        if (! $approvalService->abilitiesFor($jewelcad, $request->user())['canDelete']) {
            abort(403, 'Request ini tidak dapat dihapus.');
        }

        $this->softDeleteJewelCad($jewelcad, $this->actorName($request));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Request JewelCAD berhasil dihapus.',
        ]);

        return to_route('jewelcad.index');
    }

    private function softDeleteJewelCad(JewelCadRequest $jewelcad, string $actor): void
    {
        DB::connection('third')->transaction(function () use ($jewelcad, $actor): void {
            $jewelcad->update([
                'is_deleted' => 1,
                'deleted_date' => now(),
                'deleted_by' => $actor,
                'modified_date' => now(),
                'modified_by' => $actor,
            ]);

            $jewelcad->details()
                ->notDeleted()
                ->update([
                    'is_deleted' => 1,
                    'deleted_date' => now(),
                    'deleted_by' => $actor,
                    'modified_date' => now(),
                    'modified_by' => $actor,
                ]);
        });
    }

    /**
     * @param  list<array{
     *     spk_id: int,
     *     material?: string|null,
     *     gold_weight?: string|null,
     *     jwcad_3d?: string|null,
     *     qty?: int|null,
     *     estimation_brj: string,
     *     notes?: string|null,
     *     stones?: list<array<string, mixed>>
     * }>  $details
     */
    private function storeDetails(
        JewelCadRequest $request,
        array $details,
        string $actor,
        SpkService $spkService,
        Request $httpRequest,
        JewelCadSpkEligibility $spkEligibility,
    ): void {
        foreach ($details as $index => $detail) {
            $production = Production::query()
                ->notDeleted()
                ->where('row_id', $detail['spk_id'])
                ->first();

            if ($production === null) {
                continue;
            }

            $spkEligibility->markProcessStarted($production, $actor);

            if (
                array_key_exists('stones', $detail)
                && filled($detail['gold_weight'] ?? null)
                && filled($detail['material'] ?? null)
            ) {
                $file = $httpRequest->file("details.{$index}.file");

                $production = $spkService->updateGoldAndStones($production, [
                    'gold_weight' => $detail['gold_weight'],
                    'gold_color' => $detail['material'],
                    'jwcad_3d' => $detail['jwcad_3d'] ?? null,
                    'stones' => $detail['stones'] ?? [],
                ], $actor, $file instanceof UploadedFile ? $file : null);
            }

            $request->details()->create([
                'spk_id' => $detail['spk_id'],
                'material' => filled($detail['material'] ?? null)
                    ? (string) $detail['material']
                    : (filled($production?->gold_color) ? (string) $production->gold_color : null),
                'qty' => $detail['qty'] ?? $production?->qty,
                'estimation_brj' => $detail['estimation_brj'],
                'notes' => filled($detail['notes'] ?? null)
                    ? (string) $detail['notes']
                    : (filled($production?->notes) ? (string) $production->notes : null),
                'is_deleted' => 0,
                'created_date' => now(),
                'created_by' => $actor,
                'modified_date' => now(),
                'modified_by' => $actor,
            ]);
        }
    }

    /**
     * @return array{pending: int, inProgress: int, completed: int}
     */
    private function spkStatusCounts(JewelCadSpkEligibility $spkEligibility): array
    {
        return [
            'pending' => Production::query()
                ->tap(fn (Builder $builder) => $spkEligibility->applyEligibleScope($builder))
                ->count(),
            'inProgress' => Production::query()
                ->tap(fn (Builder $builder) => $spkEligibility->applyInProgressScope($builder))
                ->count(),
            'completed' => Production::query()
                ->tap(fn (Builder $builder) => $spkEligibility->applyCompletedScope($builder))
                ->count(),
        ];
    }

    private function resolveIndexSort(string $sort): string
    {
        $allowed = ['id', 'date', 'spk', 'operator'];

        return in_array($sort, $allowed, true) ? $sort : 'id';
    }

    private function resolveIndexDirection(string $direction): string
    {
        return in_array($direction, ['asc', 'desc'], true) ? $direction : 'desc';
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

    private function resolveOperatorFilter(string $operator): ?string
    {
        $trimmed = trim($operator);

        return $trimmed !== '' ? $trimmed : null;
    }

    private function resolveIndexPerPage(int $perPage): int
    {
        return in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 50;
    }

    /**
     * @return list<string>
     */
    private function resolveStatusFilters(mixed $status): array
    {
        $allowed = array_keys($this->statusFilterCodes());

        return collect(is_array($status) ? $status : (filled($status) ? [$status] : []))
            ->map(fn (mixed $value): string => trim((string) $value))
            ->filter(fn (string $value): bool => in_array($value, $allowed, true))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<string, list<string>>
     */
    private function statusFilterCodes(): array
    {
        return [
            'draft' => [],
            'submitted' => [JewelCadApprovalService::STATUS_SUBMITTED],
            'manager' => [JewelCadApprovalService::STATUS_MANAGER],
            'done' => [JewelCadApprovalService::STATUS_DONE],
        ];
    }

    /**
     * @param  Builder<JewelCadRequestDetail>  $query
     */
    private function applyIndexSort(Builder $query, string $sort, string $direction): void
    {
        $ascending = $direction === 'asc';
        $order = $ascending ? 'asc' : 'desc';

        match ($sort) {
            'date' => $query
                ->orderBy(
                    JewelCadRequest::query()
                        ->select('trans_date')
                        ->whereColumn('requestjwcad.row_id', 'requestjwcaddetails.row_id')
                        ->limit(1),
                    $order,
                )
                ->orderBy('line_id', $order),
            'spk' => $query
                ->orderBy(
                    Production::query()
                        ->select('spk_no')
                        ->whereColumn('spk.row_id', 'requestjwcaddetails.spk_id')
                        ->limit(1),
                    $order,
                )
                ->orderBy('line_id', $order),
            'operator' => $query
                ->orderBy(
                    JewelCadRequest::query()
                        ->select('operator')
                        ->whereColumn('requestjwcad.row_id', 'requestjwcaddetails.row_id')
                        ->limit(1),
                    $order,
                )
                ->orderBy('line_id', $order),
            default => $query
                ->orderBy(
                    JewelCadRequest::query()
                        ->select('doc_no')
                        ->whereColumn('requestjwcad.row_id', 'requestjwcaddetails.row_id')
                        ->limit(1),
                    $order,
                )
                ->orderBy('line_id', $order),
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function indexOperatorOptions(): array
    {
        return JewelCadRequest::query()
            ->notDeleted()
            ->whereNotNull('operator')
            ->where('operator', '!=', '')
            ->distinct()
            ->orderBy('operator')
            ->pluck('operator')
            ->map(fn (mixed $operator): array => [
                'value' => (string) $operator,
                'label' => (string) $operator,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{
     *     id: int,
     *     requestId: int,
     *     docNo: string|null,
     *     transDate: string|null,
     *     operator: string|null,
     *     status: string|null,
     *     statusLabel: string|null,
     *     notes: string|null,
     *     material: string|null,
     *     spkNo: string|null,
     *     spkId: int|null,
     *     skuCode: string|null,
     *     typeCode: string|null,
     *     productItemName: string|null,
     *     itemDescription: string|null,
     *     qty: int|null,
     *     qtyLabel: string|null,
     *     jwcad3d: string|null,
     *     goldWeight: string|null,
     *     estimationBrj: string|null
     * }
     */
    private function toListItem(JewelCadRequestDetail $detail): array
    {
        $request = $detail->request;
        $production = $detail->production;
        $qty = $detail->qty ?? $production?->qty;
        $typeCode = trim((string) ($production?->categoryPrefix?->category ?? ''));
        $productItemName = trim((string) ($production?->sku?->item_original ?? ''));

        if ($productItemName === '') {
            $productItemName = trim((string) ($production?->item_name ?? ''));
        }

        $itemDescription = trim((string) ($production?->description ?? ''));

        return [
            'id' => (int) $detail->line_id,
            'requestId' => (int) $detail->row_id,
            'docNo' => $request?->doc_no,
            'transDate' => $request?->trans_date?->format('Y-m-d'),
            'operator' => filled($request?->operator) ? (string) $request->operator : null,
            'status' => $request?->status,
            'statusLabel' => $request !== null
                ? app(JewelCadApprovalService::class)->statusLabelFor($request)
                : null,
            'notes' => filled($request?->notes) ? (string) $request->notes : null,
            'material' => filled($detail->material) ? (string) $detail->material : null,
            'spkNo' => filled($production?->spk_no) ? (string) $production->spk_no : null,
            'spkId' => filled($detail->spk_id) ? (int) $detail->spk_id : null,
            'skuCode' => filled($production?->sku?->sku_code)
                ? (string) $production->sku->sku_code
                : null,
            'typeCode' => $typeCode !== '' ? $typeCode : null,
            'productItemName' => $productItemName !== '' ? $productItemName : null,
            'itemDescription' => $itemDescription !== '' ? $itemDescription : null,
            'qty' => $qty !== null ? (int) $qty : null,
            'qtyLabel' => $qty !== null
                ? SpkQtyUnit::label((int) $qty, $production?->satuan)
                : null,
            'jwcad3d' => filled($production?->jwcad_3d)
                ? (string) $production->jwcad_3d
                : null,
            'goldWeight' => $production?->gold_weight !== null
                ? number_format((float) $production->gold_weight, 2, '.', '')
                : null,
            'estimationBrj' => $detail->estimation_brj !== null
                ? number_format((float) $detail->estimation_brj, 2, '.', '')
                : null,
        ];
    }

    /**
     * @return array{
     *     id: int,
     *     docNo: string|null,
     *     operator: string|null,
     *     transDate: string|null,
     *     notes: string|null,
     *     status: string|null,
     *     createdBy: string|null,
     *     createdAt: string|null,
     *     modifiedBy: string|null,
     *     modifiedAt: string|null,
     *     details: list<array{
     *         spkId: int,
     *         spkNo: string|null,
     *         material: string|null,
     *         goldWeight: string,
     *         skuCode: string|null,
     *         satuan: string,
     *         qty: int|null,
     *         estimationBrj: string,
     *         notes: string|null
     *     }>
     * }
     */
    private function toDetailItem(JewelCadRequest $request): array
    {
        return [
            ...$this->toFormItem($request),
            'createdBy' => $request->created_by,
            'createdAt' => $request->created_date?->format('d/m/Y H:i'),
            'modifiedBy' => $request->modified_by,
            'modifiedAt' => $request->modified_date?->format('d/m/Y H:i'),
        ];
    }

    /**
     * @return array{
     *     id: int,
     *     docNo: string|null,
     *     operator: string|null,
     *     transDate: string|null,
     *     notes: string|null,
     *     status: string|null,
     *     details: list<array{
     *         spkId: int,
     *         spkNo: string|null,
     *         material: string|null,
     *         goldWeight: string,
     *         skuCode: string|null,
     *         typeCode: string|null,
     *         productItemName: string|null,
     *         satuan: string,
     *         qty: int|null,
     *         estimationBrj: string,
     *         notes: string|null
     *     }>
     * }
     */
    private function toFormItem(JewelCadRequest $request): array
    {
        return [
            'id' => (int) $request->row_id,
            'docNo' => $request->doc_no,
            'operator' => $request->operator,
            'transDate' => $request->trans_date?->format('Y-m-d'),
            'notes' => $request->notes,
            'status' => $request->status,
            'details' => $request->details
                ->filter(fn (JewelCadRequestDetail $detail): bool => $detail->is_deleted === 0)
                ->map(fn (JewelCadRequestDetail $detail): array => $this->mapDetailRow($detail))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array{
     *     spkId: int,
     *     spkNo: string|null,
     *     material: string|null,
     *     goldWeight: string,
     *     skuCode: string|null,
     *     typeCode: string|null,
     *     productItemName: string|null,
     *     itemDescription: string|null,
     *     satuan: string,
     *     qty: int|null,
     *     estimationBrj: string,
     *     notes: string|null
     * }
     */
    private function mapDetailRow(JewelCadRequestDetail $detail): array
    {
        $qty = $detail->production?->qty ?? $detail->qty;
        $typeCode = trim((string) ($detail->production?->categoryPrefix?->prefix ?? ''));
        $productItemName = trim((string) ($detail->production?->sku?->item_original ?? ''));
        $itemDescription = trim((string) ($detail->production?->description ?? ''));

        return [
            'spkId' => (int) $detail->spk_id,
            'spkNo' => $detail->production?->spk_no,
            'spkType' => filled($detail->production?->spk_type)
                ? (string) $detail->production->spk_type
                : null,
            'orderTypeLabel' => app(ProductionOrderTypeLabel::class)->forProduction($detail->production),
            'material' => filled($detail->production?->gold_color)
                ? (string) $detail->production->gold_color
                : $detail->material,
            'goldWeight' => $detail->production?->gold_weight !== null
                ? number_format((float) $detail->production->gold_weight, 2, '.', '')
                : '',
            'skuCode' => filled($detail->production?->sku?->sku_code)
                ? (string) $detail->production->sku->sku_code
                : null,
            'typeCode' => $typeCode !== '' ? $typeCode : null,
            'productItemName' => $productItemName !== '' ? $productItemName : null,
            'itemDescription' => $itemDescription !== '' ? $itemDescription : null,
            'satuan' => SpkQtyUnit::label($qty, $detail->production?->satuan),
            'qty' => $qty,
            'estimationBrj' => number_format((float) $detail->estimation_brj, 3, '.', ''),
            'notes' => filled($detail->production?->notes)
                ? (string) $detail->production->notes
                : $detail->notes,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function jewelCadDetailEagerLoads(): array
    {
        return [
            'details' => fn ($query) => $query->notDeleted()
                ->with([
                    'production' => fn ($productionQuery) => $productionQuery
                        ->notDeleted()
                        ->with([
                            'sku' => fn ($skuQuery) => $skuQuery
                                ->select(['id', 'sku_code', 'item_original']),
                            'categoryPrefix' => fn ($prefixQuery) => $prefixQuery
                                ->select(['id', 'prefix']),
                        ])
                        ->select([
                            'row_id',
                            'spk_no',
                            'spk_type',
                            'request_order_no',
                            'item_name',
                            'customer_name',
                            'gold_color',
                            'gold_weight',
                            'qty',
                            'satuan',
                            'sku_id',
                            'category_prefix_id',
                            'notes',
                            'description',
                            'jwcad_3d',
                        ]),
                ])
                ->orderBy('line_id'),
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function operatorOptions(): array
    {
        return Employee::query()
            ->productionActive()
            ->whereNotNull('nama_lengkap')
            ->where('nama_lengkap', '!=', '')
            ->orderBy('nama_lengkap')
            ->get(['id', 'nama_lengkap'])
            ->map(fn (Employee $employee): array => [
                'value' => (string) $employee->nama_lengkap,
                'label' => (string) $employee->nama_lengkap,
            ])
            ->unique('value')
            ->values()
            ->all();
    }

    private function defaultOperatorName(Request $request): string
    {
        $user = $request->user();

        if ($user === null) {
            return '';
        }

        $employeeId = $user->getAttribute('employee_id');

        if (filled($employeeId)) {
            $linked = Employee::query()
                ->productionActive()
                ->where('id', (int) $employeeId)
                ->value('nama_lengkap');

            if (filled($linked)) {
                return (string) $linked;
            }
        }

        $matched = Employee::query()
            ->productionActive()
            ->where('nama_lengkap', $user->name)
            ->value('nama_lengkap');

        return filled($matched) ? (string) $matched : '';
    }

    private function actorName(Request $request): string
    {
        return $request->user()?->name ?? 'system';
    }
}
