<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BuildsPolishProcessReport;
use App\Http\Requests\BulkUpdatePolishFinishedGoodStatusRequest;
use App\Http\Requests\StorePolishFinishedGoodRequest;
use App\Http\Requests\UpdatePolishFinishedGoodRequest;
use App\Models\PolishFinishedGood;
use App\Models\Production;
use App\Support\PolishFinishedGoodApprovalService;
use App\Support\PolishFinishedGoodDocNumberGenerator;
use App\Support\PolishFinishedGoodSpkEligibility;
use App\Support\ProductionOrderTypeLabel;
use App\Support\SpkApprovalRoles;
use App\Support\SpkItemImageUrl;
use App\Support\SpkOrderReference;
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

class PolishFinishedGoodController extends Controller
{
    use BuildsPolishProcessReport;

    private const ALL_STATUS_FILTER = 'all';

    private const COMPLETED_STATUS_FILTER = 'done';

    public function index(Request $request, PolishFinishedGoodSpkEligibility $spkEligibility): Response
    {
        $search = $request->string('search')->trim()->toString();
        $sort = $this->resolveIndexSort($request->string('sort')->toString());
        $direction = $this->resolveIndexDirection($request->string('direction')->toString());
        $statusFilters = $this->resolveStatusFilters($request->input('status'));
        $dateFrom = $this->resolveIndexDate($request->string('date_from')->toString());
        $dateTo = $this->resolveIndexDate($request->string('date_to')->toString());
        $craftsmanIds = $this->resolveCraftsmanFilters($request->input('craftsman'));
        $perPage = $this->resolveIndexPerPage($request->integer('per_page', 50));

        if ($dateFrom !== null && $dateTo !== null && $dateTo < $dateFrom) {
            $dateTo = $dateFrom;
        }

        $documents = PolishFinishedGood::query()
            ->notDeleted()
            ->with([
                'production' => fn ($productionQuery) => $productionQuery
                    ->notDeleted()
                    ->with($this->productionSpkInfoRelations())
                    ->select([...$this->productionSpkInfoColumns(), 'file_name']),
            ])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($innerQuery) use ($search): void {
                    $innerQuery->where('doc_no', 'like', "%{$search}%")
                        ->orWhere('status', 'like', "%{$search}%")
                        ->orWhere('status_item', 'like', "%{$search}%")
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
            ->when($statusFilters !== [], function ($query) use ($statusFilters): void {
                $statusCodes = collect($statusFilters)
                    ->flatMap(fn (string $statusKey): array => $this->statusFilterCodes()[$statusKey] ?? [])
                    ->unique()
                    ->values()
                    ->all();
                $includeOpenUnset = in_array('open', $statusFilters, true);

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
            ->when($craftsmanIds !== [], function ($query) use ($craftsmanIds): void {
                $query->whereIn('craftsman_id', $craftsmanIds);
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
                ->map(fn (PolishFinishedGood $document): array => $this->toListItem(
                    $document,
                    $craftsmanNames,
                ))
                ->values(),
        );

        return Inertia::render('poles-chrome/index', [
            'documents' => $documents,
            'spkStatusCounts' => $this->spkStatusCounts($spkEligibility),
            'filters' => [
                'search' => $search,
                'sort' => $sort,
                'direction' => $direction,
                'status' => $statusFilters,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'craftsman' => $craftsmanIds,
                'per_page' => $perPage,
            ],
            'defaultFilters' => [
                'status' => $this->defaultStatusFilters(),
            ],
            'filterOptions' => [
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
     * Bulk update Poles Chrome document statuses via workflow actions.
     */
    public function bulkUpdateStatus(
        BulkUpdatePolishFinishedGoodStatusRequest $request,
        PolishFinishedGoodApprovalService $approvalService,
    ): RedirectResponse {
        $validated = $request->validated();
        /** @var list<int> $ids */
        $ids = array_map(intval(...), $validated['ids']);
        /** @var 'submit'|'manager_approve'|'complete'|'delete' $action */
        $action = $validated['action'];
        $actor = $this->actorName($request);
        $user = $request->user();

        $documents = PolishFinishedGood::query()
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
                    'delete' => $document->update([
                        'is_deleted' => 1,
                        'deleted_date' => now(),
                        'deleted_by' => $actor,
                        'modified_date' => now(),
                        'modified_by' => $actor,
                    ]),
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

    public function create(): Response
    {
        return Inertia::render('poles-chrome/create', [
            'formDocumentNo' => (string) config('spk.poles_chrome_form_document_no'),
            'craftsmanOptions' => $this->craftsmanOptions(),
            'statusItemOptions' => $this->statusItemOptions(),
            'form' => [
                'sendCraftsmanDate' => now()->format('Y-m-d H:i'),
                'receivedCraftsmanDate' => '',
                'craftsmanId' => null,
                'notes' => '',
                'statusItem' => null,
                'startWeight' => '',
                'finishWeight' => '',
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
        $spkEligibility = app(PolishFinishedGoodSpkEligibility::class);

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
            ? $spkEligibility->polishFinishedGoodRefsBySpkIds(
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
                    'polishFinishedGoodId' => $ref['polishFinishedGoodId'] ?? null,
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
        StorePolishFinishedGoodRequest $request,
        PolishFinishedGoodDocNumberGenerator $docNumberGenerator,
    ): RedirectResponse {
        $validated = $request->validated();
        $actor = $this->actorName($request);

        $document = DB::connection('third')->transaction(function () use (
            $validated,
            $actor,
            $docNumberGenerator,
        ): PolishFinishedGood {
            $sendDate = $validated['send_craftsman_date'] ?? null;
            $receivedDate = $validated['received_craftsman_date'] ?? null;

            $document = PolishFinishedGood::query()->create([
                'doc_no' => $docNumberGenerator->generate(),
                'process_name' => $validated['process_name'] ?? 'General',
                'date_from' => $sendDate,
                'spk_id' => $validated['spk_id'],
                'craftsman_id' => $validated['craftsman_id'] ?? null,
                'date_to' => $receivedDate,
                'start_weight' => $validated['start_weight'] ?? null,
                'finish_weight' => $validated['finish_weight'] ?? null,
                'shrink' => '0.00',
                'status_item' => $validated['status_item'] ?? null,
                'send_craftsman_date' => $sendDate,
                'received_craftsman_date' => $receivedDate,
                'qty_stone' => $validated['qty_stone'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'status' => null,
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
                $eligibility = app(PolishFinishedGoodSpkEligibility::class);
                $eligibility->markProcessStarted($production, $actor);
                $eligibility->syncLastWeight(
                    $production,
                    $validated['finish_weight'] ?? null,
                    $actor,
                );
            }

            $this->recalculateShrink($document->refresh());

            return $document->refresh();
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Dokumen Poles Chrome berhasil ditambahkan.',
        ]);

        return to_route('poles-chrome.show', $document);
    }

    public function show(
        Request $request,
        PolishFinishedGood $polesChrome,
        PolishFinishedGoodApprovalService $approvalService,
    ): Response {
        abort_if($polesChrome->is_deleted === 1, 404);

        $polesChrome->load([
            'production' => fn ($productionQuery) => $productionQuery
                ->notDeleted()
                ->with($this->productionSpkInfoRelations())
                ->select($this->productionSpkInfoColumns()),
        ]);

        return Inertia::render('poles-chrome/show', [
            'polishFinishedGoodItem' => $this->toDetailItem($polesChrome),
            'workflowStatus' => $approvalService->map($polesChrome),
            'approvalHistory' => $approvalService->history($polesChrome),
            'approvalFooter' => $approvalService->footerColumns(
                $polesChrome,
                $this->actorName($request),
            ),
            'approval' => $approvalService->abilitiesFor($polesChrome, $request->user()),
        ]);
    }

    public function edit(
        Request $request,
        PolishFinishedGood $polesChrome,
        PolishFinishedGoodApprovalService $approvalService,
    ): Response {
        abort_if($polesChrome->is_deleted === 1, 404);
        abort_unless(
            $approvalService->abilitiesFor($polesChrome, $request->user())['canEdit'],
            403,
            'Dokumen Poles Chrome tidak dapat diubah pada status saat ini.',
        );

        $polesChrome->load([
            'production' => fn ($productionQuery) => $productionQuery
                ->notDeleted()
                ->with($this->productionSpkInfoRelations())
                ->select($this->productionSpkInfoColumns()),
        ]);

        $production = $polesChrome->production;

        return Inertia::render('poles-chrome/edit', [
            'formDocumentNo' => (string) config('spk.poles_chrome_form_document_no'),
            'craftsmanOptions' => $this->craftsmanOptions(),
            'statusItemOptions' => $this->statusItemOptions(),
            'form' => [
                'id' => (int) $polesChrome->row_id,
                'docNo' => $polesChrome->doc_no,
                'sendCraftsmanDate' => $polesChrome->send_craftsman_date?->format('Y-m-d H:i') ?? '',
                'receivedCraftsmanDate' => $polesChrome->received_craftsman_date?->format('Y-m-d H:i') ?? '',
                'craftsmanId' => filled($polesChrome->craftsman_id) && (int) $polesChrome->craftsman_id > 0
                    ? (int) $polesChrome->craftsman_id
                    : null,
                'notes' => filled($polesChrome->notes) ? (string) $polesChrome->notes : '',
                'statusItem' => filled($polesChrome->status_item)
                    ? (string) $polesChrome->status_item
                    : null,
                'startWeight' => $this->formatDecimal($polesChrome->start_weight) ?? '',
                'finishWeight' => $this->formatDecimal($polesChrome->finish_weight) ?? '',
                'spk' => $production === null ? null : [
                    'spkId' => (int) $production->row_id,
                    'spkNo' => $production->spk_no,
                    ...$this->productionSpkInfoFields($production),
                ],
            ],
            'approval' => $approvalService->abilitiesFor($polesChrome, $request->user()),
        ]);
    }

    public function submit(
        Request $request,
        PolishFinishedGood $polesChrome,
        PolishFinishedGoodApprovalService $approvalService,
    ): RedirectResponse {
        abort_if($polesChrome->is_deleted === 1, 404);

        if (! $approvalService->abilitiesFor($polesChrome, $request->user())['canSubmit']) {
            abort(403, 'Dokumen ini tidak dapat dikirim ke Manager Produksi.');
        }

        try {
            $approvalService->submit($polesChrome, $this->actorName($request));
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['approval' => $exception->getMessage()]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Dokumen Poles Chrome dikirim ke Manager Produksi.',
        ]);

        return to_route('poles-chrome.show', $polesChrome);
    }

    public function managerApprove(
        Request $request,
        PolishFinishedGood $polesChrome,
        PolishFinishedGoodApprovalService $approvalService,
    ): RedirectResponse {
        abort_if($polesChrome->is_deleted === 1, 404);

        if (! $approvalService->abilitiesFor($polesChrome, $request->user())['canManagerApprove']) {
            abort(403, 'Dokumen ini tidak dapat di-approve oleh Manager Produksi.');
        }

        try {
            $approvalService->managerApprove($polesChrome, $this->actorName($request));
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['approval' => $exception->getMessage()]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Dokumen Poles Chrome di-approve oleh Manager Produksi.',
        ]);

        return to_route('poles-chrome.show', $polesChrome);
    }

    public function complete(
        Request $request,
        PolishFinishedGood $polesChrome,
        PolishFinishedGoodApprovalService $approvalService,
    ): RedirectResponse {
        abort_if($polesChrome->is_deleted === 1, 404);

        if (! $approvalService->abilitiesFor($polesChrome, $request->user())['canComplete']) {
            abort(403, 'Dokumen ini tidak dapat diselesaikan.');
        }

        try {
            $approvalService->complete($polesChrome, $this->actorName($request));
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['approval' => $exception->getMessage()]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Dokumen Poles Chrome selesai.',
        ]);

        if ($request->input('return_to') === 'spk') {
            $spkNo = Production::query()
                ->notDeleted()
                ->where('row_id', $polesChrome->spk_id)
                ->value('spk_no');

            if (filled($spkNo)) {
                return redirect()->route('spk.show', $spkNo);
            }
        }

        return to_route('poles-chrome.show', $polesChrome);
    }

    public function update(
        UpdatePolishFinishedGoodRequest $request,
        PolishFinishedGood $polesChrome,
        PolishFinishedGoodApprovalService $approvalService,
    ): RedirectResponse {
        abort_if($polesChrome->is_deleted === 1, 404);
        abort_unless(
            $approvalService->abilitiesFor($polesChrome, $request->user())['canEdit'],
            403,
            'Dokumen Poles Chrome tidak dapat diubah pada status saat ini.',
        );

        $validated = $request->validated();
        $actor = $this->actorName($request);

        DB::connection('third')->transaction(function () use ($polesChrome, $validated, $actor): void {
            $sendDate = $validated['send_craftsman_date'] ?? null;
            $receivedDate = $validated['received_craftsman_date'] ?? null;

            $polesChrome->update([
                'spk_id' => $validated['spk_id'],
                'process_name' => $validated['process_name'] ?? $polesChrome->process_name ?? 'General',
                'craftsman_id' => $validated['craftsman_id'] ?? null,
                'date_from' => $sendDate,
                'date_to' => $receivedDate,
                'start_weight' => $validated['start_weight'] ?? null,
                'finish_weight' => $validated['finish_weight'] ?? null,
                'status_item' => $validated['status_item'] ?? null,
                'send_craftsman_date' => $sendDate,
                'received_craftsman_date' => $receivedDate,
                'qty_stone' => $validated['qty_stone'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'modified_date' => now(),
                'modified_by' => $actor,
            ]);

            $production = Production::query()
                ->notDeleted()
                ->where('row_id', $validated['spk_id'])
                ->first();

            if ($production !== null) {
                app(PolishFinishedGoodSpkEligibility::class)->syncLastWeight(
                    $production,
                    $validated['finish_weight'] ?? null,
                    $actor,
                );
            }

            $this->recalculateShrink($polesChrome->refresh());
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Dokumen Poles Chrome berhasil diperbarui.',
        ]);

        return to_route('poles-chrome.show', $polesChrome);
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
     *     statusItem: string|null,
     *     spkNo: string|null,
     *     orderReference: string|null,
     *     skuCode: string|null,
     *     typeCode: string|null,
     *     productItemName: string|null,
     *     itemDescription: string|null,
     *     spkImageUrl: string|null,
     *     craftsmanName: string|null,
     *     sendCraftsmanDate: string|null,
     *     receivedCraftsmanDate: string|null,
     *     startWeight: string|null,
     *     finishWeight: string|null,
     *     shrink: string|null,
     *     hasWeightGain: bool,
     *     notes: string|null
     * }
     */
    private function toListItem(PolishFinishedGood $document, array $craftsmanNames = []): array
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
            'statusItem' => filled($document->status_item) ? (string) $document->status_item : null,
            'spkNo' => $document->production?->spk_no,
            'orderReference' => SpkOrderReference::label($document->production),
            ...$this->productionSkuFields($document->production),
            'spkImageUrl' => SpkItemImageUrl::fromFileName($document->production?->file_name),
            'craftsmanName' => $craftsmanId > 0
                ? ($craftsmanNames[$craftsmanId] ?? "Pengrajin {$craftsmanId}")
                : null,
            'sendCraftsmanDate' => $document->send_craftsman_date?->format('Y-m-d H:i'),
            'receivedCraftsmanDate' => $document->received_craftsman_date?->format('Y-m-d H:i'),
            'startWeight' => $this->formatDecimal($document->start_weight),
            'finishWeight' => $this->formatDecimal($document->finish_weight),
            'shrink' => $this->hasMissingWeight($document)
                ? '0.00'
                : $this->formatGainAwareDecimal($document->shrink),
            'hasWeightGain' => $this->hasWeightGain($document),
            'notes' => filled($document->notes) ? (string) $document->notes : null,
        ];
    }

    /**
     * Shrink is meaningless when the start or finish weight has not been filled in.
     */
    private function hasMissingWeight(PolishFinishedGood $document): bool
    {
        return abs($this->toFloat($document->start_weight) ?? 0.0) < 0.0005
            || abs($this->toFloat($document->finish_weight) ?? 0.0) < 0.0005;
    }

    private function hasWeightGain(PolishFinishedGood $document): bool
    {
        if ($this->hasMissingWeight($document)) {
            return false;
        }

        $start = $this->toFloat($document->start_weight) ?? 0.0;
        $finish = $this->toFloat($document->finish_weight) ?? 0.0;

        return ($finish - $start) > 0.0005;
    }

    /**
     * Nilai negatif (penambahan berat) ditampilkan sebagai magnitudo bertanda plus.
     */
    private function formatGainAwareDecimal(mixed $value, int $precision = 2): ?string
    {
        $number = $this->toFloat($value);

        if ($number === null) {
            return null;
        }

        if ($number < -0.0005) {
            return '+'.number_format(abs($number), $precision, '.', '');
        }

        return number_format($number, $precision, '.', '');
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

    private function resolveIndexSort(string $sort): string
    {
        return in_array($sort, ['id', 'date', 'spk', 'craftsman'], true) ? $sort : 'id';
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

    /**
     * @return list<int>
     */
    private function resolveCraftsmanFilters(mixed $craftsman): array
    {
        return collect(is_array($craftsman) ? $craftsman : (filled($craftsman) ? [$craftsman] : []))
            ->filter(fn (mixed $value): bool => is_numeric($value))
            ->map(fn (mixed $value): int => (int) $value)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    private function resolveIndexPerPage(int $perPage): int
    {
        return in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 50;
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<PolishFinishedGood>  $query
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
                        ->whereColumn('spk.row_id', 'polishfinishedgood.spk_id')
                        ->limit(1),
                    $ascending ? 'asc' : 'desc',
                )
                ->orderBy('row_id', $ascending ? 'asc' : 'desc'),
            'craftsman' => $query
                ->orderBy(
                    DB::connection('third')
                        ->table('mscraftsman')
                        ->select('name')
                        ->whereColumn('mscraftsman.row_id', 'polishfinishedgood.craftsman_id')
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
    private function resolveStatusFilters(mixed $status): array
    {
        if (blank($status)) {
            return $this->defaultStatusFilters();
        }

        if ($status === self::ALL_STATUS_FILTER) {
            return [];
        }

        $allowed = array_keys($this->statusFilterCodes());

        return collect(is_array($status) ? $status : (filled($status) ? [$status] : []))
            ->map(fn (mixed $value): string => trim((string) $value))
            ->filter(fn (string $value): bool => in_array($value, $allowed, true))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    private function defaultStatusFilters(): array
    {
        return array_values(array_diff(
            array_keys($this->statusFilterCodes()),
            [self::COMPLETED_STATUS_FILTER],
        ));
    }

    /**
     * @return array<string, list<string>>
     */
    private function statusFilterCodes(): array
    {
        return [
            'open' => [
                PolishFinishedGoodApprovalService::STATUS_SUBMITTED,
                PolishFinishedGood::STATUS_TO_CRAFTSMAN,
                PolishFinishedGood::STATUS_FROM_CRAFTSMAN,
            ],
            'ppic' => [
                PolishFinishedGoodApprovalService::STATUS_MANAGER,
                PolishFinishedGood::STATUS_TO_PPIC,
            ],
            'done' => [
                PolishFinishedGoodApprovalService::STATUS_DONE,
                PolishFinishedGood::STATUS_DONE,
                PolishFinishedGood::STATUS_TO_JB,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function toDetailItem(PolishFinishedGood $document): array
    {
        $startWeight = $this->toFloat($document->start_weight);
        $shrink = $this->toFloat($document->shrink);
        $shrinkPercent = null;

        if ($shrink !== null && $startWeight !== null && abs($startWeight) >= 0.0005) {
            $shrinkPercent = number_format(($shrink / $startWeight) * 100, 2, '.', '').'%';
        }

        $production = $document->production;

        return [
            'id' => (int) $document->row_id,
            'docNo' => $document->doc_no,
            'processName' => filled($document->process_name) ? (string) $document->process_name : null,
            'status' => filled($document->status) ? (string) $document->status : null,
            'statusLabel' => $document->statusLabel(),
            'statusItem' => filled($document->status_item) ? (string) $document->status_item : null,
            'craftsmanId' => filled($document->craftsman_id) && (int) $document->craftsman_id > 0
                ? (int) $document->craftsman_id
                : null,
            'craftsmanName' => $this->resolveCraftsmanName($document->craftsman_id),
            'sendCraftsmanDate' => $document->send_craftsman_date?->format('Y-m-d H:i:s'),
            'receivedCraftsmanDate' => $document->received_craftsman_date?->format('Y-m-d H:i:s'),
            'notes' => filled($document->notes) ? (string) $document->notes : null,
            'startWeight' => $this->formatDecimal($document->start_weight),
            'finishWeight' => $this->formatDecimal($document->finish_weight),
            'shrink' => $this->formatDecimal($document->shrink),
            'shrinkPercent' => $shrinkPercent,
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

    private function recalculateShrink(PolishFinishedGood $document): void
    {
        $finish = $this->toFloat($document->finish_weight);

        if ($finish === null) {
            $document->forceFill([
                'shrink' => '0.00',
            ])->save();

            return;
        }

        $start = $this->toFloat($document->start_weight) ?? 0.0;
        $shrink = round($start - $finish, 2);

        $document->forceFill([
            'shrink' => number_format($shrink, 2, '.', ''),
        ])->save();
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function statusItemOptions(): array
    {
        return collect(PolishFinishedGood::statusItemOptions())
            ->map(fn (string $value): array => [
                'value' => $value,
                'label' => $value === 'NOK' ? 'Not OK' : $value,
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
                ...$this->productionSkuFields(null),
                'customerName' => null,
                'satuan' => '—',
            ];
        }

        return [
            'spkType' => filled($production->spk_type)
                ? (string) $production->spk_type
                : null,
            'orderTypeLabel' => app(ProductionOrderTypeLabel::class)->forProduction($production),
            ...$this->productionSkuFields($production),
            'customerName' => filled($production->customer_name)
                ? (string) $production->customer_name
                : null,
            'satuan' => SpkQtyUnit::label($production->qty, $production->satuan),
        ];
    }

    /**
     * @return array{
     *     skuCode: string|null,
     *     typeCode: string|null,
     *     productItemName: string|null,
     *     itemDescription: string|null
     * }
     */
    private function productionSkuFields(?Production $production): array
    {
        if ($production === null) {
            return [
                'skuCode' => null,
                'typeCode' => null,
                'productItemName' => null,
                'itemDescription' => null,
            ];
        }

        $typeCode = trim((string) ($production->categoryPrefix?->prefix ?? ''));
        $productItemName = trim((string) ($production->sku?->item_original ?? ''));

        if ($productItemName === '') {
            $productItemName = trim((string) ($production->item_name ?? ''));
        }

        $itemDescription = trim((string) ($production->description ?? ''));

        return [
            'skuCode' => filled($production->sku?->sku_code)
                ? (string) $production->sku->sku_code
                : null,
            'typeCode' => $typeCode !== '' ? $typeCode : null,
            'productItemName' => $productItemName !== '' ? $productItemName : null,
            'itemDescription' => $itemDescription !== '' ? $itemDescription : null,
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

    protected function polishProcessReportModelClass(): string
    {
        return PolishFinishedGood::class;
    }

    protected function polishProcessReportTable(): string
    {
        return 'polishfinishedgood';
    }

    protected function polishProcessReportExportTitle(): string
    {
        return 'Laporan Poles Chrome';
    }

    protected function polishProcessReportExportPrefix(): string
    {
        return 'laporan-poles-chrome';
    }

    protected function polishProcessReportInertiaPage(): string
    {
        return 'poles-chrome/report';
    }

    protected function polishProcessReportIncludesProcessName(): bool
    {
        return true;
    }

    /**
     * @return array{pending: int, inProgress: int, completed: int}
     */
    private function spkStatusCounts(PolishFinishedGoodSpkEligibility $spkEligibility): array
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
