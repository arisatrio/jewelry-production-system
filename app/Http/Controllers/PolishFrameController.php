<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePolishFrameRequest;
use App\Http\Requests\UpdatePolishFrameRequest;
use App\Models\PolishFrame;
use App\Models\Production;
use App\Support\PolishFrameApprovalService;
use App\Support\PolishFrameDocNumberGenerator;
use App\Support\PolishFrameSpkEligibility;
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

class PolishFrameController extends Controller
{
    public function index(Request $request, PolishFrameSpkEligibility $spkEligibility): Response
    {
        $search = $request->string('search')->trim()->toString();
        $perPage = $request->integer('per_page', 10);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;

        $documents = PolishFrame::query()
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
            ->orderByDesc('row_id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (PolishFrame $document): array => $this->toListItem($document));

        return Inertia::render('poles-rangka/index', [
            'documents' => $documents,
            'spkStatusCounts' => $this->spkStatusCounts($spkEligibility),
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('poles-rangka/create', [
            'formDocumentNo' => (string) config('spk.poles_rangka_form_document_no'),
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
        $spkEligibility = app(PolishFrameSpkEligibility::class);

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
            ? $spkEligibility->polishFrameRefsBySpkIds(
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
                    'polishFrameId' => $ref['polishFrameId'] ?? null,
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
        StorePolishFrameRequest $request,
        PolishFrameDocNumberGenerator $docNumberGenerator,
    ): RedirectResponse {
        $validated = $request->validated();
        $actor = $this->actorName($request);

        $document = DB::connection('third')->transaction(function () use (
            $validated,
            $actor,
            $docNumberGenerator,
        ): PolishFrame {
            $sendDate = $validated['send_craftsman_date'] ?? null;
            $receivedDate = $validated['received_craftsman_date'] ?? null;

            $document = PolishFrame::query()->create([
                'doc_no' => $docNumberGenerator->generate(),
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
                'frame_id' => null,
                'notes' => $validated['notes'] ?? null,
                'status' => null,
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
                $eligibility = app(PolishFrameSpkEligibility::class);
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
            'message' => 'Dokumen Poles Rangka berhasil ditambahkan.',
        ]);

        return to_route('poles-rangka.show', $document);
    }

    public function show(
        Request $request,
        PolishFrame $polesRangka,
        PolishFrameApprovalService $approvalService,
    ): Response {
        abort_if($polesRangka->is_deleted === 1, 404);

        $polesRangka->load([
            'production' => fn ($productionQuery) => $productionQuery
                ->notDeleted()
                ->with($this->productionSpkInfoRelations())
                ->select($this->productionSpkInfoColumns()),
        ]);

        return Inertia::render('poles-rangka/show', [
            'polishFrameItem' => $this->toDetailItem($polesRangka),
            'workflowStatus' => $approvalService->map($polesRangka),
            'approvalHistory' => $approvalService->history($polesRangka),
            'approvalFooter' => $approvalService->footerColumns(
                $polesRangka,
                $this->actorName($request),
            ),
            'approval' => $approvalService->abilitiesFor($polesRangka, $request->user()),
        ]);
    }

    public function edit(
        Request $request,
        PolishFrame $polesRangka,
        PolishFrameApprovalService $approvalService,
    ): Response {
        abort_if($polesRangka->is_deleted === 1, 404);
        abort_unless(
            $approvalService->abilitiesFor($polesRangka, $request->user())['canEdit'],
            403,
            'Dokumen Poles Rangka tidak dapat diubah pada status saat ini.',
        );

        $polesRangka->load([
            'production' => fn ($productionQuery) => $productionQuery
                ->notDeleted()
                ->with($this->productionSpkInfoRelations())
                ->select($this->productionSpkInfoColumns()),
        ]);

        $production = $polesRangka->production;

        return Inertia::render('poles-rangka/edit', [
            'formDocumentNo' => (string) config('spk.poles_rangka_form_document_no'),
            'craftsmanOptions' => $this->craftsmanOptions(),
            'statusItemOptions' => $this->statusItemOptions(),
            'form' => [
                'id' => (int) $polesRangka->row_id,
                'docNo' => $polesRangka->doc_no,
                'sendCraftsmanDate' => $polesRangka->send_craftsman_date?->format('Y-m-d H:i') ?? '',
                'receivedCraftsmanDate' => $polesRangka->received_craftsman_date?->format('Y-m-d H:i') ?? '',
                'craftsmanId' => filled($polesRangka->craftsman_id) && (int) $polesRangka->craftsman_id > 0
                    ? (int) $polesRangka->craftsman_id
                    : null,
                'notes' => filled($polesRangka->notes) ? (string) $polesRangka->notes : '',
                'statusItem' => filled($polesRangka->status_item)
                    ? (string) $polesRangka->status_item
                    : null,
                'startWeight' => $this->formatDecimal($polesRangka->start_weight) ?? '',
                'finishWeight' => $this->formatDecimal($polesRangka->finish_weight) ?? '',
                'spk' => $production === null ? null : [
                    'spkId' => (int) $production->row_id,
                    'spkNo' => $production->spk_no,
                    ...$this->productionSpkInfoFields($production),
                ],
            ],
            'approval' => $approvalService->abilitiesFor($polesRangka, $request->user()),
        ]);
    }

    public function submit(
        Request $request,
        PolishFrame $polesRangka,
        PolishFrameApprovalService $approvalService,
    ): RedirectResponse {
        abort_if($polesRangka->is_deleted === 1, 404);

        if (! $approvalService->abilitiesFor($polesRangka, $request->user())['canSubmit']) {
            abort(403, 'Dokumen ini tidak dapat dikirim ke Manager Produksi.');
        }

        try {
            $approvalService->submit($polesRangka, $this->actorName($request));
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['approval' => $exception->getMessage()]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Dokumen Poles Rangka dikirim ke Manager Produksi.',
        ]);

        return to_route('poles-rangka.show', $polesRangka);
    }

    public function managerApprove(
        Request $request,
        PolishFrame $polesRangka,
        PolishFrameApprovalService $approvalService,
    ): RedirectResponse {
        abort_if($polesRangka->is_deleted === 1, 404);

        if (! $approvalService->abilitiesFor($polesRangka, $request->user())['canManagerApprove']) {
            abort(403, 'Dokumen ini tidak dapat di-approve oleh Manager Produksi.');
        }

        try {
            $approvalService->managerApprove($polesRangka, $this->actorName($request));
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['approval' => $exception->getMessage()]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Dokumen Poles Rangka di-approve oleh Manager Produksi.',
        ]);

        return to_route('poles-rangka.show', $polesRangka);
    }

    public function complete(
        Request $request,
        PolishFrame $polesRangka,
        PolishFrameApprovalService $approvalService,
    ): RedirectResponse {
        abort_if($polesRangka->is_deleted === 1, 404);

        if (! $approvalService->abilitiesFor($polesRangka, $request->user())['canComplete']) {
            abort(403, 'Dokumen ini tidak dapat diselesaikan.');
        }

        try {
            $approvalService->complete($polesRangka, $this->actorName($request));
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['approval' => $exception->getMessage()]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Dokumen Poles Rangka selesai.',
        ]);

        return to_route('poles-rangka.show', $polesRangka);
    }

    public function update(
        UpdatePolishFrameRequest $request,
        PolishFrame $polesRangka,
        PolishFrameApprovalService $approvalService,
    ): RedirectResponse {
        abort_if($polesRangka->is_deleted === 1, 404);
        abort_unless(
            $approvalService->abilitiesFor($polesRangka, $request->user())['canEdit'],
            403,
            'Dokumen Poles Rangka tidak dapat diubah pada status saat ini.',
        );

        $validated = $request->validated();
        $actor = $this->actorName($request);

        DB::connection('third')->transaction(function () use ($polesRangka, $validated, $actor): void {
            $sendDate = $validated['send_craftsman_date'] ?? null;
            $receivedDate = $validated['received_craftsman_date'] ?? null;

            $polesRangka->update([
                'spk_id' => $validated['spk_id'],
                'craftsman_id' => $validated['craftsman_id'] ?? null,
                'date_from' => $sendDate,
                'date_to' => $receivedDate,
                'start_weight' => $validated['start_weight'] ?? null,
                'finish_weight' => $validated['finish_weight'] ?? null,
                'status_item' => $validated['status_item'] ?? null,
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
                app(PolishFrameSpkEligibility::class)->syncLastWeight(
                    $production,
                    $validated['finish_weight'] ?? null,
                    $actor,
                );
            }

            $this->recalculateShrink($polesRangka->refresh());
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Dokumen Poles Rangka berhasil diperbarui.',
        ]);

        return to_route('poles-rangka.show', $polesRangka);
    }

    /**
     * @return array<string, mixed>
     */
    private function toListItem(PolishFrame $document): array
    {
        return [
            'id' => (int) $document->row_id,
            'docNo' => $document->doc_no,
            'transDate' => $document->send_craftsman_date?->format('Y-m-d'),
            'status' => filled($document->status) ? (string) $document->status : null,
            'statusLabel' => $document->statusLabel(),
            'statusItem' => filled($document->status_item) ? (string) $document->status_item : null,
            'spkNo' => $document->production?->spk_no,
            'startWeight' => $this->formatDecimal($document->start_weight),
            'finishWeight' => $this->formatDecimal($document->finish_weight),
            'shrink' => $this->formatDecimal($document->shrink),
            'notes' => filled($document->notes) ? (string) $document->notes : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function toDetailItem(PolishFrame $document): array
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

    private function recalculateShrink(PolishFrame $document): void
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
        return collect(PolishFrame::statusItemOptions())
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
    private function spkStatusCounts(PolishFrameSpkEligibility $spkEligibility): array
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
