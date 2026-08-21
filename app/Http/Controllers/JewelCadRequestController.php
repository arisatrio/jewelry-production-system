<?php

namespace App\Http\Controllers;

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
use App\Support\SkuMasterDiamondMapper;
use App\Support\SpkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class JewelCadRequestController extends Controller
{
    /**
     * Display a listing of JewelCAD requests.
     */
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();
        $perPage = $request->integer('per_page', 10);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;

        $requests = JewelCadRequest::query()
            ->notDeleted()
            ->withCount([
                'details as detail_count' => fn ($query) => $query->notDeleted(),
            ])
            ->with([
                'details' => function ($query): void {
                    $query->notDeleted()
                        ->with([
                            'production' => fn ($productionQuery) => $productionQuery
                                ->notDeleted()
                                ->select(['row_id', 'spk_no', 'item_name', 'customer_name']),
                        ])
                        ->orderBy('line_id');
                },
            ])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($innerQuery) use ($search): void {
                    $innerQuery->where('doc_no', 'like', "%{$search}%")
                        ->orWhere('notes', 'like', "%{$search}%")
                        ->orWhereHas('details', function ($detailQuery) use ($search): void {
                            $detailQuery->notDeleted()
                                ->where(function ($detailInnerQuery) use ($search): void {
                                    $detailInnerQuery->where('material', 'like', "%{$search}%")
                                        ->orWhere('notes', 'like', "%{$search}%")
                                        ->orWhereHas('production', function ($productionQuery) use ($search): void {
                                            $productionQuery->notDeleted()
                                                ->where('spk_no', 'like', "%{$search}%")
                                                ->orWhere('item_name', 'like', "%{$search}%")
                                                ->orWhere('customer_name', 'like', "%{$search}%");
                                        });
                                });
                        });
                });
            })
            ->orderByDesc('row_id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (JewelCadRequest $jewelCadRequest): array => $this->toListItem($jewelCadRequest));

        return Inertia::render('jewelcad/index', [
            'requests' => $requests,
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
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
            ->whereNotNull('spk_no')
            ->when($exclude !== [], fn ($builder) => $builder->whereNotIn('row_id', $exclude))
            ->orderByDesc('row_id')
            ->limit($limit)
            ->select([
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

        return response()->json([
            'status' => true,
            'data' => $query->get()->map(fn (Production $production): array => [
                'rowId' => (int) $production->row_id,
                'spkNo' => (string) $production->spk_no,
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
                    ? number_format((float) $production->gold_weight, 3, '.', '')
                    : '',
                'qty' => $production->qty ?? 1,
                'notes' => filled($production->notes)
                    ? (string) $production->notes
                    : '',
            ])->values()->all(),
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
            ? number_format((float) $production->sku->gold_weight, 3, '.', '')
            : null;
        $productionGoldWeight = $production->gold_weight !== null
            && (float) $production->gold_weight > 0
            ? number_format((float) $production->gold_weight, 3, '.', '')
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
                        ? trim($production->qty.' '.(filled($production->satuan) ? $production->satuan : 'Pcs'))
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
                    ? number_format((float) $production->gold_weight, 3, '.', '')
                    : '',
                'qty' => $production->qty ?? 1,
                'notes' => filled($production->notes) ? (string) $production->notes : '',
                'estimationBrj' => number_format((float) $validated['estimation_brj'], 2, '.', ''),
            ],
        ]);
    }

    /**
     * Store a newly created request in storage.
     */
    public function store(
        StoreJewelCadRequestRequest $request,
        SpkService $spkService,
    ): RedirectResponse {
        $validated = $request->validated();
        $actor = $this->actorName($request);

        DB::connection('third')->transaction(function () use ($validated, $actor, $spkService, $request): void {
            $jewelCadRequest = JewelCadRequest::query()->create([
                'doc_no' => $this->nextDocNo(),
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

            $this->storeDetails($jewelCadRequest, $validated['details'], $actor, $spkService, $request);
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Request JewelCAD berhasil ditambahkan.',
        ]);

        return to_route('jewelcad.index');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        abort(404);
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

        $jewelcad->load([
            'details' => fn ($query) => $query->notDeleted()
                ->with([
                    'production' => fn ($productionQuery) => $productionQuery
                        ->notDeleted()
                        ->select([
                            'row_id',
                            'spk_no',
                            'item_name',
                            'customer_name',
                            'gold_color',
                            'gold_weight',
                            'qty',
                            'notes',
                            'jwcad_3d',
                        ]),
                ])
                ->orderBy('line_id'),
        ]);

        return Inertia::render('jewelcad/edit', [
            'formDocumentNo' => (string) config('spk.jewelcad_form_document_no'),
            'operatorOptions' => $this->operatorOptions(),
            'approvalFooter' => $approvalService->footerColumns(
                $jewelcad,
                $this->actorName($request),
            ),
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
    ): RedirectResponse {
        abort_if($jewelcad->is_deleted === 1, 404);

        $validated = $request->validated();
        $actor = $this->actorName($request);

        DB::connection('third')->transaction(function () use ($jewelcad, $validated, $actor, $spkService, $request): void {
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

            $this->storeDetails($jewelcad, $validated['details'], $actor, $spkService, $request);
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Request JewelCAD berhasil diperbarui.',
        ]);

        return to_route('jewelcad.index');
    }

    /**
     * Remove the specified request from storage.
     */
    public function destroy(Request $request, JewelCadRequest $jewelcad): RedirectResponse
    {
        abort_if($jewelcad->is_deleted === 1, 404);

        $actor = $this->actorName($request);

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

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Request JewelCAD berhasil dihapus.',
        ]);

        return to_route('jewelcad.index');
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
    ): void {
        foreach ($details as $index => $detail) {
            $production = Production::query()
                ->notDeleted()
                ->where('row_id', $detail['spk_id'])
                ->first();

            if (
                $production !== null
                && array_key_exists('stones', $detail)
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
     * @return array{
     *     id: int,
     *     docNo: string|null,
     *     transDate: string|null,
     *     status: string|null,
     *     notes: string|null,
     *     detailCount: int,
     *     materials: list<string>,
     *     spkNos: list<string>
     * }
     */
    private function toListItem(JewelCadRequest $request): array
    {
        $details = $request->details
            ->filter(fn (JewelCadRequestDetail $detail): bool => $detail->is_deleted === 0)
            ->values();

        return [
            'id' => (int) $request->row_id,
            'docNo' => $request->doc_no,
            'transDate' => $request->trans_date?->format('Y-m-d'),
            'status' => $request->status,
            'notes' => $request->notes,
            'detailCount' => (int) ($request->detail_count ?? $details->count()),
            'materials' => $details
                ->pluck('material')
                ->filter()
                ->map(fn (mixed $material): string => (string) $material)
                ->unique()
                ->values()
                ->all(),
            'spkNos' => $details
                ->map(fn (JewelCadRequestDetail $detail): ?string => $detail->production?->spk_no)
                ->filter()
                ->map(fn (mixed $spkNo): string => (string) $spkNo)
                ->unique()
                ->values()
                ->all(),
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
                ->map(fn (JewelCadRequestDetail $detail): array => [
                    'spkId' => (int) $detail->spk_id,
                    'spkNo' => $detail->production?->spk_no,
                    'material' => filled($detail->production?->gold_color)
                        ? (string) $detail->production->gold_color
                        : $detail->material,
                    'goldWeight' => $detail->production?->gold_weight !== null
                        ? number_format((float) $detail->production->gold_weight, 3, '.', '')
                        : '',
                    'qty' => $detail->production?->qty ?? $detail->qty,
                    'estimationBrj' => number_format((float) $detail->estimation_brj, 2, '.', ''),
                    'notes' => filled($detail->production?->notes)
                        ? (string) $detail->production->notes
                        : $detail->notes,
                ])
                ->values()
                ->all(),
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

    private function nextDocNo(): string
    {
        $latest = JewelCadRequest::query()
            ->notDeleted()
            ->where('doc_no', 'like', 'JWC%')
            ->orderByDesc('row_id')
            ->value('doc_no');

        $nextNumber = 1;

        if (is_string($latest) && preg_match('/(\d+)$/', $latest, $matches) === 1) {
            $nextNumber = ((int) ($matches[1] ?? 0)) + 1;
        }

        return sprintf('JWC%07d', $nextNumber);
    }

    private function actorName(Request $request): string
    {
        return $request->user()?->name ?? 'system';
    }
}
