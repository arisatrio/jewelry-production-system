<?php

namespace App\Http\Controllers;

use App\Http\Requests\BulkUpdateFinishingStatusRequest;
use App\Http\Requests\StoreFinishingRequest;
use App\Http\Requests\UpdateFinishingRequest;
use App\Models\FinishingHandmade;
use App\Models\Production;
use App\Support\FinishingApprovalService;
use App\Support\FinishingDocNumberGenerator;
use App\Support\FinishingMaterialBreakdown;
use App\Support\FinishingMaterialGoldSynchronizer;
use App\Support\FinishingSpkEligibility;
use App\Support\ProductionOrderTypeLabel;
use App\Support\SpkApprovalRoles;
use App\Support\SpkQtyUnit;
use DateTimeImmutable;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class FinishingController extends Controller
{
    /**
     * Display a listing of finishing documents.
     */
    public function index(Request $request, FinishingSpkEligibility $spkEligibility): Response
    {
        $search = $request->string('search')->trim()->toString();
        $sort = $this->resolveIndexSort($request->string('sort')->toString());
        $direction = $this->resolveIndexDirection($request->string('direction')->toString());
        $processFilters = $this->resolveProcessFilters($request->input('process'));
        $statusFilters = $this->resolveStatusFilters($request->input('status'));
        $dateFrom = $this->resolveIndexDate($request->string('date_from')->toString());
        $dateTo = $this->resolveIndexDate($request->string('date_to')->toString());
        $craftsmanId = $this->resolveCraftsmanFilter($request->input('craftsman'));
        $perPage = $this->resolveIndexPerPage($request->integer('per_page', 50));

        if ($dateFrom !== null && $dateTo !== null && $dateTo < $dateFrom) {
            $dateTo = $dateFrom;
        }

        $documents = FinishingHandmade::query()
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
                        ->orWhere('process_name', 'like', "%{$search}%")
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
            ->when($processFilters !== [], function ($query) use ($processFilters): void {
                $query->whereIn('process_name', $processFilters);
            })
            ->when($statusFilters !== [], function ($query) use ($statusFilters): void {
                $statusCodes = collect($statusFilters)
                    ->flatMap(fn (string $statusKey): array => $this->statusFilterCodes()[$statusKey] ?? [])
                    ->unique()
                    ->values()
                    ->all();
                $includeOpenUnset = in_array('open', $statusFilters, true);

                if ($statusCodes === [] && ! $includeOpenUnset) {
                    return;
                }

                $query->where(function ($statusQuery) use ($statusCodes, $includeOpenUnset): void {
                    if ($statusCodes !== []) {
                        $statusQuery->whereIn('status', $statusCodes);
                    }

                    if ($includeOpenUnset) {
                        $statusQuery->orWhereNull('status')
                            ->orWhere('status', '')
                            ->orWhereRaw("UPPER(TRIM(status)) IN ('DRAFT', 'OPEN', '-')");
                    }
                });
            })
            ->when($dateFrom !== null, function ($query) use ($dateFrom): void {
                $query->whereDate('send_craftsman_date', '>=', $dateFrom);
            })
            ->when($dateTo !== null, function ($query) use ($dateTo): void {
                $query->whereDate('send_craftsman_date', '<=', $dateTo);
            })
            ->when($craftsmanId !== null, function ($query) use ($craftsmanId): void {
                $query->where('craftsman_id', $craftsmanId);
            })
            ->tap(fn ($query) => $this->applyIndexSort($query, $sort, $direction))
            ->paginate($perPage)
            ->withQueryString();

        $craftsmanNames = $this->resolveCraftsmanNames(
            $documents->getCollection()
                ->pluck('craftsman_id')
                ->all(),
        );

        $documents->setCollection(
            $documents->getCollection()
                ->map(fn (FinishingHandmade $document): array => $this->toListItem(
                    $document,
                    $craftsmanNames,
                ))
                ->values(),
        );

        return Inertia::render('finishing/index', [
            'documents' => $documents,
            'spkStatusCounts' => $this->spkStatusCounts($spkEligibility),
            'filters' => [
                'search' => $search,
                'sort' => $sort,
                'direction' => $direction,
                'process' => $processFilters,
                'status' => $statusFilters,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'craftsman' => $craftsmanId,
                'per_page' => $perPage,
            ],
            'filterOptions' => [
                'process' => $this->processOptions(),
                'status' => [
                    ['value' => 'open', 'label' => 'Open / Pengajuan'],
                    ['value' => 'ppic', 'label' => 'Serahkan ke PPIC'],
                    ['value' => 'done', 'label' => 'Completed'],
                ],
                'craftsman' => $this->craftsmanOptions(),
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
                    ['value' => 'craftsman', 'label' => 'Nama Pengrajin'],
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
     * Show the form for creating a new finishing document.
     */
    public function create(FinishingMaterialGoldSynchronizer $materialSynchronizer): Response
    {
        return Inertia::render('finishing/create', [
            'formDocumentNo' => (string) config('spk.finishing_form_document_no'),
            'processOptions' => $this->processOptions(),
            'itemCategoryOptions' => $this->itemCategoryOptions(),
            'craftsmanOptions' => $this->craftsmanOptions(),
            'materialOptions' => $materialSynchronizer->materialOptions(),
            'form' => [
                'sendCraftsmanDate' => now()->format('Y-m-d H:i'),
                'receivedCraftsmanDate' => '',
                'processName' => 'Finishing',
                'craftsmanId' => null,
                'itemCategory' => null,
                'notes' => '',
                'startWeight' => '',
                'finishWeight' => '',
                'spk' => null,
                'materials' => [],
            ],
        ]);
    }

    /**
     * Search SPKs for the finishing form selector.
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
        $spkEligibility = app(FinishingSpkEligibility::class);

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
        $finishingRefs = in_array($queue, ['inProgress', 'completed'], true)
            ? $spkEligibility->finishingRefsBySpkIds(
                $productions->pluck('row_id')->map(fn (mixed $id): int => (int) $id)->all(),
            )
            : [];

        return response()->json([
            'status' => true,
            'data' => $productions->map(function (Production $production) use ($finishingRefs): array {
                $spkId = (int) $production->row_id;
                $finishingRef = $finishingRefs[$spkId] ?? null;

                return [
                    'rowId' => $spkId,
                    'spkNo' => (string) $production->spk_no,
                    'finishingId' => $finishingRef['finishingId'] ?? null,
                    'docNo' => $finishingRef['docNo'] ?? null,
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

    /**
     * Store a newly created finishing document.
     */
    public function store(
        StoreFinishingRequest $request,
        FinishingDocNumberGenerator $docNumberGenerator,
        FinishingMaterialGoldSynchronizer $materialSynchronizer,
    ): RedirectResponse {
        $validated = $request->validated();
        $actor = $this->actorName($request);
        $materials = $validated['materials'] ?? [];

        $document = DB::connection('third')->transaction(function () use (
            $validated,
            $materials,
            $actor,
            $docNumberGenerator,
            $materialSynchronizer,
        ): FinishingHandmade {
            $document = FinishingHandmade::query()->create([
                'doc_no' => $docNumberGenerator->generate(),
                'spk_id' => $validated['spk_id'],
                'process_name' => $validated['process_name'],
                'craftsman_id' => $validated['craftsman_id'] ?? null,
                'start_weight' => $validated['start_weight'] ?? null,
                'finish_weight' => $validated['finish_weight'] ?? null,
                'submit_materialgold' => '0.000',
                'result_materialgold' => '0.000',
                'shrink' => '0.000',
                'shrink_tolerance' => null,
                'send_craftsman_date' => $validated['send_craftsman_date'] ?? null,
                'received_craftsman_date' => $validated['received_craftsman_date'] ?? null,
                'item_category' => $validated['item_category'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'status' => null,
                'is_from_new_system' => 1,
                'is_deleted' => 0,
                'created_date' => now(),
                'created_by' => $actor,
                'modified_date' => now(),
                'modified_by' => $actor,
                'koreksi_qc' => 0,
            ]);

            $production = Production::query()
                ->notDeleted()
                ->where('row_id', $validated['spk_id'])
                ->first();

            if ($production !== null) {
                $eligibility = app(FinishingSpkEligibility::class);
                $eligibility->markProcessStarted($production, $actor);
                $eligibility->syncLastWeight(
                    $production,
                    $validated['finish_weight'] ?? null,
                    $actor,
                );
            }

            $materialSynchronizer->sync($document, $materials, $actor);
            $this->recalculateShrink($document->refresh());

            return $document->refresh();
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Dokumen finishing berhasil ditambahkan.',
        ]);

        return to_route('finishing.show', $document);
    }

    /**
     * Display the specified finishing document.
     */
    public function show(
        Request $request,
        FinishingHandmade $finishing,
        FinishingApprovalService $approvalService,
        FinishingMaterialBreakdown $materialBreakdown,
    ): Response {
        abort_if($finishing->is_deleted === 1, 404);

        $finishing->load([
            'production' => fn ($productionQuery) => $productionQuery
                ->notDeleted()
                ->with($this->productionSpkInfoRelations())
                ->select($this->productionSpkInfoColumns()),
        ]);

        return Inertia::render('finishing/show', [
            'finishingItem' => $this->toDetailItem($finishing, $materialBreakdown),
            'workflowStatus' => $approvalService->map($finishing),
            'approvalHistory' => $approvalService->history($finishing),
            'approvalFooter' => $approvalService->footerColumns(
                $finishing,
                $this->actorName($request),
            ),
            'approval' => $approvalService->abilitiesFor($finishing, $request->user()),
        ]);
    }

    /**
     * Show the form for editing the specified finishing document.
     */
    public function edit(
        Request $request,
        FinishingHandmade $finishing,
        FinishingApprovalService $approvalService,
        FinishingMaterialGoldSynchronizer $materialSynchronizer,
    ): Response {
        abort_if($finishing->is_deleted === 1, 404);
        abort_unless(
            $approvalService->abilitiesFor($finishing, $request->user())['canEdit'],
            403,
            'Dokumen finishing tidak dapat diubah pada status saat ini.',
        );

        $finishing->load([
            'production' => fn ($productionQuery) => $productionQuery
                ->notDeleted()
                ->with($this->productionSpkInfoRelations())
                ->select($this->productionSpkInfoColumns()),
        ]);

        $production = $finishing->production;

        return Inertia::render('finishing/edit', [
            'formDocumentNo' => (string) config('spk.finishing_form_document_no'),
            'processOptions' => $this->processOptions(),
            'itemCategoryOptions' => $this->itemCategoryOptions(),
            'craftsmanOptions' => $this->craftsmanOptions(),
            'materialOptions' => $materialSynchronizer->materialOptions(),
            'form' => [
                'id' => (int) $finishing->row_id,
                'docNo' => $finishing->doc_no,
                'sendCraftsmanDate' => $finishing->send_craftsman_date?->format('Y-m-d H:i') ?? '',
                'receivedCraftsmanDate' => $finishing->received_craftsman_date?->format('Y-m-d H:i') ?? '',
                'processName' => filled($finishing->process_name)
                    ? (string) $finishing->process_name
                    : 'Finishing',
                'craftsmanId' => filled($finishing->craftsman_id) && (int) $finishing->craftsman_id > 0
                    ? (int) $finishing->craftsman_id
                    : null,
                'itemCategory' => filled($finishing->item_category)
                    ? (string) $finishing->item_category
                    : null,
                'notes' => filled($finishing->notes) ? (string) $finishing->notes : '',
                'startWeight' => $this->formatDecimal($finishing->start_weight) ?? '',
                'finishWeight' => $this->formatDecimal($finishing->finish_weight) ?? '',
                'spk' => $production === null ? null : [
                    'spkId' => (int) $production->row_id,
                    'spkNo' => $production->spk_no,
                    ...$this->productionSpkInfoFields($production),
                ],
                'materials' => $materialSynchronizer->formLinesFor($finishing),
            ],
            'approval' => $approvalService->abilitiesFor($finishing, $request->user()),
        ]);
    }

    /**
     * Kirim dokumen Open ke Manager Produksi.
     */
    public function submit(
        Request $request,
        FinishingHandmade $finishing,
        FinishingApprovalService $approvalService,
    ): RedirectResponse {
        abort_if($finishing->is_deleted === 1, 404);

        if (! $approvalService->abilitiesFor($finishing, $request->user())['canSubmit']) {
            abort(403, 'Dokumen ini tidak dapat dikirim ke Manager Produksi.');
        }

        try {
            $approvalService->submit($finishing, $this->actorName($request));
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['approval' => $exception->getMessage()]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Dokumen finishing dikirim ke Manager Produksi.',
        ]);

        return to_route('finishing.show', $finishing);
    }

    /**
     * Manager Produksi meng-approve dokumen.
     */
    public function managerApprove(
        Request $request,
        FinishingHandmade $finishing,
        FinishingApprovalService $approvalService,
    ): RedirectResponse {
        abort_if($finishing->is_deleted === 1, 404);

        if (! $approvalService->abilitiesFor($finishing, $request->user())['canManagerApprove']) {
            abort(403, 'Dokumen ini tidak dapat di-approve oleh Manager Produksi.');
        }

        try {
            $approvalService->managerApprove($finishing, $this->actorName($request));
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['approval' => $exception->getMessage()]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Dokumen finishing di-approve oleh Manager Produksi.',
        ]);

        return to_route('finishing.show', $finishing);
    }

    /**
     * Selesaikan dokumen finishing.
     */
    public function complete(
        Request $request,
        FinishingHandmade $finishing,
        FinishingApprovalService $approvalService,
    ): RedirectResponse {
        abort_if($finishing->is_deleted === 1, 404);

        if (! $approvalService->abilitiesFor($finishing, $request->user())['canComplete']) {
            abort(403, 'Dokumen ini tidak dapat diselesaikan.');
        }

        try {
            $approvalService->complete($finishing, $this->actorName($request));
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['approval' => $exception->getMessage()]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Dokumen finishing selesai.',
        ]);

        return to_route('finishing.show', $finishing);
    }

    /**
     * Bulk update finishing document statuses via workflow actions.
     */
    public function bulkUpdateStatus(
        BulkUpdateFinishingStatusRequest $request,
        FinishingApprovalService $approvalService,
    ): RedirectResponse {
        $validated = $request->validated();
        /** @var list<int> $ids */
        $ids = array_map(intval(...), $validated['ids']);
        /** @var 'submit'|'manager_approve'|'complete'|'delete' $action */
        $action = $validated['action'];
        $actor = $this->actorName($request);
        $user = $request->user();

        $documents = FinishingHandmade::query()
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
                    'delete' => $this->softDeleteFinishing($document, $actor),
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
            ? "{$updated} dokumen berhasil {$verb} ({$actionLabel})."
            : "Tidak ada dokumen yang dapat {$verb} ({$actionLabel}).";

        if ($skipped > 0) {
            $message .= " {$skipped} dilewati.";
        }

        Inertia::flash('toast', [
            'type' => $updated > 0 ? 'success' : 'warning',
            'message' => $message,
        ]);

        return back();
    }

    private function softDeleteFinishing(FinishingHandmade $finishing, string $actor): void
    {
        DB::connection('third')->transaction(function () use ($finishing, $actor): void {
            $finishing->update([
                'is_deleted' => 1,
                'deleted_date' => now(),
                'deleted_by' => $actor,
                'modified_date' => now(),
                'modified_by' => $actor,
            ]);

            if (
                Schema::connection('third')->hasTable('trmaterialgold')
                && Schema::connection('third')->hasColumn('trmaterialgold', 'is_deleted')
            ) {
                DB::connection('third')
                    ->table('trmaterialgold')
                    ->where('ref_row_id', $finishing->row_id)
                    ->whereIn('transtype_id', FinishingMaterialGoldSynchronizer::transtypeIds())
                    ->where('is_deleted', 0)
                    ->update([
                        'is_deleted' => 1,
                        'deleted_date' => now(),
                        'deleted_by' => $actor,
                        'modified_date' => now(),
                        'modified_by' => $actor,
                    ]);
            }
        });
    }

    /**
     * Update the specified finishing document.
     */
    public function update(
        UpdateFinishingRequest $request,
        FinishingHandmade $finishing,
        FinishingApprovalService $approvalService,
        FinishingMaterialGoldSynchronizer $materialSynchronizer,
    ): RedirectResponse {
        abort_if($finishing->is_deleted === 1, 404);
        abort_unless(
            $approvalService->abilitiesFor($finishing, $request->user())['canEdit'],
            403,
            'Dokumen finishing tidak dapat diubah pada status saat ini.',
        );

        $validated = $request->validated();
        $actor = $this->actorName($request);
        $materials = $validated['materials'] ?? [];

        DB::connection('third')->transaction(function () use (
            $finishing,
            $validated,
            $materials,
            $actor,
            $materialSynchronizer,
        ): void {
            $finishing->update([
                'spk_id' => $validated['spk_id'],
                'process_name' => $validated['process_name'],
                'craftsman_id' => $validated['craftsman_id'] ?? null,
                'start_weight' => $validated['start_weight'] ?? null,
                'finish_weight' => $validated['finish_weight'] ?? null,
                'send_craftsman_date' => $validated['send_craftsman_date'] ?? null,
                'received_craftsman_date' => $validated['received_craftsman_date'] ?? null,
                'item_category' => $validated['item_category'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'modified_date' => now(),
                'modified_by' => $actor,
            ]);

            $production = Production::query()
                ->notDeleted()
                ->where('row_id', $validated['spk_id'])
                ->first();

            if ($production !== null) {
                app(FinishingSpkEligibility::class)->syncLastWeight(
                    $production,
                    $validated['finish_weight'] ?? null,
                    $actor,
                );
            }

            $materialSynchronizer->sync($finishing->refresh(), $materials, $actor);
            $this->recalculateShrink($finishing->refresh());
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Dokumen finishing berhasil diperbarui.',
        ]);

        return to_route('finishing.show', $finishing);
    }

    /**
     * @param  array<int, string>  $craftsmanNames
     * @return array{
     *     id: int,
     *     docNo: string|null,
     *     transDate: string|null,
     *     processName: string|null,
     *     status: string|null,
     *     statusLabel: string,
     *     spkNo: string|null,
     *     craftsmanName: string|null,
     *     sendCraftsmanDate: string|null,
     *     receivedCraftsmanDate: string|null,
     *     startWeight: string|null,
     *     finishWeight: string|null,
     *     submitMaterial: string|null,
     *     resultMaterial: string|null,
     *     shrink: string|null,
     *     shrinkTolerance: string|null,
     *     notes: string|null
     * }
     */
    private function toListItem(FinishingHandmade $document, array $craftsmanNames = []): array
    {
        $craftsmanId = filled($document->craftsman_id) ? (int) $document->craftsman_id : 0;

        return [
            'id' => (int) $document->row_id,
            'docNo' => $document->doc_no,
            'transDate' => $document->send_craftsman_date?->format('Y-m-d'),
            'processName' => filled($document->process_name)
                ? (string) $document->process_name
                : null,
            'status' => filled($document->status) ? (string) $document->status : null,
            'statusLabel' => $document->statusLabel(),
            'spkNo' => $document->production?->spk_no,
            'craftsmanName' => $craftsmanId > 0
                ? ($craftsmanNames[$craftsmanId] ?? "Pengrajin {$craftsmanId}")
                : null,
            'sendCraftsmanDate' => $document->send_craftsman_date?->format('Y-m-d H:i'),
            'receivedCraftsmanDate' => $document->received_craftsman_date?->format('Y-m-d H:i'),
            'startWeight' => $this->formatDecimal($document->start_weight),
            'finishWeight' => $this->formatDecimal($document->finish_weight),
            'submitMaterial' => $this->formatDecimal($document->submit_materialgold),
            'resultMaterial' => $this->formatDecimal($document->result_materialgold),
            'shrink' => $this->formatDecimal($document->shrink),
            'shrinkTolerance' => $this->formatDecimal($document->shrink_tolerance, 2),
            'notes' => filled($document->notes) ? (string) $document->notes : null,
        ];
    }

    /**
     * @return array{
     *     id: int,
     *     docNo: string|null,
     *     processName: string|null,
     *     status: string|null,
     *     statusLabel: string,
     *     craftsmanId: int|null,
     *     craftsmanName: string|null,
     *     sendCraftsmanDate: string|null,
     *     receivedCraftsmanDate: string|null,
     *     itemCategory: string|null,
     *     notes: string|null,
     *     startWeight: string|null,
     *     finishWeight: string|null,
     *     submitMaterial: string|null,
     *     resultMaterial: string|null,
     *     shrink: string|null,
     *     shrinkTolerance: string|null,
     *     shrinkPercent: string|null,
     *     koreksiQc: int|null,
     *     keteranganQc: string|null,
     *     materials: array{
     *         bahan: list<array{name: string, weight: float, notes: string|null}>,
     *         sisa: list<array{name: string, weight: float, notes: string|null}>
     *     },
     *     spk: array{
     *         spkId: int|null,
     *         spkNo: string|null,
     *         spkType: string|null,
     *         orderTypeLabel: string|null,
     *         skuCode: string|null,
     *         typeCode: string|null,
     *         productItemName: string|null,
     *         itemDescription: string|null,
     *         customerName: string|null,
     *         satuan: string
     *     }|null
     * }
     */
    private function toDetailItem(
        FinishingHandmade $document,
        FinishingMaterialBreakdown $materialBreakdown,
    ): array {
        $startWeight = $this->toFloat($document->start_weight);
        $shrink = $this->toFloat($document->shrink);
        $shrinkPercent = null;

        if ($shrink !== null && $startWeight !== null && abs($startWeight) >= 0.0005) {
            $shrinkPercent = number_format(($shrink / $startWeight) * 100, 2, '.', '').'%';
        }

        $production = $document->production;
        $materials = $materialBreakdown->forIds([(int) $document->row_id])[(int) $document->row_id]
            ?? $materialBreakdown->empty();

        return [
            'id' => (int) $document->row_id,
            'docNo' => $document->doc_no,
            'processName' => filled($document->process_name)
                ? (string) $document->process_name
                : null,
            'status' => filled($document->status) ? (string) $document->status : null,
            'statusLabel' => $document->statusLabel(),
            'craftsmanId' => filled($document->craftsman_id) && (int) $document->craftsman_id > 0
                ? (int) $document->craftsman_id
                : null,
            'craftsmanName' => $this->resolveCraftsmanName($document->craftsman_id),
            'sendCraftsmanDate' => $document->send_craftsman_date?->format('Y-m-d H:i:s'),
            'receivedCraftsmanDate' => $document->received_craftsman_date?->format('Y-m-d H:i:s'),
            'itemCategory' => filled($document->item_category)
                ? (string) $document->item_category
                : null,
            'notes' => filled($document->notes) ? (string) $document->notes : null,
            'startWeight' => $this->formatDecimal($document->start_weight),
            'finishWeight' => $this->formatDecimal($document->finish_weight),
            'submitMaterial' => $this->formatDecimal($document->submit_materialgold),
            'resultMaterial' => $this->formatDecimal($document->result_materialgold),
            'shrink' => $this->formatDecimal($document->shrink),
            'shrinkTolerance' => $this->formatDecimal($document->shrink_tolerance, 2),
            'shrinkPercent' => $shrinkPercent,
            'koreksiQc' => filled($document->koreksi_qc) ? (int) $document->koreksi_qc : null,
            'keteranganQc' => filled($document->keterangan_qc)
                ? (string) $document->keterangan_qc
                : null,
            'materials' => $materials,
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

        if ($id <= 0) {
            return null;
        }

        return $this->resolveCraftsmanNames([$id])[$id] ?? null;
    }

    /**
     * @param  list<mixed>  $craftsmanIds
     * @return array<int, string>
     */
    private function resolveCraftsmanNames(array $craftsmanIds): array
    {
        $ids = collect($craftsmanIds)
            ->map(fn (mixed $id): int => filled($id) ? (int) $id : 0)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($ids === [] || ! Schema::connection('third')->hasTable('mscraftsman')) {
            return [];
        }

        $names = DB::connection('third')
            ->table('mscraftsman')
            ->whereIn('row_id', $ids)
            ->pluck('name', 'row_id');

        $resolved = [];

        foreach ($ids as $id) {
            $name = $names[$id] ?? null;
            $resolved[$id] = filled($name) ? (string) $name : "Pengrajin {$id}";
        }

        return $resolved;
    }

    private function recalculateShrink(FinishingHandmade $document): void
    {
        $start = $this->toFloat($document->start_weight) ?? 0.0;
        $finish = $this->toFloat($document->finish_weight) ?? 0.0;
        $submit = $this->toFloat($document->submit_materialgold) ?? 0.0;
        $result = $this->toFloat($document->result_materialgold) ?? 0.0;

        $inputWeight = $start + $submit;
        $shrink = round($inputWeight - ($finish + $result), 3);

        $shrinkTolerance = abs($inputWeight) >= 0.0005
            ? round(($shrink / $inputWeight) * 100, 2)
            : 0.0;

        $document->forceFill([
            'shrink' => number_format($shrink, 2, '.', ''),
            'shrink_tolerance' => number_format($shrinkTolerance, 2, '.', ''),
        ])->save();
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function processOptions(): array
    {
        return collect(FinishingHandmade::processNameOptions())
            ->map(fn (string $value): array => [
                'value' => $value,
                'label' => $value,
            ])
            ->values()
            ->all();
    }

    private function resolveIndexSort(string $sort): string
    {
        $allowed = ['id', 'date', 'spk', 'craftsman'];

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

    private function resolveCraftsmanFilter(mixed $craftsman): ?int
    {
        if (! filled($craftsman) || ! is_numeric($craftsman)) {
            return null;
        }

        $id = (int) $craftsman;

        return $id > 0 ? $id : null;
    }

    private function resolveIndexPerPage(int $perPage): int
    {
        return in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 50;
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<FinishingHandmade>  $query
     */
    private function applyIndexSort($query, string $sort, string $direction): void
    {
        $ascending = $direction === 'asc';

        match ($sort) {
            'date' => $ascending
                ? $query->orderBy('send_craftsman_date')->orderBy('row_id')
                : $query->orderByDesc('send_craftsman_date')->orderByDesc('row_id'),
            'spk' => $query
                ->orderBy(
                    Production::query()
                        ->select('spk_no')
                        ->whereColumn('spk.row_id', 'finishinghandmade.spk_id')
                        ->limit(1),
                    $ascending ? 'asc' : 'desc',
                )
                ->orderBy('row_id', $ascending ? 'asc' : 'desc'),
            'craftsman' => $query
                ->orderBy(
                    DB::connection('third')
                        ->table('mscraftsman')
                        ->select('name')
                        ->whereColumn('mscraftsman.row_id', 'finishinghandmade.craftsman_id')
                        ->limit(1),
                    $ascending ? 'asc' : 'desc',
                )
                ->orderBy('row_id', $ascending ? 'asc' : 'desc'),
            default => $ascending
                ? $query->orderBy('doc_no')->orderBy('row_id')
                : $query->orderByDesc('doc_no')->orderByDesc('row_id'),
        };
    }

    /**
     * @return list<string>
     */
    private function resolveProcessFilters(mixed $process): array
    {
        $allowed = FinishingHandmade::processNameOptions();

        return collect(is_array($process) ? $process : (filled($process) ? [$process] : []))
            ->map(fn (mixed $value): string => trim((string) $value))
            ->filter(fn (string $value): bool => in_array($value, $allowed, true))
            ->unique()
            ->values()
            ->all();
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
            'open' => [
                FinishingHandmade::STATUS_OPEN,
                FinishingHandmade::STATUS_TO_CRAFTSMAN,
                FinishingHandmade::STATUS_REPARATION_OPEN,
                FinishingApprovalService::LEGACY_NEW_STATUS_SUBMITTED,
            ],
            'ppic' => [
                FinishingHandmade::STATUS_TO_PPIC,
                FinishingApprovalService::LEGACY_NEW_STATUS_MANAGER,
            ],
            'done' => [
                FinishingHandmade::STATUS_DONE,
                FinishingHandmade::STATUS_REPARATION_DONE,
                FinishingApprovalService::LEGACY_NEW_STATUS_DONE,
            ],
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function itemCategoryOptions(): array
    {
        return collect(FinishingHandmade::itemCategoryOptions())
            ->map(fn (string $value): array => [
                'value' => $value,
                'label' => $value,
            ])
            ->values()
            ->all();
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
    private function spkStatusCounts(FinishingSpkEligibility $spkEligibility): array
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
