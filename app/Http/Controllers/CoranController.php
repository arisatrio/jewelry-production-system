<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCoranRequest;
use App\Models\Coran;
use App\Models\CoranSpk;
use App\Models\Production;
use App\Support\CoranApprovalService;
use App\Support\CoranDocNumberGenerator;
use App\Support\CoranMaterialBreakdown;
use App\Support\ProductionOrderTypeLabel;
use App\Support\SpkQtyUnit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

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

        return Inertia::render('coran/index', [
            'corans' => $corans,
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
            ],
        ]);
    }

    /**
     * Show the form for creating a new coran document.
     */
    public function create(): Response
    {
        return Inertia::render('coran/create', [
            'formDocumentNo' => (string) config('spk.coran_form_document_no'),
            'statusOptions' => $this->detailStatusOptions(),
            'craftsmanOptions' => $this->craftsmanOptions(),
            'form' => [
                'transDate' => now()->format('Y-m-d'),
                'craftsmanId' => null,
                'details' => [],
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
            ->whereNotNull('spk_no')
            ->when($exclude !== [], fn ($builder) => $builder->whereNotIn('row_id', $exclude))
            ->with($this->productionSpkInfoRelations())
            ->select([
                ...$this->productionSpkInfoColumns(),
                'gold_color',
            ])
            ->orderByDesc('row_id')
            ->limit($limit);

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

        return response()->json([
            'status' => true,
            'data' => $productions->map(function (Production $production): array {
                return [
                    'rowId' => (int) $production->row_id,
                    'spkNo' => (string) $production->spk_no,
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
    ): RedirectResponse {
        $validated = $request->validated();
        $actor = $this->actorName($request);
        $details = $validated['details'];

        $coran = DB::connection('third')->transaction(function () use ($validated, $details, $actor, $docNumberGenerator): Coran {
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
                'shrink' => null,
                'weight' => number_format($totalWeight, 3, '.', ''),
                'status' => null,
                'is_deleted' => 0,
                'created_date' => now(),
                'created_by' => $actor,
                'modified_date' => now(),
                'modified_by' => $actor,
            ]);

            $this->storeDetails($coran, $details, $actor);

            return $coran;
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
        ]);
    }

    /**
     * @param  list<array{spk_id: int, weight?: string|null, status?: string|null}>  $details
     */
    private function storeDetails(Coran $coran, array $details, string $actor): void
    {
        foreach ($details as $detail) {
            $coran->details()->create([
                'spk_id' => $detail['spk_id'],
                'weight' => filled($detail['weight'] ?? null)
                    ? $detail['weight']
                    : null,
                'status' => filled($detail['status'] ?? null)
                    ? (string) $detail['status']
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
                    'status' => filled($detail->status) ? (string) $detail->status : null,
                    'statusLabel' => $this->spkStatusLabel(
                        filled($detail->status) ? (string) $detail->status : null,
                    ),
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
                'weight' => number_format($weight, 3, '.', ''),
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

        return number_format((float) $numbers->sum(), 3, '.', '');
    }

    private function formatDecimal(mixed $value): ?string
    {
        $number = $this->toFloat($value);

        if ($number === null) {
            return null;
        }

        return number_format($number, 3, '.', '');
    }

    private function toFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }
}
