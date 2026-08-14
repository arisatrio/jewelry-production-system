<?php

namespace App\Http\Controllers;

use App\Http\Requests\SpkApprovalDecisionRequest;
use App\Http\Requests\StoreProductionRequest;
use App\Http\Requests\UpdateProductionRequest;
use App\Models\MsPosition;
use App\Models\MsShape;
use App\Models\Production;
use App\Models\SkuMaster;
use App\Models\SkuPrefixCategory;
use App\Models\SpkStone;
use App\Policies\ProductionPolicy;
use App\Support\GoldColorOptions;
use App\Support\RequestOrderRepository;
use App\Support\SpkApprovalRoles;
use App\Support\SpkApprovalService;
use App\Support\SpkCraftsmanReport;
use App\Support\SpkGoldReport;
use App\Support\SpkProcessMapper;
use App\Support\SpkProductionControlReport;
use App\Support\SpkService;
use App\Support\SpkShrinkSummary;
use App\Support\SpkStatusMapper;
use App\Support\SpkStatusOrder;
use App\Support\SpkStoneReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class ProductionController extends Controller
{
    public function __construct(private SpkStatusOrder $statusOrder) {}

    /**
     * Display a listing of SPK productions.
     */
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();
        $perPage = $request->integer('per_page', 10);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;

        $productions = Production::query()
            ->notDeleted()
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('spk_no', 'like', "%{$search}%")
                        ->orWhere('spk_type', 'like', "%{$search}%")
                        ->orWhere('customer_name', 'like', "%{$search}%")
                        ->orWhere('item_name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('status', 'like', "%{$search}%")
                        ->orWhere('last_process', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('row_id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (Production $production): array => $this->toListItem($production));

        return Inertia::render('spk/index', [
            'productions' => $productions,
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
            ],
        ]);
    }

    /**
     * Show the form for creating a new SPK (nomor digenerate saat simpan).
     */
    public function create(Request $request): Response
    {
        return Inertia::render('spk/form', [
            'production' => $this->emptyFormData(),
            'stones' => [],
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
        $blank = '';

        return view('spk.print', [
            'title' => 'Form SPK — Template',
            'header' => $this->documentHeader(),
            'blankTemplate' => true,
            'document' => [
                'info' => [
                    'spkNo' => $blank,
                    'spkType' => $blank,
                    'requestOrderNo' => $blank,
                    'refSpkNo' => $blank,
                    'customerName' => $blank,
                    'orderDate' => $blank,
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
        $production = $spkService->createWithDetails(
            $request->validated(),
            $this->actorName($request),
            $request->file('file'),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "SPK {$production->spk_no} berhasil dibuat.",
        ]);

        return to_route('spk.form', $production->row_id);
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
            abort(403, 'SPK hanya dapat diedit saat berstatus Draft oleh Admin/SPV.');
        }

        $spkService->saveHeader(
            $production,
            $request->validated(),
            $this->actorName($request),
            $request->file('file'),
        );

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
     * Manager menyetujui SPK (SPKDONE).
     */
    public function approve(
        SpkApprovalDecisionRequest $request,
        int $rowId,
        SpkApprovalService $approvalService,
        ProductionPolicy $policy,
    ): RedirectResponse {
        $production = $this->findActiveProduction($rowId);

        if (! $policy->approve($request->user(), $production)) {
            abort(403, 'Hanya Manager Produksi yang dapat approve SPK.');
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
            'message' => "SPK {$production->spk_no} disetujui (SPKDONE).",
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
    public function destroy(Request $request, int $rowId, SpkService $spkService): RedirectResponse
    {
        $production = $this->findActiveProduction($rowId);
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

        $baseQuery = Production::query()->notDeleted()->whereNotNull('spk_no');

        $total = (clone $baseQuery)->count();
        $position = (clone $baseQuery)->where('row_id', '>', $production->row_id)->count() + 1;
        $previousSpkNo = (clone $baseQuery)
            ->where('row_id', '>', $production->row_id)
            ->orderBy('row_id')
            ->value('spk_no');
        $nextSpkNo = (clone $baseQuery)
            ->where('row_id', '<', $production->row_id)
            ->orderByDesc('row_id')
            ->value('spk_no');

        return Inertia::render('spk/show', [
            'production' => $this->toDetail($production, $statusMapper),
            'item' => $this->toItemDetail($production),
            'stones' => $production->stones()
                ->notDeleted()
                ->with(['shape', 'position'])
                ->orderBy('line_id')
                ->get()
                ->map(fn (SpkStone $stone): array => $this->toStoneItem($stone))
                ->values(),
            'processes' => $processMapper->forProduction((int) $production->row_id),
            'defaultProcessSelection' => $processMapper->resolveDefaultSelection($production->last_process),
            'shrinkReport' => $shrinkSummary->forProduction($production),
            'craftsmanReport' => $craftsmanReport->forProduction($production),
            'goldReport' => $goldReport->forProduction($production),
            'stoneReport' => $stoneReport->forProduction($production),
            'productionControlReport' => $productionControlReport->forProduction($production, $goldReport),
            'navigation' => [
                'position' => $position,
                'total' => $total,
                'previousSpkNo' => $previousSpkNo !== null ? (string) $previousSpkNo : null,
                'nextSpkNo' => $nextSpkNo !== null ? (string) $nextSpkNo : null,
            ],
            'detailUrl' => route('spk.show', $production, absolute: true),
            'approval' => $this->approvalAbilities($request, $production),
            'approvalFooter' => app(SpkApprovalService::class)->footerColumns(
                $production,
                $this->actorName($request),
            ),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function toItemDetail(Production $production): array
    {
        $production->loadMissing(['sku', 'categoryPrefix']);

        $ukuran = $this->ukuranFromProduction($production);
        $qty = $production->qty !== null ? (string) $production->qty : '-';
        $satuan = filled($production->satuan) ? (string) $production->satuan : SpkService::DEFAULT_UNIT;
        $qtyLabel = $qty === '-' ? '-' : trim($qty.' '.$satuan);
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
            'goldColor' => $production->gold_color ?: '-',
            'jwcad3d' => $jwcad3d,
            'description' => filled($production->description) ? (string) $production->description : '-',
            'imageUrl' => $this->productionImageUrl($production->file_name),
            'finishingType' => $jwcad3d,
        ];
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

    /**
     * @return array<string, mixed>
     */
    private function toListItem(Production $production): array
    {
        return [
            'id' => (string) $production->row_id,
            'produksiNo' => $production->spk_no ?? '-',
            'tipeProduksi' => $production->spk_type ?? '-',
            'customer' => filled($production->customer_name) ? $production->customer_name : '-',
            'item' => $production->relationLoaded('item') && $production->item !== null
                ? ($production->item->name ?? '-')
                : ($production->item_name ?? '-'),
            'description' => filled($production->description) ? $production->description : '-',
            'itemId' => $production->item_id !== null ? (string) $production->item_id : null,
            'orderDate' => $production->order_date?->format('d-M-Y') ?? '-',
            'workEstimated' => $production->work_estimated ?? '-',
            'estimatedDelivery' => $production->estimated_delivery_time?->format('d-M-Y') ?? '-',
            'status' => $production->status ?: '-',
            'prosesTerakhir' => $production->last_process ?? '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function toDetail(Production $production, SpkStatusMapper $statusMapper): array
    {
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
            ...$this->toListItem($production),
            'requestOrderNo' => $production->request_order_no ?? '-',
            'refSpkNo' => $refSpkNo,
            'description' => $production->description ?? '-',
            'qty' => $production->qty ?? '-',
            'goldWeight' => $production->gold_weight ?? '-',
            'goldColor' => $production->gold_color ?? '-',
            'goldContent' => $production->gold_content ?? '-',
            'priority' => $production->priority ?? '-',
            'statusOrder' => $this->formatStatusOrder($production->status_order),
            'notes' => $production->notes ?? '-',
            'frameId' => $production->frame_id ?? '-',
            'fileName' => $production->file_name ?? '-',
            'lastWeight' => $production->last_weight ?? '-',
            'createdDate' => $production->created_date?->format('d-M-Y H:i') ?? '-',
            'createdBy' => $production->created_by ?? '-',
            'modifiedDate' => $production->modified_date?->format('d-M-Y H:i') ?? '-',
            'modifiedBy' => $production->modified_by ?? '-',
            'workflowStatus' => $statusMapper->map($production),
        ];
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
            'priorities' => [
                ['value' => 'YES', 'label' => 'YES'],
                ['value' => 'NO', 'label' => 'NO'],
            ],
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
            'skus' => SkuMaster::query()
                ->active()
                ->with('goldColorPrefix')
                ->orderBy('sku_code')
                ->get([
                    'id',
                    'sku_code',
                    'item_original',
                    'category_prefix_id',
                    'gold_prefix_id',
                    'image_url',
                    'catalog_image',
                ])
                ->map(fn (SkuMaster $sku): array => [
                    'value' => (string) $sku->id,
                    'label' => $sku->displayName(),
                    'skuCode' => (string) $sku->sku_code,
                    'itemOriginal' => (string) ($sku->item_original ?? ''),
                    'categoryPrefixId' => $sku->category_prefix_id !== null
                        ? (string) $sku->category_prefix_id
                        : '',
                    'description' => (string) ($sku->item_original ?? ''),
                    'goldColor' => (string) ($sku->resolvedGoldColor() ?? ''),
                    'imageUrl' => filled($sku->image_url)
                        ? (string) $sku->image_url
                        : (filled($sku->catalog_image) ? (string) $sku->catalog_image : null),
                ])
                ->values()
                ->all(),
        ];
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
            'customerName' => null,
            'itemName' => null,
            'refSpkId' => null,
            'refSpkNo' => null,
            'orderDate' => now()->toDateString(),
            'priority' => '',
            'description' => '',
            'workEstimated' => null,
            'estimatedDeliveryTime' => '',
            'itemTypeId' => '',
            'categoryPrefixId' => '',
            'skuId' => '',
            'frameId' => '',
            'frameNo' => '',
            'qty' => '',
            'satuan' => SpkService::DEFAULT_UNIT,
            'statusOrder' => '',
            'diameterLengthRingsize' => '',
            'diameter' => '',
            'dimensi' => '',
            'ringSize' => '',
            'goldWeight' => '0',
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
            'goldWeight' => $production->gold_weight !== null ? (string) $production->gold_weight : '0',
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
            'docNo' => (string) config('spk.form_document_no', 'WHOJ-PRD-FR-001'),
            'issueNo' => (string) config('spk.issue_no', '01'),
            'revision' => (string) config('spk.revision', '00'),
            'issueDate' => now()->format('d-m-Y'),
        ];
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
            'canReject' => false,
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
                'size' => $this->printDecimal3($stone['size'] ?? null),
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

        return [
            'info' => [
                'spkNo' => $this->printText(
                    $info['spkNo'] ?? null,
                    now()->format('Y').'/PRD/00000',
                ),
                'spkType' => $this->printText($info['spkType'] ?? null),
                'requestOrderNo' => $this->printText($info['requestOrderNo'] ?? null),
                'refSpkNo' => $this->printText($info['refSpkNo'] ?? null),
                'customerName' => $this->printText($info['customerName'] ?? null),
                'orderDate' => $this->printText($info['orderDate'] ?? null),
                'workEstimated' => $this->printText($info['workEstimated'] ?? null),
                'estimatedDelivery' => $this->printText($info['estimatedDelivery'] ?? null),
                'priority' => $this->printText($info['priority'] ?? null),
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
                'diameter' => $this->printDecimal3($item['diameter'] ?? null),
                'dimensi' => $this->printDecimal3($item['dimensi'] ?? null),
                'ringSize' => $this->printText($item['ringSize'] ?? null),
                'goldWeight' => $this->printText($item['goldWeight'] ?? null),
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
        $qty = $production->qty !== null ? (string) $production->qty : '';
        $satuan = filled($production->satuan) ? (string) $production->satuan : 'Pcs';
        $qtyLabel = trim($qty.' '.$satuan) !== '' ? trim($qty.' '.$satuan) : '-';
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
                    'size' => $this->printDecimal3($stone->size ?? null),
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
                'refSpkNo' => null,
                'customerName' => $production->customer_name,
                'orderDate' => $production->order_date?->format('d/m/Y'),
                'workEstimated' => $production->work_estimated !== null
                    ? (string) $production->work_estimated.' hari kerja'
                    : null,
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
                    ? (string) $production->gold_weight
                    : null,
                'goldColor' => $production->gold_color,
                'jwcad3d' => $production->jwcad_3d,
                'description' => $production->description,
                'imageUrl' => $this->productionImageUrl($production->file_name) ?? '',
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

    /**
     * Resolve SPK item image URL from file_name.
     *
     * Legacy rows store a bare filename on GCS (production_image_base_url).
     * New uploads are stored on the public disk under a path like spk/{id}/file.jpg.
     */
    private function productionImageUrl(?string $fileName): ?string
    {
        if (! filled($fileName)) {
            return null;
        }

        $path = trim(str_replace('\\', '/', (string) $fileName));

        if ($path === '' || $path === '-') {
            return null;
        }

        if (
            str_starts_with($path, 'http://')
            || str_starts_with($path, 'https://')
        ) {
            return $path;
        }

        $path = preg_replace('#^/?storage/#', '', $path) ?? $path;
        $path = ltrim($path, '/');

        if ($path === '' || $path === '.' || $path === '-') {
            return null;
        }

        if (str_contains($path, '/')) {
            return '/storage/'.$path;
        }

        $base = rtrim((string) config('spk.production_image_base_url'), '/').'/';

        return $base.$path;
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

    private function actorName(Request $request): string
    {
        return $request->user()?->name ?? 'system';
    }
}
