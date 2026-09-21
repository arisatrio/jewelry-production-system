<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCoranRequest;
use App\Http\Requests\UpdateCoranRequest;
use App\Models\Coran;
use App\Models\CoranSpk;
use App\Models\Production;
use App\Support\CoranApprovalService;
use App\Support\CoranDocNumberGenerator;
use App\Support\CoranMaterialBreakdown;
use App\Support\CoranMaterialGoldSynchronizer;
use App\Support\CoranSpkEligibility;
use App\Support\ProductionOrderTypeLabel;
use App\Support\SpkQtyUnit;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class CoranController extends Controller
{
    /**
     * Display a listing of coran documents.
     */
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();
        $perPage = $request->integer('per_page', 10);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;

        $corans = Coran::query()
            ->notDeleted()
            ->with([
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
                        ->orWhere('status', 'like', "%{$search}%")
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
            ->through(fn (Coran $coran): array => $this->toListItem($coran));

        $spkEligibility = app(CoranSpkEligibility::class);

        return Inertia::render('coran/index', [
            'corans' => $corans,
            'spkStatusCounts' => $this->spkStatusCounts($spkEligibility),
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
            ],
        ]);
    }

    /**
     * Show the form for creating a new coran document.
     */
    public function create(CoranMaterialGoldSynchronizer $materialSynchronizer): Response
    {
        return Inertia::render('coran/create', [
            'formDocumentNo' => (string) config('spk.coran_form_document_no'),
            'statusOptions' => $this->detailStatusOptions(),
            'craftsmanOptions' => $this->craftsmanOptions(),
            'materialOptions' => $materialSynchronizer->materialOptions(),
            'form' => [
                'transDate' => now()->format('Y-m-d'),
                'craftsmanId' => null,
                'details' => [],
                'materials' => [],
            ],
        ]);
    }

    /**
     * Search SPKs for the coran form selector.
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
        $spkEligibility = app(CoranSpkEligibility::class);

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
        $coranRefs = in_array($queue, ['inProgress', 'completed'], true)
            ? $spkEligibility->coranRefsBySpkIds(
                $productions->pluck('row_id')->map(fn (mixed $id): int => (int) $id)->all(),
            )
            : [];

        return response()->json([
            'status' => true,
            'data' => $productions->map(function (Production $production) use ($coranRefs): array {
                $spkId = (int) $production->row_id;
                $coranRef = $coranRefs[$spkId] ?? null;

                return [
                    'rowId' => $spkId,
                    'spkNo' => (string) $production->spk_no,
                    'coranId' => $coranRef['coranId'] ?? null,
                    'docNo' => $coranRef['docNo'] ?? null,
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
     * Store a newly created coran document.
     */
    public function store(
        StoreCoranRequest $request,
        CoranDocNumberGenerator $docNumberGenerator,
        CoranMaterialGoldSynchronizer $materialSynchronizer,
    ): RedirectResponse {
        $validated = $request->validated();
        $actor = $this->actorName($request);
        $details = $validated['details'];
        $materials = $validated['materials'] ?? [];

        $coran = DB::connection('third')->transaction(function () use ($validated, $details, $materials, $actor, $docNumberGenerator, $materialSynchronizer): Coran {
            $totalWeight = collect($details)
                ->map(fn (array $detail): float => (float) ($detail['weight'] ?? 0))
                ->sum();

            $coran = Coran::query()->create([
                'doc_no' => $docNumberGenerator->generate(),
                'trans_date' => $validated['trans_date'],
                'craftsman_id' => $validated['craftsman_id'] ?? null,
                'submit_material_rosegold' => '0.000',
                'submit_material_whitegold' => '0.000',
                'submit_material_yellowgold' => '0.000',
                'result_material_rosegold' => '0.000',
                'result_material_whitegold' => '0.000',
                'result_material_yellowgold' => '0.000',
                'shrink' => '0.00',
                'weight' => number_format($totalWeight, 2, '.', ''),
                'status' => null,
                'is_from_new_system' => 1,
                'is_deleted' => 0,
                'created_date' => now(),
                'created_by' => $actor,
                'modified_date' => now(),
                'modified_by' => $actor,
            ]);

            $this->storeDetails($coran, $details, $actor);
            $materialSynchronizer->sync($coran, $materials, $actor);
            $this->recalculateShrink($coran->refresh());

            return $coran->refresh();
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Dokumen coran berhasil ditambahkan.',
        ]);

        return to_route('coran.show', $coran);
    }

    /**
     * Display the specified coran document.
     */
    public function show(
        Request $request,
        Coran $coran,
        CoranApprovalService $approvalService,
        CoranMaterialBreakdown $materialBreakdown,
    ): Response {
        abort_if($coran->is_deleted === 1, 404);

        $coran->load([
            'details' => fn ($query) => $query
                ->notDeleted()
                ->with([
                    'production' => fn ($productionQuery) => $productionQuery
                        ->notDeleted()
                        ->with($this->productionSpkInfoRelations())
                        ->select($this->productionSpkInfoColumns()),
                ])
                ->orderBy('line_id'),
        ]);

        return Inertia::render('coran/show', [
            'coranItem' => $this->toDetailItem($coran, $approvalService, $materialBreakdown),
            'workflowStatus' => $approvalService->map($coran),
            'approvalHistory' => $approvalService->history($coran),
            'approvalFooter' => $approvalService->footerColumns(
                $coran,
                $this->actorName($request),
            ),
            'approval' => $approvalService->abilitiesFor($coran, $request->user()),
        ]);
    }

    /**
     * Show the form for editing the specified coran document.
     */
    public function edit(
        Request $request,
        Coran $coran,
        CoranApprovalService $approvalService,
        CoranMaterialGoldSynchronizer $materialSynchronizer,
    ): Response {
        abort_if($coran->is_deleted === 1, 404);

        $coran->load([
            'details' => fn ($query) => $query
                ->notDeleted()
                ->with([
                    'production' => fn ($productionQuery) => $productionQuery
                        ->notDeleted()
                        ->with($this->productionSpkInfoRelations())
                        ->select($this->productionSpkInfoColumns()),
                ])
                ->orderBy('line_id'),
        ]);

        $details = $coran->details
            ->filter(fn (CoranSpk $detail): bool => $detail->is_deleted === 0)
            ->values()
            ->map(fn (CoranSpk $detail): array => [
                'spkId' => (int) $detail->spk_id,
                'spkNo' => $detail->production?->spk_no,
                ...$this->productionSpkInfoFields($detail->production),
                'weight' => $this->formatDecimal($detail->weight) ?? '',
                'weightRosegold' => $this->formatDecimal($detail->weight_rosegold) ?? '',
                'weightWhitegold' => $this->formatDecimal($detail->weight_whitegold) ?? '',
                'weightYellowgold' => $this->formatDecimal($detail->weight_yellowgold) ?? '',
                ...$this->detailKadarStatusFormFields($detail),
            ])
            ->all();

        return Inertia::render('coran/edit', [
            'formDocumentNo' => (string) config('spk.coran_form_document_no'),
            'statusOptions' => $this->detailStatusOptions(),
            'craftsmanOptions' => $this->craftsmanOptions(),
            'materialOptions' => $materialSynchronizer->materialOptions(),
            'approval' => $approvalService->abilitiesFor($coran, $request->user()),
            'form' => [
                'id' => (int) $coran->row_id,
                'docNo' => $coran->doc_no,
                'transDate' => $coran->trans_date?->format('Y-m-d') ?? now()->format('Y-m-d'),
                'craftsmanId' => filled($coran->craftsman_id) && (int) $coran->craftsman_id > 0
                    ? (int) $coran->craftsman_id
                    : null,
                'details' => $details,
                'materials' => $materialSynchronizer->formLinesFor($coran),
            ],
        ]);
    }

    /**
     * Kirim dokumen Open ke Manager Produksi (COR010).
     */
    public function submit(
        Request $request,
        Coran $coran,
        CoranApprovalService $approvalService,
    ): RedirectResponse {
        abort_if($coran->is_deleted === 1, 404);

        if (! $approvalService->abilitiesFor($coran, $request->user())['canSubmit']) {
            abort(403, 'Dokumen ini tidak dapat dikirim ke Manager Produksi.');
        }

        try {
            $approvalService->submit($coran, $this->actorName($request));
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['approval' => $exception->getMessage()]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Dokumen coran dikirim ke Manager Produksi.',
        ]);

        return to_route('coran.show', $coran);
    }

    /**
     * Manager Produksi meng-approve dokumen (COR010 → COR020).
     */
    public function managerApprove(
        Request $request,
        Coran $coran,
        CoranApprovalService $approvalService,
    ): RedirectResponse {
        abort_if($coran->is_deleted === 1, 404);

        if (! $approvalService->abilitiesFor($coran, $request->user())['canManagerApprove']) {
            abort(403, 'Dokumen ini tidak dapat di-approve oleh Manager Produksi.');
        }

        try {
            $approvalService->managerApprove($coran, $this->actorName($request));
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['approval' => $exception->getMessage()]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Dokumen coran di-approve oleh Manager Produksi.',
        ]);

        return to_route('coran.show', $coran);
    }

    /**
     * Selesaikan dokumen (COR020 → CORDONE).
     */
    public function complete(
        Request $request,
        Coran $coran,
        CoranApprovalService $approvalService,
    ): RedirectResponse {
        abort_if($coran->is_deleted === 1, 404);

        if (! $approvalService->abilitiesFor($coran, $request->user())['canComplete']) {
            abort(403, 'Dokumen ini tidak dapat diselesaikan.');
        }

        try {
            $approvalService->complete($coran, $this->actorName($request));
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['approval' => $exception->getMessage()]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Dokumen coran selesai.',
        ]);

        return to_route('coran.show', $coran);
    }

    /**
     * Update the specified coran document.
     */
    public function update(
        UpdateCoranRequest $request,
        Coran $coran,
        CoranApprovalService $approvalService,
        CoranMaterialGoldSynchronizer $materialSynchronizer,
    ): RedirectResponse {
        abort_if($coran->is_deleted === 1, 404);
        abort_unless(
            $approvalService->canEditForm($coran),
            403,
            'Dokumen coran tidak dapat diubah pada status saat ini.',
        );

        $validated = $request->validated();
        $actor = $this->actorName($request);
        $details = $validated['details'];
        $materials = $validated['materials'] ?? [];

        DB::connection('third')->transaction(function () use ($coran, $validated, $details, $materials, $actor, $materialSynchronizer): void {
            $totalWeight = collect($details)
                ->map(fn (array $detail): float => (float) ($detail['weight'] ?? 0))
                ->sum();

            $coran->update([
                'trans_date' => $validated['trans_date'],
                'craftsman_id' => $validated['craftsman_id'] ?? null,
                'weight' => number_format($totalWeight, 2, '.', ''),
                'modified_date' => now(),
                'modified_by' => $actor,
            ]);

            $coran->details()
                ->notDeleted()
                ->update([
                    'is_deleted' => 1,
                    'deleted_date' => now(),
                    'deleted_by' => $actor,
                    'modified_date' => now(),
                    'modified_by' => $actor,
                ]);

            $this->storeDetails($coran, $details, $actor);
            $materialSynchronizer->sync($coran, $materials, $actor);
            $this->recalculateShrink($coran->refresh());
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Dokumen coran berhasil diperbarui.',
        ]);

        return to_route('coran.show', $coran);
    }

    /**
     * Soft-delete the specified coran document.
     */
    public function destroy(
        Request $request,
        Coran $coran,
        CoranApprovalService $approvalService,
    ): RedirectResponse {
        abort_if($coran->is_deleted === 1, 404);

        if (! $approvalService->abilitiesFor($coran, $request->user())['canDelete']) {
            abort(403, 'Dokumen coran tidak dapat dihapus pada status saat ini.');
        }

        $actor = $this->actorName($request);

        DB::connection('third')->transaction(function () use ($coran, $actor): void {
            $coran->update([
                'is_deleted' => 1,
                'deleted_date' => now(),
                'deleted_by' => $actor,
                'modified_date' => now(),
                'modified_by' => $actor,
            ]);

            $coran->details()
                ->notDeleted()
                ->update([
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
                    ->where('ref_row_id', $coran->row_id)
                    ->whereIn('transtype_id', CoranMaterialGoldSynchronizer::transtypeIds())
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

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Dokumen coran berhasil dihapus.',
        ]);

        return to_route('coran.index');
    }

    /**
     * @param  list<array{
     *     spk_id: int,
     *     weight?: string|null,
     *     weight_rosegold?: string|null,
     *     weight_whitegold?: string|null,
     *     weight_yellowgold?: string|null,
     *     kadar?: string|null,
     *     weight_rosegold?: string|null,
     *     weight_whitegold?: string|null,
     *     weight_yellowgold?: string|null,
     *     kadar?: string|null,
     *     kadar_rosegold?: string|null,
     *     kadar_whitegold?: string|null,
     *     kadar_yellowgold?: string|null,
     *     status?: string|null,
     *     status_rosegold?: string|null,
     *     status_whitegold?: string|null,
     *     status_yellowgold?: string|null
     * }>  $details
     */
    private function storeDetails(Coran $coran, array $details, string $actor): void
    {
        $spkEligibility = app(CoranSpkEligibility::class);
        $hasColorColumns = Schema::connection('third')->hasColumn('coranspk', 'weight_rosegold');

        foreach ($details as $detail) {
            $production = Production::query()
                ->notDeleted()
                ->where('row_id', $detail['spk_id'])
                ->first();

            if ($production !== null) {
                $spkEligibility->syncLastWeight($production, $detail['weight'] ?? null, $actor);
            }

            $payload = [
                'spk_id' => $detail['spk_id'],
                'weight' => filled($detail['weight'] ?? null)
                    ? $detail['weight']
                    : null,
                'kadar' => filled($detail['kadar'] ?? null)
                    ? $detail['kadar']
                    : null,
                'status' => filled($detail['status'] ?? null)
                    ? (string) $detail['status']
                    : null,
                'is_deleted' => 0,
                'created_date' => now(),
                'created_by' => $actor,
                'modified_date' => now(),
                'modified_by' => $actor,
            ];

            if ($hasColorColumns) {
                $payload['weight_rosegold'] = filled($detail['weight_rosegold'] ?? null)
                    ? $detail['weight_rosegold']
                    : null;
                $payload['weight_whitegold'] = filled($detail['weight_whitegold'] ?? null)
                    ? $detail['weight_whitegold']
                    : null;
                $payload['weight_yellowgold'] = filled($detail['weight_yellowgold'] ?? null)
                    ? $detail['weight_yellowgold']
                    : null;
            }

            if (Schema::connection('third')->hasColumn('coranspk', 'kadar_rosegold')) {
                $payload['kadar_rosegold'] = filled($detail['kadar_rosegold'] ?? null)
                    ? $detail['kadar_rosegold']
                    : null;
                $payload['kadar_whitegold'] = filled($detail['kadar_whitegold'] ?? null)
                    ? $detail['kadar_whitegold']
                    : null;
                $payload['kadar_yellowgold'] = filled($detail['kadar_yellowgold'] ?? null)
                    ? $detail['kadar_yellowgold']
                    : null;
            }

            if (Schema::connection('third')->hasColumn('coranspk', 'status_rosegold')) {
                $payload['status_rosegold'] = filled($detail['status_rosegold'] ?? null)
                    ? (string) $detail['status_rosegold']
                    : null;
                $payload['status_whitegold'] = filled($detail['status_whitegold'] ?? null)
                    ? (string) $detail['status_whitegold']
                    : null;
                $payload['status_yellowgold'] = filled($detail['status_yellowgold'] ?? null)
                    ? (string) $detail['status_yellowgold']
                    : null;
            }

            $coran->details()->create($payload);
        }
    }

    private function recalculateShrink(Coran $coran): void
    {
        $totalSubmit = collect([
            $coran->submit_material_rosegold,
            $coran->submit_material_whitegold,
            $coran->submit_material_yellowgold,
        ])->sum(fn (mixed $value): float => (float) ($value ?? 0));

        $totalResult = collect([
            $coran->result_material_rosegold,
            $coran->result_material_whitegold,
            $coran->result_material_yellowgold,
        ])->sum(fn (mixed $value): float => (float) ($value ?? 0));

        $totalSpkWeight = (float) ($coran->weight ?? 0);
        $shrink = round($totalSubmit - $totalResult - $totalSpkWeight, 2);

        $coran->forceFill([
            'shrink' => number_format($shrink, 2, '.', ''),
        ])->save();
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function detailStatusOptions(): array
    {
        /** @var list<array{value?: mixed, label?: mixed}> $options */
        $options = config('spk.coran_detail_statuses', []);

        return collect($options)
            ->filter(fn (mixed $option): bool => is_array($option)
                && filled($option['value'] ?? null)
                && filled($option['label'] ?? null))
            ->map(fn (array $option): array => [
                'value' => (string) $option['value'],
                'label' => (string) $option['label'],
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
     * @return array{
     *     id: int,
     *     docNo: string|null,
     *     transDate: string|null,
     *     status: string|null,
     *     statusLabel: string,
     *     spkNos: list<string>,
     *     totalSpkWeight: string|null,
     *     totalSubmitMaterial: string|null,
     *     totalResultMaterial: string|null,
     *     shrink: string|null
     * }
     */
    private function toListItem(Coran $coran): array
    {
        $detailRows = $coran->details
            ->filter(fn (CoranSpk $detail): bool => $detail->is_deleted === 0)
            ->values();

        return [
            'id' => (int) $coran->row_id,
            'docNo' => $coran->doc_no,
            'transDate' => $coran->trans_date?->format('Y-m-d'),
            'status' => $coran->status,
            'statusLabel' => app(CoranApprovalService::class)->statusLabelFor($coran),
            'spkNos' => $detailRows
                ->map(fn (CoranSpk $detail): ?string => $detail->production?->spk_no)
                ->filter()
                ->map(fn (mixed $spkNo): string => (string) $spkNo)
                ->unique()
                ->values()
                ->all(),
            'totalSpkWeight' => $this->sumDecimal(
                $detailRows->map(fn (CoranSpk $detail): mixed => $detail->weight),
            ),
            'totalSubmitMaterial' => $this->sumDecimal(collect([
                $coran->submit_material_rosegold,
                $coran->submit_material_whitegold,
                $coran->submit_material_yellowgold,
            ])),
            'totalResultMaterial' => $this->sumDecimal(collect([
                $coran->result_material_rosegold,
                $coran->result_material_whitegold,
                $coran->result_material_yellowgold,
            ])),
            'shrink' => $this->formatDecimal($coran->shrink),
        ];
    }

    /**
     * @return array{
     *     id: int,
     *     docNo: string|null,
     *     transDate: string|null,
     *     status: string|null,
     *     statusLabel: string,
     *     craftsmanId: int|null,
     *     craftsmanName: string|null,
     *     submitMaterials: list<array{name: string, weight: string}>,
     *     resultMaterials: list<array{name: string, weight: string}>,
     *     totalSubmitMaterial: string|null,
     *     totalResultMaterial: string|null,
     *     totalSpkWeight: string|null,
     *     spkCount: int,
     *     okSpkPercent: string|null,
     *     shrink: string|null,
     *     coranBreakdown: list<array{
     *         color: string,
     *         colorKey: string,
     *         bahan: list<array{name: string, weight: float}>,
     *         sisa: list<array{name: string, weight: float}>
     *     }>,
     *     details: list<array{
     *         lineId: int,
     *         spkId: int,
     *         spkNo: string|null,
     *         spkType: string|null,
     *         orderTypeLabel: string|null,
     *         skuCode: string|null,
     *         typeCode: string|null,
     *         productItemName: string|null,
     *         itemDescription: string|null,
     *         customerName: string|null,
     *         satuan: string,
     *         weight: string|null,
     *         weightRosegold: string|null,
     *         weightWhitegold: string|null,
     *         weightYellowgold: string|null,
     *         kadar: string|null,
     *         status: string|null,
     *         statusLabel: string
     *     }>
     * }
     */
    private function toDetailItem(
        Coran $coran,
        CoranApprovalService $approvalService,
        CoranMaterialBreakdown $materialBreakdown,
    ): array {
        $detailRows = $coran->details
            ->filter(fn (CoranSpk $detail): bool => $detail->is_deleted === 0)
            ->values();

        $submitMaterials = $this->materialLines([
            'Rose Gold' => $coran->submit_material_rosegold,
            'White Gold' => $coran->submit_material_whitegold,
            'Yellow Gold' => $coran->submit_material_yellowgold,
        ]);
        $resultMaterials = $this->materialLines([
            'Rose Gold' => $coran->result_material_rosegold,
            'White Gold' => $coran->result_material_whitegold,
            'Yellow Gold' => $coran->result_material_yellowgold,
        ]);

        $spkCount = $detailRows->count();
        $okSpkCount = $detailRows
            ->filter(function (CoranSpk $detail): bool {
                $status = strtoupper(trim((string) ($detail->status ?? '')));

                return $status === CoranSpk::STATUS_OK;
            })
            ->count();

        return [
            'id' => (int) $coran->row_id,
            'docNo' => $coran->doc_no,
            'transDate' => $coran->trans_date?->format('Y-m-d'),
            'status' => $coran->status,
            'statusLabel' => $approvalService->statusLabelFor($coran),
            'craftsmanId' => filled($coran->craftsman_id) && (int) $coran->craftsman_id > 0
                ? (int) $coran->craftsman_id
                : null,
            'craftsmanName' => $this->resolveCraftsmanName($coran->craftsman_id),
            'submitMaterials' => $submitMaterials,
            'resultMaterials' => $resultMaterials,
            'totalSubmitMaterial' => $this->sumDecimal(collect([
                $coran->submit_material_rosegold,
                $coran->submit_material_whitegold,
                $coran->submit_material_yellowgold,
            ])),
            'totalResultMaterial' => $this->sumDecimal(collect([
                $coran->result_material_rosegold,
                $coran->result_material_whitegold,
                $coran->result_material_yellowgold,
            ])),
            'totalSpkWeight' => $this->sumDecimal(
                $detailRows->map(fn (CoranSpk $detail): mixed => $detail->weight),
            ),
            'spkCount' => $spkCount,
            'okSpkPercent' => $spkCount > 0
                ? number_format(($okSpkCount / $spkCount) * 100, 2, '.', '').'%'
                : null,
            'shrink' => $this->formatDecimal($coran->shrink),
            'coranBreakdown' => $materialBreakdown->forIds([(int) $coran->row_id])[(int) $coran->row_id]
                ?? $materialBreakdown->empty(),
            'details' => $detailRows
                ->map(fn (CoranSpk $detail): array => [
                    'lineId' => (int) $detail->line_id,
                    'spkId' => (int) $detail->spk_id,
                    'spkNo' => $detail->production?->spk_no,
                    ...$this->productionSpkInfoFields($detail->production),
                    'weight' => $this->formatDecimal($detail->weight),
                    'weightRosegold' => $this->formatDecimal($detail->weight_rosegold),
                    'weightWhitegold' => $this->formatDecimal($detail->weight_whitegold),
                    'weightYellowgold' => $this->formatDecimal($detail->weight_yellowgold),
                    ...$this->detailKadarStatusDisplayFields($detail),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $materials
     * @return list<array{name: string, weight: string}>
     */
    private function materialLines(array $materials): array
    {
        $lines = [];

        foreach ($materials as $name => $value) {
            $weight = $this->toFloat($value);

            if ($weight === null || abs($weight) < 0.0005) {
                continue;
            }

            $lines[] = [
                'name' => $name,
                'weight' => number_format($weight, 2, '.', ''),
            ];
        }

        return $lines;
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

    private function spkStatusLabel(?string $status): string
    {
        if (! filled($status)) {
            return '—';
        }

        $normalized = strtoupper(trim($status));

        return match ($normalized) {
            'OK' => 'OK',
            'NOK', 'NOT OK', 'NOTOK' => 'Not OK',
            default => trim($status),
        };
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

    /**
     * @return array{
     *     kadar: string,
     *     kadarRosegold: string,
     *     kadarWhitegold: string,
     *     kadarYellowgold: string,
     *     status: string,
     *     statusRosegold: string,
     *     statusWhitegold: string,
     *     statusYellowgold: string
     * }
     */
    private function detailKadarStatusFormFields(CoranSpk $detail): array
    {
        $legacyKadar = $this->formatKadar($detail->kadar) ?? '';
        $legacyStatus = filled($detail->status) ? (string) $detail->status : '';

        $kadarRosegold = $this->formatKadar($detail->kadar_rosegold ?? null) ?? '';
        $kadarWhitegold = $this->formatKadar($detail->kadar_whitegold ?? null) ?? '';
        $kadarYellowgold = $this->formatKadar($detail->kadar_yellowgold ?? null) ?? '';
        $hasColorKadar = $kadarRosegold !== ''
            || $kadarWhitegold !== ''
            || $kadarYellowgold !== '';

        if (! $hasColorKadar && $legacyKadar !== '') {
            $kadarRosegold = $legacyKadar;
        }

        $statusRosegold = filled($detail->status_rosegold ?? null)
            ? (string) $detail->status_rosegold
            : '';
        $statusWhitegold = filled($detail->status_whitegold ?? null)
            ? (string) $detail->status_whitegold
            : '';
        $statusYellowgold = filled($detail->status_yellowgold ?? null)
            ? (string) $detail->status_yellowgold
            : '';
        $hasColorStatus = $statusRosegold !== ''
            || $statusWhitegold !== ''
            || $statusYellowgold !== '';

        if (! $hasColorStatus && $legacyStatus !== '') {
            $statusRosegold = $legacyStatus;
        }

        return [
            'kadar' => $legacyKadar,
            'kadarRosegold' => $kadarRosegold,
            'kadarWhitegold' => $kadarWhitegold,
            'kadarYellowgold' => $kadarYellowgold,
            'status' => $legacyStatus,
            'statusRosegold' => $statusRosegold,
            'statusWhitegold' => $statusWhitegold,
            'statusYellowgold' => $statusYellowgold,
        ];
    }

    /**
     * @return array{
     *     kadar: string|null,
     *     kadarRosegold: string|null,
     *     kadarWhitegold: string|null,
     *     kadarYellowgold: string|null,
     *     status: string|null,
     *     statusLabel: string,
     *     statusRosegold: string|null,
     *     statusRosegoldLabel: string,
     *     statusWhitegold: string|null,
     *     statusWhitegoldLabel: string,
     *     statusYellowgold: string|null,
     *     statusYellowgoldLabel: string
     * }
     */
    private function detailKadarStatusDisplayFields(CoranSpk $detail): array
    {
        $legacyKadar = $this->formatKadar($detail->kadar);
        $kadarRosegold = $this->formatKadar($detail->kadar_rosegold ?? null);
        $kadarWhitegold = $this->formatKadar($detail->kadar_whitegold ?? null);
        $kadarYellowgold = $this->formatKadar($detail->kadar_yellowgold ?? null);

        if (
            $kadarRosegold === null
            && $kadarWhitegold === null
            && $kadarYellowgold === null
            && $legacyKadar !== null
        ) {
            $kadarRosegold = $legacyKadar;
        }

        $legacyStatus = filled($detail->status) ? (string) $detail->status : null;
        $statusRosegold = filled($detail->status_rosegold ?? null)
            ? (string) $detail->status_rosegold
            : null;
        $statusWhitegold = filled($detail->status_whitegold ?? null)
            ? (string) $detail->status_whitegold
            : null;
        $statusYellowgold = filled($detail->status_yellowgold ?? null)
            ? (string) $detail->status_yellowgold
            : null;

        if (
            $statusRosegold === null
            && $statusWhitegold === null
            && $statusYellowgold === null
            && $legacyStatus !== null
        ) {
            $statusRosegold = $legacyStatus;
        }

        return [
            'kadar' => $legacyKadar,
            'kadarRosegold' => $kadarRosegold,
            'kadarWhitegold' => $kadarWhitegold,
            'kadarYellowgold' => $kadarYellowgold,
            'status' => $legacyStatus,
            'statusLabel' => $this->spkStatusLabel($legacyStatus),
            'statusRosegold' => $statusRosegold,
            'statusRosegoldLabel' => $this->spkStatusLabel($statusRosegold),
            'statusWhitegold' => $statusWhitegold,
            'statusWhitegoldLabel' => $this->spkStatusLabel($statusWhitegold),
            'statusYellowgold' => $statusYellowgold,
            'statusYellowgoldLabel' => $this->spkStatusLabel($statusYellowgold),
        ];
    }

    /**
     * @param  Collection<int, mixed>  $values
     */
    private function sumDecimal(Collection $values): ?string
    {
        $numbers = $values
            ->map(fn (mixed $value): ?float => $this->toFloat($value))
            ->filter(fn (?float $value): bool => $value !== null);

        if ($numbers->isEmpty()) {
            return null;
        }

        return number_format((float) $numbers->sum(), 2, '.', '');
    }

    private function formatDecimal(mixed $value): ?string
    {
        $number = $this->toFloat($value);

        if ($number === null) {
            return null;
        }

        return number_format($number, 2, '.', '');
    }

    private function formatKadar(mixed $value): ?string
    {
        $number = $this->toFloat($value);

        if ($number === null) {
            return null;
        }

        return number_format($number, 2, '.', '');
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
    private function spkStatusCounts(CoranSpkEligibility $spkEligibility): array
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
