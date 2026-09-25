<?php

namespace App\Http\Controllers;

use App\Http\Requests\BulkUpdateResinStatusRequest;
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
use App\Support\SpkApprovalRoles;
use App\Support\SpkQtyUnit;
use Carbon\Carbon;
use DateTimeImmutable;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class ResinController extends Controller
{
    /**
     * Display a listing of resin documents (one row per SPK detail).
     */
    public function index(Request $request, ResinSpkEligibility $spkEligibility): Response
    {
        $search = $request->string('search')->trim()->toString();
        $sort = $this->resolveIndexSort($request->string('sort')->toString());
        $direction = $this->resolveIndexDirection($request->string('direction')->toString());
        $statusFilters = $this->resolveStatusFilters($request->input('status'));
        $dateFrom = $this->resolveIndexDate($request->string('date_from')->toString());
        $dateTo = $this->resolveIndexDate($request->string('date_to')->toString());
        $operator = $this->resolveOperatorFilter($request->string('operator')->toString());
        $perPage = $this->resolveIndexPerPage($request->integer('per_page', 50));

        $rows = ResinDetail::query()
            ->notDeleted()
            ->whereHas('resin', function ($query) use (
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
                                    ->orWhereRaw("UPPER(TRIM(status)) IN ('DRAFT', 'OPEN', '-', '".Resin::STATUS_OPEN."')");
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
                    $innerQuery->where('catatan', 'like', "%{$search}%")
                        ->orWhere('berat_resin', 'like', "%{$search}%")
                        ->orWhereHas('production', function ($productionQuery) use ($search): void {
                            $productionQuery->notDeleted()
                                ->where(function ($productionInner) use ($search): void {
                                    $productionInner->where('spk_no', 'like', "%{$search}%")
                                        ->orWhere('item_name', 'like', "%{$search}%")
                                        ->orWhere('customer_name', 'like', "%{$search}%")
                                        ->orWhere('gold_color', 'like', "%{$search}%");
                                });
                        })
                        ->orWhereHas('resin', function ($resinQuery) use ($search): void {
                            $resinQuery->notDeleted()
                                ->where(function ($resinInner) use ($search): void {
                                    $resinInner->where('doc_no', 'like', "%{$search}%")
                                        ->orWhere('notes', 'like', "%{$search}%")
                                        ->orWhere('operator', 'like', "%{$search}%")
                                        ->orWhere('status', 'like', "%{$search}%");
                                });
                        });
                });
            })
            ->with([
                'resin',
                'production' => fn ($productionQuery) => $productionQuery
                    ->notDeleted()
                    ->select([
                        'row_id',
                        'spk_no',
                        'item_name',
                        'customer_name',
                        'qty',
                        'satuan',
                        'sku_id',
                        'category_prefix_id',
                        'description',
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
            ->through(fn (ResinDetail $detail): array => $this->toListItem($detail));

        return Inertia::render('resin/index', [
            'resins' => $rows,
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
                    ['value' => 'manager', 'label' => 'Serahkan ke Resin'],
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
                'is_from_new_system' => 1,
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
     * Bulk update / soft-delete selected resin documents.
     */
    public function bulkUpdateStatus(
        BulkUpdateResinStatusRequest $request,
        ResinApprovalService $approvalService,
    ): RedirectResponse {
        $validated = $request->validated();
        /** @var list<int> $ids */
        $ids = array_values(array_unique(array_map(intval(...), $validated['ids'])));
        /** @var 'submit'|'manager_approve'|'complete'|'delete' $action */
        $action = $validated['action'];
        $actor = $this->actorName($request);
        $user = $request->user();

        $documents = Resin::query()
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
                    'delete' => $this->softDeleteResin($document, $actor),
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

        $this->softDeleteResin($resin, $this->actorName($request));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Dokumen resin berhasil dihapus.',
        ]);

        return to_route('resin.index');
    }

    private function softDeleteResin(Resin $resin, string $actor): void
    {
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
     *     resinId: int,
     *     docNo: string|null,
     *     transDate: string|null,
     *     operator: string|null,
     *     status: string|null,
     *     statusLabel: string|null,
     *     notes: string|null,
     *     spkNo: string|null,
     *     spkId: int|null,
     *     skuCode: string|null,
     *     typeCode: string|null,
     *     productItemName: string|null,
     *     itemDescription: string|null,
     *     beratResin: string|null,
     *     statusResin: string|null,
     *     statusResinLabel: string
     * }
     */
    private function toListItem(ResinDetail $detail): array
    {
        $resin = $detail->resin;
        $production = $detail->production;
        $typeCode = trim((string) ($production?->categoryPrefix?->category ?? ''));
        $productItemName = trim((string) ($production?->sku?->item_original ?? ''));

        if ($productItemName === '') {
            $productItemName = trim((string) ($production?->item_name ?? ''));
        }

        $itemDescription = trim((string) ($production?->description ?? ''));
        $statusResin = filled($detail->status_resin)
            ? (string) $detail->status_resin
            : null;

        return [
            'id' => (int) $detail->line_id,
            'resinId' => (int) $detail->row_id,
            'docNo' => $resin?->doc_no,
            'transDate' => $resin?->trans_date?->format('Y-m-d'),
            'operator' => filled($resin?->operator) ? (string) $resin->operator : null,
            'status' => $resin?->status,
            'statusLabel' => $resin !== null
                ? app(ResinApprovalService::class)->statusLabelFor($resin)
                : null,
            'notes' => filled($resin?->notes) ? (string) $resin->notes : null,
            'spkNo' => filled($production?->spk_no) ? (string) $production->spk_no : null,
            'spkId' => filled($detail->spk_id) ? (int) $detail->spk_id : null,
            'skuCode' => filled($production?->sku?->sku_code)
                ? (string) $production->sku->sku_code
                : null,
            'typeCode' => $typeCode !== '' ? $typeCode : null,
            'productItemName' => $productItemName !== '' ? $productItemName : null,
            'itemDescription' => $itemDescription !== '' ? $itemDescription : null,
            'beratResin' => $detail->berat_resin !== null
                ? number_format((float) $detail->berat_resin, 2, '.', '')
                : null,
            'statusResin' => $statusResin,
            'statusResinLabel' => $this->detailStatusLabel($statusResin),
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
            'submitted' => [ResinApprovalService::STATUS_SUBMITTED],
            'manager' => [ResinApprovalService::STATUS_MANAGER],
            'done' => [ResinApprovalService::STATUS_DONE, Resin::STATUS_DONE],
        ];
    }

    /**
     * @param  Builder<ResinDetail>  $query
     */
    private function applyIndexSort(Builder $query, string $sort, string $direction): void
    {
        $ascending = $direction === 'asc';
        $order = $ascending ? 'asc' : 'desc';

        match ($sort) {
            'date' => $query
                ->orderBy(
                    Resin::query()
                        ->select('trans_date')
                        ->whereColumn('resin.row_id', 'resindetails.row_id')
                        ->limit(1),
                    $order,
                )
                ->orderBy('line_id', $order),
            'spk' => $query
                ->orderBy(
                    Production::query()
                        ->select('spk_no')
                        ->whereColumn('spk.row_id', 'resindetails.spk_id')
                        ->limit(1),
                    $order,
                )
                ->orderBy('line_id', $order),
            'operator' => $query
                ->orderBy(
                    Resin::query()
                        ->select('operator')
                        ->whereColumn('resin.row_id', 'resindetails.row_id')
                        ->limit(1),
                    $order,
                )
                ->orderBy('line_id', $order),
            default => $query
                ->orderBy(
                    Resin::query()
                        ->select('doc_no')
                        ->whereColumn('resin.row_id', 'resindetails.row_id')
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
        return Resin::query()
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
