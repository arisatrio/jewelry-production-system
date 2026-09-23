<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDiamondMountingRequest;
use App\Http\Requests\UpdateDiamondMountingRequest;
use App\Models\DiamondMounting;
use App\Models\Production;
use App\Support\DiamondMountingApprovalService;
use App\Support\DiamondMountingDocNumberGenerator;
use App\Support\DiamondMountingSpkEligibility;
use App\Support\DiamondMountingStoneSynchronizer;
use App\Support\ProductionOrderTypeLabel;
use App\Support\SpkQtyUnit;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class DiamondMountingController extends Controller
{
    public function index(Request $request, DiamondMountingSpkEligibility $spkEligibility): Response
    {
        $search = $request->string('search')->trim()->toString();
        $perPage = $request->integer('per_page', 10);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;

        $documents = DiamondMounting::query()
            ->notDeleted()
            ->with([
                'production' => fn ($productionQuery) => $productionQuery
                    ->notDeleted()
                    ->select(['row_id', 'spk_no', 'item_name', 'customer_name']),
            ])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($innerQuery) use ($search): void {
                    $innerQuery->where('doc_no', 'like', "%{$search}%")
                        ->orWhere('status', 'like', "%{$search}%")
                        ->orWhere('notes', 'like', "%{$search}%")
                        ->orWhereHas('production', function ($productionQuery) use ($search): void {
                            $productionQuery->notDeleted()
                                ->where(function ($productionInner) use ($search): void {
                                    $productionInner->where('spk_no', 'like', "%{$search}%")
                                        ->orWhere('item_name', 'like', "%{$search}%")
                                        ->orWhere('customer_name', 'like', "%{$search}%");
                                });
                        });
                });
            })
            ->orderByDesc('row_id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (DiamondMounting $document): array => $this->toListItem($document));

        return Inertia::render('pasang-batu/index', [
            'documents' => $documents,
            'spkStatusCounts' => $this->spkStatusCounts($spkEligibility),
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
            ],
        ]);
    }

    public function create(DiamondMountingStoneSynchronizer $stoneSynchronizer): Response
    {
        return Inertia::render('pasang-batu/create', [
            'formDocumentNo' => (string) config('spk.pasang_batu_form_document_no'),
            'craftsmanOptions' => $this->craftsmanOptions(),
            'stoneOptions' => $stoneSynchronizer->stoneOptions(),
            'shapeOptions' => $stoneSynchronizer->shapeOptions(),
            'form' => [
                'sendCraftsmanDate' => now()->format('Y-m-d H:i'),
                'receivedCraftsmanDate' => '',
                'craftsmanId' => null,
                'notes' => '',
                'weightFrame' => '',
                'weightDiamond' => '',
                'weightFinishGoods' => '',
                'settingStones' => [],
                'returnStones' => [],
                'diamonds' => [],
                'mountedStones' => [],
                'spk' => null,
            ],
        ]);
    }

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
        $spkEligibility = app(DiamondMountingSpkEligibility::class);

        match ($queue) {
            'inProgress' => $query->tap(
                fn (Builder $builder) => $spkEligibility->applyInProgressScope($builder),
            ),
            'completed' => $query->tap(
                fn (Builder $builder) => $spkEligibility->applyCompletedScope($builder),
            ),
            'pending' => $query->tap(
                fn (Builder $builder) => $spkEligibility->applyEligibleScope($builder),
            ),
            default => $query->whereNotNull('spk_no'),
        };

        $query->with($this->productionSpkInfoRelations())
            ->select([
                ...$this->productionSpkInfoColumns(),
                'gold_color',
                'last_weight',
            ]);

        if ($search !== '') {
            $like = '%'.$search.'%';

            $query->where(function ($innerQuery) use ($like): void {
                $innerQuery->where('spk_no', 'like', $like)
                    ->orWhere('item_name', 'like', $like)
                    ->orWhere('customer_name', 'like', $like)
                    ->orWhere('gold_color', 'like', $like);
            });
        }

        $productions = $query->get();
        $refs = in_array($queue, ['inProgress', 'completed'], true)
            ? $spkEligibility->diamondMountingRefsBySpkIds(
                $productions->pluck('row_id')->map(fn (mixed $id): int => (int) $id)->all(),
            )
            : [];

        return response()->json([
            'status' => true,
            'data' => $productions->map(function (Production $production) use ($refs): array {
                $spkId = (int) $production->row_id;
                $ref = $refs[$spkId] ?? null;

                return [
                    'rowId' => $spkId,
                    'spkNo' => (string) $production->spk_no,
                    'diamondMountingId' => $ref['diamondMountingId'] ?? null,
                    'docNo' => $ref['docNo'] ?? null,
                    'customer' => filled($production->customer_name)
                        ? (string) $production->customer_name
                        : '—',
                    'item' => filled($production->item_name)
                        ? (string) $production->item_name
                        : '—',
                    'goldColor' => filled($production->gold_color)
                        ? (string) $production->gold_color
                        : '',
                    'qty' => $production->qty ?? 1,
                    'lastWeight' => $this->formatDecimal($production->last_weight),
                    ...$this->productionSpkInfoFields($production),
                ];
            })->values()->all(),
        ]);
    }

    public function store(
        StoreDiamondMountingRequest $request,
        DiamondMountingDocNumberGenerator $docNumberGenerator,
        DiamondMountingStoneSynchronizer $stoneSynchronizer,
    ): RedirectResponse {
        $validated = $request->validated();
        $stonePayload = $request->stonePayload();
        $actor = $this->actorName($request);

        $document = DB::connection('third')->transaction(function () use (
            $validated,
            $stonePayload,
            $actor,
            $docNumberGenerator,
            $stoneSynchronizer,
        ): DiamondMounting {
            $sendDate = $validated['send_craftsman_date'] ?? null;
            $receivedDate = $validated['received_craftsman_date'] ?? null;
            $transDate = filled($sendDate)
                ? substr((string) $sendDate, 0, 10)
                : now()->toDateString();

            $document = DiamondMounting::query()->create([
                'doc_no' => $docNumberGenerator->generate(),
                'trans_date' => $transDate,
                'process_name' => 'Pasang Batu',
                'spk_id' => $validated['spk_id'],
                'weight_frame' => $validated['weight_frame'] ?? null,
                'weight_diamond' => $validated['weight_diamond'] ?? null,
                'total_weigth_frame_diamond' => null,
                'mounting_return' => null,
                'mounting_shrink' => '0.00',
                'weight_finish_goods' => $validated['weight_finish_goods'] ?? null,
                'polish_shrink' => null,
                'craftman_id' => $validated['craftsman_id'] ?? null,
                'setting_id' => null,
                'qc_id' => null,
                'notes' => $validated['notes'] ?? null,
                'status' => null,
                'send_craftsman_date' => $sendDate,
                'received_craftsman_date' => $receivedDate,
                'is_from_new_system' => 1,
                'is_deleted' => 0,
                'created_date' => now(),
                'created_by' => $actor,
                'modified_date' => now(),
                'modified_by' => $actor,
            ]);

            $production = Production::query()
                ->notDeleted()
                ->where('row_id', $validated['spk_id'])
                ->first();

            if ($production !== null) {
                $eligibility = app(DiamondMountingSpkEligibility::class);
                $eligibility->markProcessStarted($production, $actor);
                $eligibility->syncLastWeight(
                    $production,
                    $validated['weight_finish_goods'] ?? null,
                    $actor,
                );
            }

            $stoneSynchronizer->sync($document->refresh(), $stonePayload, $actor);
            $this->recalculateWeights($document->refresh());

            return $document->refresh();
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Dokumen Pasang Batu berhasil ditambahkan.',
        ]);

        return to_route('pasang-batu.show', $document);
    }

    public function show(
        Request $request,
        DiamondMounting $pasangBatu,
        DiamondMountingApprovalService $approvalService,
        DiamondMountingStoneSynchronizer $stoneSynchronizer,
    ): Response {
        abort_if($pasangBatu->is_deleted === 1, 404);

        $pasangBatu->load([
            'production' => fn ($productionQuery) => $productionQuery
                ->notDeleted()
                ->with($this->productionSpkInfoRelations())
                ->select($this->productionSpkInfoColumns()),
        ]);

        return Inertia::render('pasang-batu/show', [
            'diamondMountingItem' => $this->toDetailItem($pasangBatu, $stoneSynchronizer),
            'workflowStatus' => $approvalService->map($pasangBatu),
            'approvalHistory' => $approvalService->history($pasangBatu),
            'approvalFooter' => $approvalService->footerColumns(
                $pasangBatu,
                $this->actorName($request),
            ),
            'approval' => $approvalService->abilitiesFor($pasangBatu, $request->user()),
        ]);
    }

    public function edit(
        Request $request,
        DiamondMounting $pasangBatu,
        DiamondMountingApprovalService $approvalService,
        DiamondMountingStoneSynchronizer $stoneSynchronizer,
    ): Response {
        abort_if($pasangBatu->is_deleted === 1, 404);
        abort_unless(
            $approvalService->abilitiesFor($pasangBatu, $request->user())['canEdit'],
            403,
            'Dokumen Pasang Batu tidak dapat diubah pada status saat ini.',
        );

        $pasangBatu->load([
            'production' => fn ($productionQuery) => $productionQuery
                ->notDeleted()
                ->with($this->productionSpkInfoRelations())
                ->select($this->productionSpkInfoColumns()),
        ]);

        $production = $pasangBatu->production;
        $stoneForm = $stoneSynchronizer->formPayloadFor($pasangBatu);

        return Inertia::render('pasang-batu/edit', [
            'formDocumentNo' => (string) config('spk.pasang_batu_form_document_no'),
            'craftsmanOptions' => $this->craftsmanOptions(),
            'stoneOptions' => $stoneSynchronizer->stoneOptions(),
            'shapeOptions' => $stoneSynchronizer->shapeOptions(),
            'form' => [
                'id' => (int) $pasangBatu->row_id,
                'docNo' => $pasangBatu->doc_no,
                'sendCraftsmanDate' => $pasangBatu->send_craftsman_date?->format('Y-m-d H:i') ?? '',
                'receivedCraftsmanDate' => $pasangBatu->received_craftsman_date?->format('Y-m-d H:i') ?? '',
                'craftsmanId' => filled($pasangBatu->craftman_id) && (int) $pasangBatu->craftman_id > 0
                    ? (int) $pasangBatu->craftman_id
                    : null,
                'notes' => filled($pasangBatu->notes) ? (string) $pasangBatu->notes : '',
                'weightFrame' => $this->formatDecimal($pasangBatu->weight_frame) ?? '',
                'weightDiamond' => $this->formatDecimal($pasangBatu->weight_diamond, 3) ?? '',
                'weightFinishGoods' => $this->formatDecimal($pasangBatu->weight_finish_goods) ?? '',
                'settingStones' => $stoneForm['setting'],
                'returnStones' => $stoneForm['return'],
                'diamonds' => $stoneForm['diamonds'],
                'mountedStones' => $stoneForm['mounted'],
                'spk' => $production === null ? null : [
                    'spkId' => (int) $production->row_id,
                    'spkNo' => $production->spk_no,
                    ...$this->productionSpkInfoFields($production),
                ],
            ],
            'approval' => $approvalService->abilitiesFor($pasangBatu, $request->user()),
        ]);
    }

    public function submit(
        Request $request,
        DiamondMounting $pasangBatu,
        DiamondMountingApprovalService $approvalService,
    ): RedirectResponse {
        abort_if($pasangBatu->is_deleted === 1, 404);

        if (! $approvalService->abilitiesFor($pasangBatu, $request->user())['canSubmit']) {
            abort(403, 'Dokumen ini tidak dapat dikirim ke Manager Produksi.');
        }

        try {
            $approvalService->submit($pasangBatu, $this->actorName($request));
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['approval' => $exception->getMessage()]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Dokumen Pasang Batu dikirim ke Manager Produksi.',
        ]);

        return to_route('pasang-batu.show', $pasangBatu);
    }

    public function managerApprove(
        Request $request,
        DiamondMounting $pasangBatu,
        DiamondMountingApprovalService $approvalService,
    ): RedirectResponse {
        abort_if($pasangBatu->is_deleted === 1, 404);

        if (! $approvalService->abilitiesFor($pasangBatu, $request->user())['canManagerApprove']) {
            abort(403, 'Dokumen ini tidak dapat di-approve oleh Manager Produksi.');
        }

        try {
            $approvalService->managerApprove($pasangBatu, $this->actorName($request));
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['approval' => $exception->getMessage()]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Dokumen Pasang Batu di-approve oleh Manager Produksi.',
        ]);

        return to_route('pasang-batu.show', $pasangBatu);
    }

    public function complete(
        Request $request,
        DiamondMounting $pasangBatu,
        DiamondMountingApprovalService $approvalService,
    ): RedirectResponse {
        abort_if($pasangBatu->is_deleted === 1, 404);

        if (! $approvalService->abilitiesFor($pasangBatu, $request->user())['canComplete']) {
            abort(403, 'Dokumen ini tidak dapat diselesaikan.');
        }

        try {
            $approvalService->complete($pasangBatu, $this->actorName($request));
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['approval' => $exception->getMessage()]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Dokumen Pasang Batu selesai.',
        ]);

        return to_route('pasang-batu.show', $pasangBatu);
    }

    public function update(
        UpdateDiamondMountingRequest $request,
        DiamondMounting $pasangBatu,
        DiamondMountingApprovalService $approvalService,
        DiamondMountingStoneSynchronizer $stoneSynchronizer,
    ): RedirectResponse {
        abort_if($pasangBatu->is_deleted === 1, 404);
        abort_unless(
            $approvalService->abilitiesFor($pasangBatu, $request->user())['canEdit'],
            403,
            'Dokumen Pasang Batu tidak dapat diubah pada status saat ini.',
        );

        $validated = $request->validated();
        $stonePayload = $request->stonePayload();
        $actor = $this->actorName($request);

        DB::connection('third')->transaction(function () use (
            $pasangBatu,
            $validated,
            $stonePayload,
            $actor,
            $stoneSynchronizer,
        ): void {
            $sendDate = $validated['send_craftsman_date'] ?? null;
            $receivedDate = $validated['received_craftsman_date'] ?? null;
            $transDate = filled($sendDate)
                ? substr((string) $sendDate, 0, 10)
                : ($pasangBatu->trans_date?->format('Y-m-d') ?? now()->toDateString());

            $pasangBatu->update([
                'spk_id' => $validated['spk_id'],
                'trans_date' => $transDate,
                'process_name' => $pasangBatu->process_name ?? 'Pasang Batu',
                'craftman_id' => $validated['craftsman_id'] ?? null,
                'weight_frame' => $validated['weight_frame'] ?? null,
                'weight_diamond' => $validated['weight_diamond'] ?? null,
                'weight_finish_goods' => $validated['weight_finish_goods'] ?? null,
                'send_craftsman_date' => $sendDate,
                'received_craftsman_date' => $receivedDate,
                'notes' => $validated['notes'] ?? null,
                'modified_date' => now(),
                'modified_by' => $actor,
            ]);

            $production = Production::query()
                ->notDeleted()
                ->where('row_id', $validated['spk_id'])
                ->first();

            if ($production !== null) {
                app(DiamondMountingSpkEligibility::class)->syncLastWeight(
                    $production,
                    $validated['weight_finish_goods'] ?? null,
                    $actor,
                );
            }

            $stoneSynchronizer->sync($pasangBatu->refresh(), $stonePayload, $actor);
            $this->recalculateWeights($pasangBatu->refresh());
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Dokumen Pasang Batu berhasil diperbarui.',
        ]);

        return to_route('pasang-batu.show', $pasangBatu);
    }

    /**
     * @return array<string, mixed>
     */
    private function toListItem(DiamondMounting $document): array
    {
        return [
            'id' => (int) $document->row_id,
            'docNo' => $document->doc_no,
            'transDate' => $document->send_craftsman_date?->format('Y-m-d')
                ?? $document->trans_date?->format('Y-m-d'),
            'status' => filled($document->status) ? (string) $document->status : null,
            'statusLabel' => $document->statusLabel(),
            'spkNo' => $document->production?->spk_no,
            'weightFrame' => $this->formatDecimal($document->weight_frame),
            'weightDiamond' => $this->formatDecimal($document->weight_diamond, 3),
            'weightFinishGoods' => $this->formatDecimal($document->weight_finish_goods),
            'shrink' => $this->formatDecimal($document->mounting_shrink),
            'notes' => filled($document->notes) ? (string) $document->notes : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function toDetailItem(
        DiamondMounting $document,
        DiamondMountingStoneSynchronizer $stoneSynchronizer,
    ): array {
        $baseWeight = $this->toFloat($document->total_weigth_frame_diamond)
            ?? $this->toFloat($document->weight_frame);
        $shrink = $this->toFloat($document->mounting_shrink);
        $shrinkPercent = null;

        if ($shrink !== null && $baseWeight !== null && abs($baseWeight) >= 0.0005) {
            $shrinkPercent = number_format(($shrink / $baseWeight) * 100, 2, '.', '').'%';
        }

        $production = $document->production;
        $stones = $stoneSynchronizer->detailPayloadFor($document);

        return [
            'id' => (int) $document->row_id,
            'docNo' => $document->doc_no,
            'status' => filled($document->status) ? (string) $document->status : null,
            'statusLabel' => $document->statusLabel(),
            'craftsmanId' => filled($document->craftman_id) && (int) $document->craftman_id > 0
                ? (int) $document->craftman_id
                : null,
            'craftsmanName' => $this->resolveCraftsmanName($document->craftman_id),
            'sendCraftsmanDate' => $document->send_craftsman_date?->format('Y-m-d H:i:s'),
            'receivedCraftsmanDate' => $document->received_craftsman_date?->format('Y-m-d H:i:s'),
            'notes' => filled($document->notes) ? (string) $document->notes : null,
            'weightFrame' => $this->formatDecimal($document->weight_frame),
            'weightDiamond' => $this->formatDecimal($document->weight_diamond, 3),
            'totalWeight' => $this->formatDecimal($document->total_weigth_frame_diamond),
            'weightFinishGoods' => $this->formatDecimal($document->weight_finish_goods),
            'shrink' => $this->formatDecimal($document->mounting_shrink),
            'shrinkPercent' => $shrinkPercent,
            'stones' => $stones,
            'spk' => $production === null && ! filled($document->spk_id)
                ? null
                : [
                    'spkId' => filled($document->spk_id) ? (int) $document->spk_id : null,
                    'spkNo' => $production?->spk_no,
                    ...$this->productionSpkInfoFields($production),
                ],
        ];
    }

    private function resolveCraftsmanName(mixed $craftsmanId): ?string
    {
        $id = filled($craftsmanId) ? (int) $craftsmanId : 0;

        if ($id <= 0 || ! Schema::connection('third')->hasTable('mscraftsman')) {
            return null;
        }

        $name = DB::connection('third')
            ->table('mscraftsman')
            ->where('row_id', $id)
            ->value('name');

        if (! filled($name)) {
            return "Pengrajin {$id}";
        }

        return (string) $name;
    }

    private function recalculateWeights(DiamondMounting $document): void
    {
        $frame = $this->toFloat($document->weight_frame);
        $diamond = $this->toFloat($document->weight_diamond) ?? 0.0;
        $finish = $this->toFloat($document->weight_finish_goods);

        $total = $frame !== null
            ? round($frame + $diamond, 2)
            : null;

        $shrink = ($total !== null && $finish !== null)
            ? round($total - $finish, 2)
            : 0.0;

        $document->forceFill([
            'total_weigth_frame_diamond' => $total !== null
                ? number_format($total, 2, '.', '')
                : null,
            'mounting_shrink' => number_format($shrink, 2, '.', ''),
        ])->save();
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function craftsmanOptions(): array
    {
        if (! Schema::connection('third')->hasTable('mscraftsman')) {
            return [];
        }

        return DB::connection('third')
            ->table('mscraftsman')
            ->where('is_deleted', 0)
            ->where('is_active', 'YES')
            ->whereNotNull('name')
            ->where('name', '!=', '')
            ->orderBy('name')
            ->get(['row_id', 'name'])
            ->map(fn (object $row): array => [
                'value' => (string) $row->row_id,
                'label' => (string) $row->name,
            ])
            ->values()
            ->all();
    }

    private function actorName(Request $request): string
    {
        return $request->user()?->name ?? 'system';
    }

    /**
     * @return array<string, mixed>
     */
    private function productionSpkInfoRelations(): array
    {
        return [
            'sku' => fn ($skuQuery) => $skuQuery
                ->select(['id', 'sku_code', 'item_original']),
            'categoryPrefix' => fn ($prefixQuery) => $prefixQuery
                ->select(['id', 'prefix']),
        ];
    }

    /**
     * @return list<string>
     */
    private function productionSpkInfoColumns(): array
    {
        return [
            'row_id',
            'spk_no',
            'spk_type',
            'request_order_no',
            'item_name',
            'customer_name',
            'qty',
            'satuan',
            'sku_id',
            'category_prefix_id',
            'description',
        ];
    }

    /**
     * @return array{
     *     spkType: string|null,
     *     orderTypeLabel: string|null,
     *     skuCode: string|null,
     *     typeCode: string|null,
     *     productItemName: string|null,
     *     itemDescription: string|null,
     *     customerName: string|null,
     *     satuan: string
     * }
     */
    private function productionSpkInfoFields(?Production $production): array
    {
        if ($production === null) {
            return [
                'spkType' => null,
                'orderTypeLabel' => null,
                'skuCode' => null,
                'typeCode' => null,
                'productItemName' => null,
                'itemDescription' => null,
                'customerName' => null,
                'satuan' => '—',
            ];
        }

        $typeCode = trim((string) ($production->categoryPrefix?->prefix ?? ''));
        $productItemName = trim((string) ($production->sku?->item_original ?? ''));

        if ($productItemName === '') {
            $productItemName = trim((string) ($production->item_name ?? ''));
        }

        $itemDescription = trim((string) ($production->description ?? ''));

        return [
            'spkType' => filled($production->spk_type)
                ? (string) $production->spk_type
                : null,
            'orderTypeLabel' => app(ProductionOrderTypeLabel::class)->forProduction($production),
            'skuCode' => filled($production->sku?->sku_code)
                ? (string) $production->sku->sku_code
                : null,
            'typeCode' => $typeCode !== '' ? $typeCode : null,
            'productItemName' => $productItemName !== '' ? $productItemName : null,
            'itemDescription' => $itemDescription !== '' ? $itemDescription : null,
            'customerName' => filled($production->customer_name)
                ? (string) $production->customer_name
                : null,
            'satuan' => SpkQtyUnit::label($production->qty, $production->satuan),
        ];
    }

    private function formatDecimal(mixed $value, int $precision = 2): ?string
    {
        $number = $this->toFloat($value);

        if ($number === null) {
            return null;
        }

        return number_format($number, $precision, '.', '');
    }

    private function toFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }

    /**
     * @return array{pending: int, inProgress: int, completed: int}
     */
    private function spkStatusCounts(DiamondMountingSpkEligibility $spkEligibility): array
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
}
