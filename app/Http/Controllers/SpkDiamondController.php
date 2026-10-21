<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSpkDiamondRequest;
use App\Http\Requests\UpdateSpkDiamondRequest;
use App\Models\Production;
use App\Models\SpkAllocation;
use App\Models\SpkAllocationDiamond;
use App\Models\SpkAllocationStone;
use App\Models\SpkDiamond;
use App\Support\DiamondMountingStoneSynchronizer;
use App\Support\ProductionOrderTypeLabel;
use App\Support\SpkAllocationReservedStock;
use App\Support\SpkDiamondLedger;
use App\Support\SpkDiamondMountingBridge;
use App\Support\SpkDiamondSynchronizer;
use App\Support\SpkItemImageUrl;
use App\Support\SpkOrderReference;
use App\Support\SpkProductionStoneList;
use App\Support\SpkQtyUnit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SpkDiamondController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();
        $statusFilters = $this->resolveIndexStatusFilters($request->input('status'));
        $perPage = max(10, min($request->integer('per_page', 50), 100));

        $documents = SpkDiamond::query()
            ->notDeleted()
            ->with([
                'production' => fn ($query) => $query
                    ->notDeleted()
                    ->with(['sku', 'categoryPrefix'])
                    ->select([
                        'row_id',
                        'spk_no',
                        'item_name',
                        'customer_name',
                        'file_name',
                        'spk_type',
                        'description',
                        'qty',
                        'satuan',
                        'sku_id',
                        'category_prefix_id',
                    ]),
            ])
            ->when($search !== '', function ($query) use ($search): void {
                $like = '%'.$search.'%';
                $query->where(function ($inner) use ($like): void {
                    $inner->where('no_spk_diamond', 'like', $like)
                        ->orWhere('spk_no', 'like', $like)
                        ->orWhere('keterangan', 'like', $like)
                        ->orWhereHas('production', fn ($production) => $production
                            ->where('spk_no', 'like', $like)
                            ->orWhere('item_name', 'like', $like)
                            ->orWhere('customer_name', 'like', $like));
                });
            })
            ->when($statusFilters !== [], function ($query) use ($statusFilters): void {
                $expanded = collect($statusFilters)
                    ->flatMap(function (string $status): array {
                        if ($status === SpkDiamond::STATUS_SUBMITTED) {
                            return [
                                SpkDiamond::STATUS_SUBMITTED,
                                SpkDiamond::LEGACY_STATUS_SUBMITTED_TYPO,
                            ];
                        }

                        return [$status];
                    })
                    ->unique()
                    ->values()
                    ->all();

                $query->whereIn('status', $expanded);
            })
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (SpkDiamond $document): array => $this->toIndexRow($document));

        return Inertia::render('spk-diamond/index', [
            'documents' => $documents,
            'filters' => [
                'search' => $search,
                'status' => $statusFilters,
                'per_page' => $perPage,
            ],
            'defaultFilters' => [
                'status' => [],
            ],
            'filterOptions' => [
                'status' => collect($this->statusOptions())
                    ->reject(fn (array $option): bool => $option['value'] === 'all')
                    ->values()
                    ->all(),
                'per_page' => [
                    ['value' => '10', 'label' => '10'],
                    ['value' => '25', 'label' => '25'],
                    ['value' => '50', 'label' => '50'],
                    ['value' => '100', 'label' => '100'],
                ],
            ],
        ]);
    }

    public function create(DiamondMountingStoneSynchronizer $stoneSynchronizer): Response
    {
        return Inertia::render('spk-diamond/create', [
            'stoneOptions' => $stoneSynchronizer->stoneOptions(),
            'diamondOptions' => $stoneSynchronizer->diamondOptions(),
            'form' => $this->emptyForm(),
        ]);
    }

    public function store(
        StoreSpkDiamondRequest $request,
        SpkDiamondSynchronizer $synchronizer,
    ): RedirectResponse {
        $validated = $request->validated();
        $actor = $this->actorName($request);
        $production = Production::query()->notDeleted()->findOrFail($validated['spk_id']);

        $document = DB::connection('third')->transaction(function () use (
            $validated,
            $production,
            $actor,
            $synchronizer,
            $request,
        ): SpkDiamond {
            $weights = $this->resolveWeights($validated);

            $document = SpkDiamond::query()->create([
                'no_spk_diamond' => $validated['no_spk_diamond'],
                'spk_id' => $production->row_id,
                'spk_no' => $production->spk_no,
                'dmd_no' => null,
                'tanggal' => $validated['tanggal'],
                'weight_frame' => $weights['frame'],
                'weight_stone' => $weights['stone'],
                'weight_total' => $weights['total'],
                'keterangan' => $validated['keterangan'] ?? null,
                'status' => SpkDiamond::STATUS_DRAFT,
                'is_deleted' => 0,
                'created_date' => now(),
                'created_by' => $actor,
                'modified_date' => now(),
                'modified_by' => $actor,
            ]);

            $synchronizer->sync($document, $request->linePayload(), $actor);

            return $document->refresh();
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'SPK Diamond berhasil disimpan.',
        ]);

        return to_route('spk-diamond.show', $document);
    }

    public function show(SpkDiamond $spkDiamond, DiamondMountingStoneSynchronizer $stoneSynchronizer): Response
    {
        abort_if($spkDiamond->is_deleted === 1, 404);

        $spkDiamond->load([
            'diamondMounting',
            'production.sku',
            'production.categoryPrefix',
        ]);

        $detail = $this->toDetail($spkDiamond, $stoneSynchronizer);

        return Inertia::render('spk-diamond/show', [
            'workflowStatus' => $this->workflowStatus($spkDiamond),
            'spkDiamondItem' => [
                'id' => $spkDiamond->id,
                'noSpkDiamond' => $detail['noSpkDiamond'],
                'tanggal' => $detail['tanggal'],
                'keterangan' => $detail['keterangan'],
                'weightFrame' => $detail['weightFrame'],
                'weightStone' => $detail['weightStone'],
                'weightTotal' => $detail['weightTotal'],
                'status' => $detail['status'],
                'statusLabel' => $detail['statusLabel'],
                'submittedAt' => $detail['submittedAt'],
                'submittedBy' => $detail['submittedBy'],
                'mountingId' => $detail['mountingId'],
                'mountingDocNo' => $detail['mountingDocNo'],
                'stones' => [
                    'setting' => $detail['settingRows'],
                    'return' => [],
                    'diamonds' => $detail['diamondRows'],
                    'mounted' => [],
                ],
                'spk' => $detail['spk'] === null ? null : [
                    'spkId' => $detail['spk']['spkId'],
                    'spkNo' => $detail['spk']['spkNo'],
                    'spkType' => $detail['spk']['spkType'],
                    'orderTypeLabel' => $detail['spk']['orderTypeLabel'],
                    'skuCode' => $detail['spk']['skuCode'],
                    'typeCode' => $detail['spk']['typeCode'],
                    'productItemName' => $detail['spk']['productItemName'],
                    'itemDescription' => $detail['spk']['itemDescription'],
                    'customerName' => $detail['spk']['customerName'] ?? null,
                    'satuan' => $detail['spk']['satuan'],
                    'lastWeight' => $detail['spk']['lastWeight'] ?? null,
                ],
            ],
            'approvalFooter' => $this->approvalFooter($spkDiamond),
            'canEdit' => $spkDiamond->isDraft(),
            'canSubmit' => $spkDiamond->isDraft(),
        ]);
    }

    public function edit(
        SpkDiamond $spkDiamond,
        DiamondMountingStoneSynchronizer $stoneSynchronizer,
    ): Response {
        abort_if($spkDiamond->is_deleted === 1, 404);
        abort_unless($spkDiamond->isDraft(), 403, 'Hanya dokumen Draft yang dapat diubah.');

        $spkDiamond->load('production');

        $spkId = (int) ($spkDiamond->spk_id ?? 0);
        $hasAllocation = $spkId > 0
            && app(SpkAllocationReservedStock::class)->hasActiveLinesForSpk($spkId);

        return Inertia::render('spk-diamond/edit', [
            'stoneOptions' => $stoneSynchronizer->stoneOptions($spkId > 0 ? $spkId : null),
            'diamondOptions' => $hasAllocation
                ? $stoneSynchronizer->allocationDiamondOptions($spkId)
                : $stoneSynchronizer->diamondOptions(null, $spkDiamond->id),
            'form' => $this->toForm($spkDiamond),
            'skuStones' => $spkDiamond->production === null
                ? []
                : app(SpkProductionStoneList::class)->forProduction($spkDiamond->production, false),
        ]);
    }

    public function update(
        UpdateSpkDiamondRequest $request,
        SpkDiamond $spkDiamond,
        SpkDiamondSynchronizer $synchronizer,
    ): RedirectResponse {
        abort_if($spkDiamond->is_deleted === 1, 404);
        abort_unless($spkDiamond->isDraft(), 403);

        $validated = $request->validated();
        $actor = $this->actorName($request);
        $production = Production::query()->notDeleted()->findOrFail($validated['spk_id']);

        DB::connection('third')->transaction(function () use (
            $spkDiamond,
            $validated,
            $production,
            $actor,
            $synchronizer,
            $request,
        ): void {
            $weights = $this->resolveWeights($validated);

            $spkDiamond->update([
                'no_spk_diamond' => $validated['no_spk_diamond'],
                'spk_id' => $production->row_id,
                'spk_no' => $production->spk_no,
                'dmd_no' => null,
                'tanggal' => $validated['tanggal'],
                'weight_frame' => $weights['frame'],
                'weight_stone' => $weights['stone'],
                'weight_total' => $weights['total'],
                'keterangan' => $validated['keterangan'] ?? null,
                'modified_date' => now(),
                'modified_by' => $actor,
            ]);

            $synchronizer->sync($spkDiamond->refresh(), $request->linePayload(), $actor);
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'SPK Diamond berhasil diperbarui.',
        ]);

        return to_route('spk-diamond.show', $spkDiamond);
    }

    public function submit(
        Request $request,
        SpkDiamond $spkDiamond,
        SpkDiamondLedger $ledger,
    ): RedirectResponse {
        abort_if($spkDiamond->is_deleted === 1, 404);
        abort_unless($spkDiamond->isDraft(), 403);

        $actor = $this->actorName($request);

        try {
            DB::connection('third')->transaction(function () use ($spkDiamond, $ledger, $actor): void {
                $ledger->applySubmit($spkDiamond->refresh(), $actor);

                $spkDiamond->update([
                    'status' => SpkDiamond::STATUS_SUBMITTED,
                    'submitted_at' => now(),
                    'submitted_by' => $actor,
                    'modified_date' => now(),
                    'modified_by' => $actor,
                ]);
            });
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first();

            if (filled($message)) {
                Inertia::flash('toast', [
                    'type' => 'error',
                    'message' => (string) $message,
                ]);
            }

            return back()->withErrors($exception->errors());
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'SPK Diamond dikirim ke produksi.',
        ]);

        return to_route('spk-diamond.show', $spkDiamond);
    }

    public function spkStones(Request $request, SpkProductionStoneList $stoneList): JsonResponse
    {
        $spkId = $request->integer('spk_id');

        abort_if($spkId <= 0, 404);

        $production = Production::query()
            ->notDeleted()
            ->findOrFail($spkId);

        return response()->json([
            'status' => true,
            'data' => $stoneList->forProduction($production, false),
        ]);
    }

    public function spkAllocation(Request $request): JsonResponse
    {
        $spkId = $request->integer('spk_id');

        abort_if($spkId <= 0, 404);

        $allocation = SpkAllocation::query()
            ->notDeleted()
            ->where('spk_id', $spkId)
            ->with([
                'stones' => fn ($query) => $query->notDeleted(),
                'diamonds' => fn ($query) => $query->notDeleted(),
            ])
            ->first();

        if ($allocation === null) {
            return response()->json([
                'status' => true,
                'data' => [
                    'setting_stones' => [],
                    'diamonds' => [],
                ],
            ]);
        }

        return response()->json([
            'status' => true,
            'data' => [
                'setting_stones' => $allocation->stones
                    ->map(fn (SpkAllocationStone $line): array => [
                        'stone_id' => (int) $line->stone_id,
                        'pcs' => $line->pcs !== null ? (string) (int) round((float) $line->pcs) : null,
                        'crt' => $line->crt !== null ? (string) $line->crt : null,
                        'notes' => filled($line->notes) ? (string) $line->notes : null,
                    ])
                    ->values()
                    ->all(),
                'diamonds' => $allocation->diamonds
                    ->map(fn (SpkAllocationDiamond $line): array => [
                        'diamond_id' => (int) $line->trdiamond_id,
                    ])
                    ->values()
                    ->all(),
            ],
        ]);
    }

    public function searchSpks(Request $request): JsonResponse
    {
        $search = $request->string('search')->trim()->toString();
        $limit = max(1, min($request->integer('limit', 25), 50));

        $query = Production::query()
            ->notDeleted()
            ->whereNotNull('spk_no')
            ->orderByDesc('row_id')
            ->limit($limit);

        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function ($inner) use ($like): void {
                $inner->where('spk_no', 'like', $like)
                    ->orWhere('item_name', 'like', $like)
                    ->orWhere('customer_name', 'like', $like);
            });
        }

        $productions = $query->get(['row_id', 'spk_no', 'spk_type', 'item_name', 'customer_name', 'qty', 'satuan', 'sku_id', 'category_prefix_id', 'description']);

        return response()->json([
            'status' => true,
            'data' => $productions->map(fn (Production $production): array => [
                'rowId' => (int) $production->row_id,
                'spkNo' => (string) $production->spk_no,
                'customer' => filled($production->customer_name) ? (string) $production->customer_name : '—',
                'item' => filled($production->item_name) ? (string) $production->item_name : '—',
                'qty' => $production->qty ?? 1,
            ])->values()->all(),
        ]);
    }

    public function searchForPasangBatu(Request $request, SpkDiamondMountingBridge $bridge): JsonResponse
    {
        $search = $request->string('search')->trim()->toString();
        $limit = max(1, min($request->integer('limit', 25), 50));

        $query = SpkDiamond::query()
            ->notDeleted()
            ->submitted()
            ->whereDoesntHave('diamondMounting', fn ($mounting) => $mounting->notDeleted())
            ->with(['production' => fn ($q) => $q->notDeleted()->select(['row_id', 'spk_no', 'item_name', 'customer_name'])])
            ->orderByDesc('id')
            ->limit($limit);

        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function ($inner) use ($like): void {
                $inner->where('no_spk_diamond', 'like', $like)
                    ->orWhere('spk_no', 'like', $like)
                    ->orWhereHas('production', fn ($production) => $production->where('spk_no', 'like', $like));
            });
        }

        return response()->json([
            'status' => true,
            'data' => $query->get()->map(function (SpkDiamond $document) use ($bridge): array {
                $prefill = $bridge->prefillForMounting($document);

                return [
                    'id' => $document->id,
                    'noSpkDiamond' => $document->no_spk_diamond,
                    'spkNo' => $prefill['spkNo'],
                    'tanggal' => $document->tanggal?->format('Y-m-d'),
                    'keterangan' => $document->keterangan,
                    'itemName' => $document->production?->item_name,
                    'prefill' => $prefill,
                ];
            })->values()->all(),
        ]);
    }

    public function prefill(SpkDiamond $spkDiamond, SpkDiamondMountingBridge $bridge): JsonResponse
    {
        abort_if($spkDiamond->is_deleted === 1, 404);
        abort_unless($spkDiamond->isSubmitted(), 404);

        return response()->json([
            'status' => true,
            'data' => $bridge->prefillForMounting($spkDiamond),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyForm(): array
    {
        return [
            'noSpkDiamond' => '',
            'tanggal' => now()->format('Y-m-d'),
            'keterangan' => '',
            'weightFrame' => '',
            'weightStone' => '',
            'weightTotal' => '',
            'spk' => null,
            'settingStones' => [],
            'diamonds' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function toForm(SpkDiamond $document): array
    {
        $document->load([
            'stones' => fn ($query) => $query->notDeleted(),
            'diamonds' => fn ($query) => $query->notDeleted(),
            'production.sku',
            'production.categoryPrefix',
            'diamondMounting',
        ]);

        return [
            'id' => $document->id,
            'noSpkDiamond' => $document->no_spk_diamond,
            'tanggal' => $document->tanggal?->format('Y-m-d') ?? '',
            'keterangan' => $document->keterangan ?? '',
            'weightFrame' => $this->formatDecimal($document->weight_frame, 2) ?? '',
            'weightStone' => $this->formatDecimal($document->weight_stone, 3) ?? '',
            'weightTotal' => $this->formatDecimal($document->weight_total, 3) ?? '',
            'spk' => $document->production === null ? null : [
                'spkId' => (int) $document->production->row_id,
                'spkNo' => (string) $document->production->spk_no,
                'lastWeight' => $this->formatDecimal($document->production->last_weight, 2),
                ...$this->productionSpkInfoFields($document->production),
                'customerName' => filled($document->production->customer_name)
                    ? (string) $document->production->customer_name
                    : null,
            ],
            'settingStones' => $document->stones->map(fn ($line): array => [
                'stoneId' => (int) $line->stone_id,
                'pcs' => $this->formatPcsDisplay($line->pcs) ?? '',
                'crt' => $line->crt !== null ? number_format((float) $line->crt, 3, '.', '') : '',
                'notes' => (string) ($line->notes ?? ''),
            ])->values()->all(),
            'diamonds' => $document->diamonds->map(fn ($line): array => [
                'diamondId' => (int) $line->trdiamond_id,
            ])->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function toDetail(SpkDiamond $document, DiamondMountingStoneSynchronizer $stoneSynchronizer): array
    {
        $form = $this->toForm($document);
        $stoneNames = collect($stoneSynchronizer->stoneOptions())->keyBy('value');

        $settingRows = collect($form['settingStones'])->map(function (array $line) use ($stoneNames): array {
            $label = $stoneNames->get((string) $line['stoneId'])['label'] ?? 'Batu';

            return [
                'batu' => $label,
                'pcs' => $this->formatPcsDisplay($line['pcs']),
                'crt' => $line['crt'],
                'notes' => $line['notes'] !== '' ? $line['notes'] : null,
            ];
        })->values()->all();

        $diamondOptions = collect($stoneSynchronizer->diamondOptions(null, $document->id))->keyBy('value');
        $diamondRows = collect($form['diamonds'])->map(function (array $line) use ($diamondOptions): array {
            $option = $diamondOptions->get((string) $line['diamondId']);

            return [
                'kode' => $option['code'] ?? '—',
                'diamond' => $option['diamondType'] ?? '—',
                'bentuk' => $option['shape'] ?? '—',
                'sertifikat' => $option['certificate'] ?? '—',
                'crt' => $option['crt'] ?? null,
            ];
        })->values()->all();

        return [
            ...$form,
            'status' => $document->status,
            'statusLabel' => $document->statusLabel(),
            'submittedAt' => $document->submitted_at?->format('d/m/Y H:i'),
            'submittedBy' => $document->submitted_by,
            'settingRows' => $settingRows,
            'diamondRows' => $diamondRows,
            'mountingId' => $document->diamondMounting?->row_id,
            'mountingDocNo' => $document->diamondMounting?->doc_no,
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{frame: string|null, stone: string|null, total: string|null}
     */
    private function resolveWeights(array $validated): array
    {
        $frame = filled($validated['weight_frame'] ?? null)
            ? number_format((float) $validated['weight_frame'], 2, '.', '')
            : null;
        $stone = filled($validated['weight_stone'] ?? null)
            ? number_format((float) $validated['weight_stone'], 3, '.', '')
            : null;
        $total = null;

        if ($frame !== null || $stone !== null) {
            $total = number_format((float) ($frame ?? 0) + (float) ($stone ?? 0), 3, '.', '');
        }

        return ['frame' => $frame, 'stone' => $stone, 'total' => $total];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function statusOptions(): array
    {
        return [
            ['value' => 'all', 'label' => 'Semua status'],
            ['value' => SpkDiamond::STATUS_DRAFT, 'label' => 'Draft'],
            ['value' => SpkDiamond::STATUS_SUBMITTED, 'label' => 'Kirim ke produksi'],
            ['value' => SpkDiamond::STATUS_MOUNTING, 'label' => 'Sedang proses pasang batu'],
            ['value' => SpkDiamond::STATUS_DONE, 'label' => 'Selesai'],
        ];
    }

    /**
     * @return list<string>
     */
    private function resolveIndexStatusFilters(mixed $status): array
    {
        $values = is_array($status) ? $status : [$status];
        $allowed = [
            SpkDiamond::STATUS_DRAFT,
            SpkDiamond::STATUS_SUBMITTED,
            SpkDiamond::STATUS_MOUNTING,
            SpkDiamond::STATUS_DONE,
        ];

        return collect($values)
            ->map(fn (mixed $value): string => strtoupper(trim((string) $value)))
            ->filter(fn (string $value): bool => in_array($value, $allowed, true))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function toIndexRow(SpkDiamond $document): array
    {
        $production = $document->production;

        return [
            'id' => $document->id,
            'noSpkDiamond' => $document->no_spk_diamond,
            'tanggal' => $document->tanggal?->format('Y-m-d'),
            'status' => $document->status,
            'statusLabel' => $document->statusLabel(),
            'spkNo' => $document->spk_no ?? $production?->spk_no,
            'orderReference' => SpkOrderReference::label($production),
            ...$this->productionSkuFields($production),
            'spkImageUrl' => SpkItemImageUrl::fromFileName($production?->file_name),
            'weightFrame' => $this->formatDecimal($document->weight_frame, 2),
            'weightStone' => $this->formatDecimal($document->weight_stone, 3),
            'weightTotal' => $this->formatDecimal($document->weight_total, 3),
            'keterangan' => filled($document->keterangan) ? (string) $document->keterangan : null,
        ];
    }

    /**
     * @return array{
     *     key: string,
     *     label: string,
     *     stageIndex: int,
     *     stages: list<array{key: string, label: string}>
     * }
     */
    private function workflowStatus(SpkDiamond $document): array
    {
        $stages = [
            ['key' => 'draft', 'label' => 'Draft'],
            ['key' => 'submitted', 'label' => 'Kirim ke produksi'],
            ['key' => 'mounting', 'label' => 'Pasang batu'],
            ['key' => 'done', 'label' => 'Selesai'],
        ];

        $stageIndex = match (strtoupper(trim($document->status))) {
            SpkDiamond::STATUS_SUBMITTED, SpkDiamond::LEGACY_STATUS_SUBMITTED_TYPO => 1,
            SpkDiamond::STATUS_MOUNTING => 2,
            SpkDiamond::STATUS_DONE => 3,
            default => 0,
        };

        return [
            'key' => strtolower(trim($document->status)),
            'label' => $document->statusLabel(),
            'stageIndex' => $stageIndex,
            'stages' => $stages,
        ];
    }

    /**
     * @return list<array{title: string, name: string, date: string}>
     */
    private function approvalFooter(SpkDiamond $document): array
    {
        return [
            [
                'title' => 'Dibuat Oleh',
                'name' => filled($document->created_by) ? (string) $document->created_by : '—',
                'date' => $document->created_date?->format('d/m/Y H:i') ?? '—',
            ],
            [
                'title' => 'Dikirim ke Produksi',
                'name' => filled($document->submitted_by) ? (string) $document->submitted_by : '—',
                'date' => $document->submitted_at?->format('d/m/Y H:i') ?? '—',
            ],
            [
                'title' => 'Selesai',
                'name' => filled($document->completed_by) ? (string) $document->completed_by : '—',
                'date' => $document->completed_at?->format('d/m/Y H:i') ?? '—',
            ],
        ];
    }

    private function formatPcsDisplay(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $number = (float) str_replace(',', '.', trim((string) $value));

        if (! is_finite($number)) {
            return trim((string) $value);
        }

        return (string) (int) round($number);
    }

    private function formatDecimal(mixed $value, int $precision): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return number_format((float) $value, $precision, '.', '');
    }

    private function actorName(Request $request): string
    {
        $user = $request->user();

        return filled($user?->name) ? (string) $user->name : 'system';
    }

    /**
     * @return array{
     *     spkType: string|null,
     *     orderTypeLabel: string|null,
     *     skuCode: string|null,
     *     typeCode: string|null,
     *     productItemName: string|null,
     *     itemDescription: string|null,
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
                'satuan' => '—',
            ];
        }

        return [
            'spkType' => filled($production->spk_type)
                ? (string) $production->spk_type
                : null,
            'orderTypeLabel' => app(ProductionOrderTypeLabel::class)->forProduction($production),
            ...$this->productionSkuFields($production),
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
}
