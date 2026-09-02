<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreResinRequest;
use App\Http\Requests\UpdateResinProgressRequest;
use App\Http\Requests\UpdateResinRequest;
use App\Models\Employee;
use App\Models\Production;
use App\Models\Resin;
use App\Models\ResinDetail;
use App\Support\ProductionOrderTypeLabel;
use App\Support\ResinApprovalService;
use App\Support\ResinDocNumberGenerator;
use App\Support\ResinSpkEligibility;
use App\Support\ResinStatusMapper;
use App\Support\SpkQtyUnit;
use Carbon\Carbon;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class ResinController extends Controller
{
    /**
     * Display a listing of resin documents.
     */
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();
        $perPage = $request->integer('per_page', 10);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;

        $resins = Resin::query()
            ->notDeleted()
            ->with([
                'production' => fn ($query) => $query
                    ->notDeleted()
                    ->select(['row_id', 'spk_no']),
                'details' => fn ($query) => $query
                    ->notDeleted()
                    ->with([
                        'production' => fn ($productionQuery) => $productionQuery
                            ->notDeleted()
                            ->select(['row_id', 'spk_no', 'item_name', 'customer_name']),
                    ])
                    ->orderBy('line_id'),
            ])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($innerQuery) use ($search): void {
                    $innerQuery->where('doc_no', 'like', "%{$search}%")
                        ->orWhere('operator', 'like', "%{$search}%")
                        ->orWhere('status', 'like', "%{$search}%")
                        ->orWhereHas('production', function ($productionQuery) use ($search): void {
                            $productionQuery->notDeleted()
                                ->where(function ($productionInner) use ($search): void {
                                    $productionInner->where('spk_no', 'like', "%{$search}%")
                                        ->orWhere('item_name', 'like', "%{$search}%")
                                        ->orWhere('customer_name', 'like', "%{$search}%");
                                });
                        })
                        ->orWhereHas('details', function ($detailQuery) use ($search): void {
                            $detailQuery->notDeleted()
                                ->whereHas('production', function ($productionQuery) use ($search): void {
                                    $productionQuery->notDeleted()
                                        ->where(function ($productionInner) use ($search): void {
                                            $productionInner->where('spk_no', 'like', "%{$search}%")
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
            ->through(fn (Resin $resin): array => $this->toListItem($resin));

        $spkEligibility = app(ResinSpkEligibility::class);

        return Inertia::render('resin/index', [
            'resins' => $resins,
            'spkStatusCounts' => $this->spkStatusCounts($spkEligibility),
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
            ],
        ]);
    }

    /**
     * Show the form for creating a new resin document.
     */
    public function create(Request $request, ResinApprovalService $approvalService): Response
    {
        return Inertia::render('resin/create', [
            'formDocumentNo' => (string) config('spk.resin_form_document_no'),
            'statusOptions' => $this->detailStatusOptions(),
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
     * Search SPKs for the resin form selector.
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
        $spkEligibility = app(ResinSpkEligibility::class);

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
            default => $query
                ->whereNotNull('spk_no'),
        };

        $query->with($this->productionSpkInfoRelations());

        $query->select([
            ...$this->productionSpkInfoColumns(),
            'gold_color',
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
        $resinRefs = in_array($queue, ['inProgress', 'completed'], true)
            ? $spkEligibility->resinRefsBySpkIds(
                $productions->pluck('row_id')->map(fn (mixed $id): int => (int) $id)->all(),
            )
            : [];

        return response()->json([
            'status' => true,
            'data' => $productions->map(function (Production $production) use ($resinRefs): array {
                $spkId = (int) $production->row_id;
                $resinRef = $resinRefs[$spkId] ?? null;

                return [
                    'rowId' => $spkId,
                    'spkNo' => (string) $production->spk_no,
                    'resinId' => $resinRef['resinId'] ?? null,
                    'docNo' => $resinRef['docNo'] ?? null,
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
                    ...$this->productionSpkInfoFields($production),
                ];
            })->values()->all(),
        ]);
    }

    /**
     * Store a newly created resin document.
     */
    public function store(
        StoreResinRequest $request,
        ResinDocNumberGenerator $docNumberGenerator,
    ): RedirectResponse {
        $validated = $request->validated();
        $actor = $this->actorName($request);
        $details = $validated['details'];

        $resin = DB::connection('third')->transaction(function () use ($validated, $details, $actor, $docNumberGenerator): Resin {
            $resin = Resin::query()->create([
                'doc_no' => $docNumberGenerator->generate(
                    Carbon::parse($validated['trans_date']),
                ),
                'operator' => $validated['operator'],
                'notes' => $validated['notes'] ?? null,
                'trans_date' => $validated['trans_date'],
                'spk_id' => $details[0]['spk_id'],
                'status' => 'DRAFT',
                'is_deleted' => 0,
                'created_date' => now(),
                'created_by' => $actor,
                'modified_date' => now(),
                'modified_by' => $actor,
            ]);

            $this->storeDetails($resin, $details, $actor);

            return $resin;
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Dokumen resin berhasil ditambahkan.',
        ]);

        return to_route('resin.show', $resin);
    }

    /**
     * Display the specified resin document.
     */
    public function show(
        Request $request,
        Resin $resin,
        ResinApprovalService $approvalService,
        ResinStatusMapper $statusMapper,
    ): Response {
        abort_if($resin->is_deleted === 1, 404);

        $resin->load($this->resinDetailEagerLoads());

        return Inertia::render('resin/show', [
            'approvalFooter' => $approvalService->footerColumns(
                $resin,
                $this->actorName($request),
            ),
            'approvalHistory' => $approvalService->history($resin),
            'approval' => $approvalService->abilitiesFor($resin, $request->user()),
            'workflowStatus' => $statusMapper->map($resin),
            'statusOptions' => $this->detailStatusOptions(),
            'saveProgressUrl' => route('resin.update-progress', $resin),
            'resinItem' => $this->toDetailItem($resin),
        ]);
    }

    /**
     * Kirim request Draft ke Manager Produksi (RSN010).
     */
    public function submit(
        Request $request,
        Resin $resin,
        ResinApprovalService $approvalService,
    ): RedirectResponse {
        abort_if($resin->is_deleted === 1, 404);

        if (! $approvalService->abilitiesFor($resin, $request->user())['canSubmit']) {
            abort(403, 'Request ini tidak dapat dikirim ke Manager Produksi.');
        }

        try {
            $approvalService->submit($resin, $this->actorName($request));
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['approval' => $exception->getMessage()]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Request Resin dikirim ke Manager Produksi.',
        ]);

        return to_route('resin.show', $resin);
    }

    /**
     * Manager Produksi meng-approve request (RSN010 → RSN020).
     */
    public function managerApprove(
        Request $request,
        Resin $resin,
        ResinApprovalService $approvalService,
    ): RedirectResponse {
        abort_if($resin->is_deleted === 1, 404);

        if (! $approvalService->abilitiesFor($resin, $request->user())['canManagerApprove']) {
            abort(403, 'Request ini tidak dapat di-approve oleh Manager Produksi.');
        }

        try {
            $approvalService->managerApprove($resin, $this->actorName($request));
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['approval' => $exception->getMessage()]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Request Resin di-approve oleh Manager Produksi.',
        ]);

        return to_route('resin.show', $resin);
    }

    /**
     * Menyelesaikan request (RSN020 → RSNDONE).
     */
    public function complete(
        Request $request,
        Resin $resin,
        ResinApprovalService $approvalService,
    ): RedirectResponse {
        abort_if($resin->is_deleted === 1, 404);

        if (! $approvalService->abilitiesFor($resin, $request->user())['canComplete']) {
            abort(403, 'Request ini tidak dapat diselesaikan.');
        }

        try {
            $approvalService->complete($resin, $this->actorName($request));
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['approval' => $exception->getMessage()]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Request Resin diselesaikan.',
        ]);

        return to_route('resin.show', $resin);
    }

    /**
     * Show the form for editing the specified resin document.
     */
    public function edit(
        Request $request,
        Resin $resin,
        ResinApprovalService $approvalService,
    ): Response {
        abort_if($resin->is_deleted === 1, 404);

        $resin->load($this->resinDetailEagerLoads());

        $resinItem = $this->toFormItem($resin);

        if (! filled($resinItem['operator'])) {
            $resinItem['operator'] = $this->defaultOperatorName($request);
        }

        return Inertia::render('resin/edit', [
            'formDocumentNo' => (string) config('spk.resin_form_document_no'),
            'statusOptions' => $this->detailStatusOptions(),
            'operatorOptions' => $this->operatorOptions(),
            'approvalFooter' => $approvalService->footerColumns(
                $resin,
                $this->actorName($request),
            ),
            'approval' => $approvalService->abilitiesFor($resin, $request->user()),
            'resinItem' => $resinItem,
        ]);
    }

    /**
     * Update the specified resin document.
     */
    public function update(
        UpdateResinRequest $request,
        Resin $resin,
        ResinApprovalService $approvalService,
    ): RedirectResponse {
        abort_if($resin->is_deleted === 1, 404);

        abort_unless(
            $approvalService->canEditForm($resin),
            403,
            'Dokumen resin tidak dapat diubah pada status saat ini.',
        );

        $validated = $request->validated();
        $actor = $this->actorName($request);
        $details = $validated['details'];

        DB::connection('third')->transaction(function () use ($resin, $validated, $details, $actor): void {
            $resin->update([
                'doc_no' => $validated['doc_no'],
                'operator' => $validated['operator'],
                'notes' => $validated['notes'] ?? null,
                'trans_date' => $validated['trans_date'],
                'spk_id' => $details[0]['spk_id'],
                'modified_date' => now(),
                'modified_by' => $actor,
            ]);

            $resin->details()
                ->notDeleted()
                ->update([
                    'is_deleted' => 1,
                    'deleted_date' => now(),
                    'deleted_by' => $actor,
                    'modified_date' => now(),
                    'modified_by' => $actor,
                ]);

            $this->storeDetails($resin, $details, $actor);
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Dokumen resin berhasil diperbarui.',
        ]);

        return to_route('resin.show', $resin);
    }

    /**
     * Update resin detail progress while status is Serahkan ke Resin.
     */
    public function updateProgress(
        UpdateResinProgressRequest $request,
        Resin $resin,
        ResinApprovalService $approvalService,
    ): RedirectResponse {
        abort_if($resin->is_deleted === 1, 404);

        if (! $approvalService->abilitiesFor($resin, $request->user())['canComplete']) {
            abort(403, 'Progress resin hanya dapat diperbarui saat status Serahkan ke Resin.');
        }

        $validated = $request->validated();
        $actor = $this->actorName($request);

        DB::connection('third')->transaction(function () use ($resin, $validated, $actor): void {
            foreach ($validated['details'] as $detail) {
                $resin->details()
                    ->notDeleted()
                    ->where('spk_id', $detail['spk_id'])
                    ->update([
                        'berat_resin' => filled($detail['berat_resin'] ?? null)
                            ? $detail['berat_resin']
                            : null,
                        'status_resin' => filled($detail['status_resin'] ?? null)
                            ? (string) $detail['status_resin']
                            : null,
                        'catatan' => filled($detail['catatan'] ?? null)
                            ? (string) $detail['catatan']
                            : null,
                        'modified_date' => now(),
                        'modified_by' => $actor,
                    ]);
            }

            $resin->update([
                'modified_date' => now(),
                'modified_by' => $actor,
            ]);
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Progress resin berhasil disimpan.',
        ]);

        return to_route('resin.show', $resin);
    }

    /**
     * Soft-delete the specified resin document.
     */
    public function destroy(
        Request $request,
        Resin $resin,
        ResinApprovalService $approvalService,
    ): RedirectResponse {
        abort_if($resin->is_deleted === 1, 404);

        if (! $approvalService->abilitiesFor($resin, $request->user())['canDelete']) {
            abort(403, 'Request ini tidak dapat dihapus.');
        }

        $actor = $this->actorName($request);

        DB::connection('third')->transaction(function () use ($resin, $actor): void {
            $resin->update([
                'is_deleted' => 1,
                'deleted_date' => now(),
                'deleted_by' => $actor,
                'modified_date' => now(),
                'modified_by' => $actor,
            ]);

            $resin->details()
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
            'message' => 'Dokumen resin berhasil dihapus.',
        ]);

        return to_route('resin.index');
    }

    /**
     * @param  list<array{
     *     spk_id: int,
     *     berat_resin?: string|null,
     *     status_resin: string,
     *     catatan?: string|null
     * }>  $details
     */
    private function storeDetails(Resin $resin, array $details, string $actor): void
    {
        $spkEligibility = app(ResinSpkEligibility::class);

        foreach ($details as $detail) {
            $production = Production::query()
                ->notDeleted()
                ->where('row_id', $detail['spk_id'])
                ->first();

            if ($production !== null) {
                $spkEligibility->markProcessStarted($production, $actor);
            }

            $resin->details()->create([
                'spk_id' => $detail['spk_id'],
                'berat_resin' => filled($detail['berat_resin'] ?? null)
                    ? $detail['berat_resin']
                    : null,
                'status_resin' => filled($detail['status_resin'] ?? null)
                    ? (string) $detail['status_resin']
                    : null,
                'catatan' => filled($detail['catatan'] ?? null)
                    ? (string) $detail['catatan']
                    : null,
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
     *     statusLabel: string,
     *     notes: string|null,
     *     spkNos: list<string>,
     *     totalBeratResin: string|null
     * }
     */
    private function toListItem(Resin $resin): array
    {
        $detailRows = $resin->details
            ->filter(fn (ResinDetail $detail): bool => $detail->is_deleted === 0)
            ->values();

        $notes = filled($resin->notes) ? (string) $resin->notes : null;

        if ($detailRows->isEmpty() && filled($resin->spk_id)) {
            return [
                'id' => (int) $resin->row_id,
                'docNo' => $resin->doc_no,
                'transDate' => $resin->trans_date?->format('Y-m-d'),
                'status' => $resin->status,
                'statusLabel' => app(ResinApprovalService::class)->statusLabelFor($resin),
                'notes' => $notes,
                'spkNos' => filled($resin->production?->spk_no)
                    ? [(string) $resin->production->spk_no]
                    : [],
                'totalBeratResin' => null,
            ];
        }

        return [
            'id' => (int) $resin->row_id,
            'docNo' => $resin->doc_no,
            'transDate' => $resin->trans_date?->format('Y-m-d'),
            'status' => $resin->status,
            'statusLabel' => app(ResinApprovalService::class)->statusLabelFor($resin),
            'notes' => $notes,
            'spkNos' => $detailRows
                ->map(fn (ResinDetail $detail): ?string => $detail->production?->spk_no)
                ->filter()
                ->map(fn (mixed $spkNo): string => (string) $spkNo)
                ->values()
                ->all(),
            'totalBeratResin' => $this->totalBeratResin($detailRows),
        ];
    }

    /**
     * @param  Collection<int, ResinDetail>  $detailRows
     */
    private function totalBeratResin(Collection $detailRows): ?string
    {
        $weights = $detailRows
            ->map(fn (ResinDetail $detail): ?float => $detail->berat_resin !== null
                ? (float) $detail->berat_resin
                : null)
            ->filter(fn (?float $weight): bool => $weight !== null);

        if ($weights->isEmpty()) {
            return null;
        }

        return number_format((float) $weights->sum(), 3, '.', '');
    }

    /**
     * @return array{
     *     id: int,
     *     docNo: string|null,
     *     transDate: string|null,
     *     status: string|null,
     *     statusLabel: string,
     *     operator: string|null,
     *     notes: string|null,
     *     details: list<array{
     *         spkId: int,
     *         spkNo: string|null,
     *         itemName: string|null,
     *         customerName: string|null,
     *         beratResin: string,
     *         statusResin: string|null,
     *         statusResinLabel: string,
     *         catatan: string|null
     *     }>
     * }
     */
    private function toDetailItem(Resin $resin): array
    {
        return [
            ...$this->toFormItem($resin),
            'statusLabel' => app(ResinApprovalService::class)->statusLabelFor($resin),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resinDetailEagerLoads(): array
    {
        return [
            'production' => fn ($query) => $query
                ->notDeleted()
                ->with($this->productionSpkInfoRelations())
                ->select($this->productionSpkInfoColumns()),
            'details' => fn ($query) => $query
                ->notDeleted()
                ->with([
                    'production' => fn ($productionQuery) => $productionQuery
                        ->notDeleted()
                        ->with($this->productionSpkInfoRelations())
                        ->select($this->productionSpkInfoColumns()),
                ])
                ->orderBy('line_id'),
        ];
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
     *     id: int,
     *     docNo: string|null,
     *     transDate: string|null,
     *     status: string|null,
     *     operator: string|null,
     *     notes: string|null,
     *     details: list<array{
     *         spkId: int,
     *         spkNo: string|null,
     *         skuCode: string|null,
     *         typeCode: string|null,
     *         productItemName: string|null,
     *         itemDescription: string|null,
     *         customerName: string|null,
     *         satuan: string,
     *         beratResin: string,
     *         statusResin: string|null,
     *         statusResinLabel: string,
     *         catatan: string|null
     *     }>
     * }
     */
    private function toFormItem(Resin $resin): array
    {
        $detailRows = $resin->details
            ->filter(fn (ResinDetail $detail): bool => $detail->is_deleted === 0)
            ->values();

        if ($detailRows->isEmpty() && filled($resin->spk_id)) {
            $detailRows = collect([
                [
                    'spkId' => (int) $resin->spk_id,
                    'spkNo' => $resin->production?->spk_no,
                    ...$this->productionSpkInfoFields($resin->production),
                    'beratResin' => '',
                    'statusResin' => filled($resin->status) ? (string) $resin->status : null,
                    'statusResinLabel' => $this->detailStatusLabel(
                        filled($resin->status) ? (string) $resin->status : null,
                    ),
                    'catatan' => null,
                ],
            ]);
        } else {
            $detailRows = $detailRows->map(fn (ResinDetail $detail): array => [
                'spkId' => (int) $detail->spk_id,
                'spkNo' => $detail->production?->spk_no,
                ...$this->productionSpkInfoFields($detail->production),
                'beratResin' => $detail->berat_resin !== null
                    ? number_format((float) $detail->berat_resin, 3, '.', '')
                    : '',
                'statusResin' => filled($detail->status_resin)
                    ? (string) $detail->status_resin
                    : null,
                'statusResinLabel' => $this->detailStatusLabel(
                    filled($detail->status_resin) ? (string) $detail->status_resin : null,
                ),
                'catatan' => filled($detail->catatan) ? (string) $detail->catatan : null,
            ]);
        }

        return [
            'id' => (int) $resin->row_id,
            'docNo' => $resin->doc_no,
            'transDate' => $resin->trans_date?->format('Y-m-d'),
            'status' => $resin->status,
            'operator' => filled($resin->operator) ? (string) $resin->operator : null,
            'notes' => filled($resin->notes) ? (string) $resin->notes : null,
            'details' => $detailRows->values()->all(),
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

    /**
     * @return array{
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

    /**
     * @return list<array{value: string, label: string}>
     */
    private function detailStatusOptions(): array
    {
        /** @var list<array{value: string, label: string}> $options */
        $options = config('spk.resin_detail_statuses', []);

        return $options;
    }

    private function detailStatusLabel(?string $status): string
    {
        if (! filled($status)) {
            return '—';
        }

        $matched = collect($this->detailStatusOptions())
            ->first(fn (array $option): bool => $option['value'] === $status);

        return $matched['label'] ?? $status;
    }

    private function actorName(Request $request): string
    {
        return $request->user()?->name ?? 'system';
    }

    /**
     * @return array{pending: int, inProgress: int, completed: int}
     */
    private function spkStatusCounts(ResinSpkEligibility $spkEligibility): array
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
