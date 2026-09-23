<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePolishFinishedGoodRequest;
use App\Http\Requests\UpdatePolishFinishedGoodRequest;
use App\Models\PolishFinishedGood;
use App\Models\Production;
use App\Support\PolishFinishedGoodApprovalService;
use App\Support\PolishFinishedGoodDocNumberGenerator;
use App\Support\PolishFinishedGoodSpkEligibility;
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

class PolishFinishedGoodController extends Controller
{
    public function index(Request $request, PolishFinishedGoodSpkEligibility $spkEligibility): Response
    {
        $search = $request->string('search')->trim()->toString();
        $perPage = $request->integer('per_page', 10);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;

        $documents = PolishFinishedGood::query()
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
            ->through(fn (PolishFinishedGood $document): array => $this->toListItem($document));

        return Inertia::render('poles-chrome/index', [
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
     * @return array<string, mixed>
     */
    private function toListItem(PolishFinishedGood $document): array
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
