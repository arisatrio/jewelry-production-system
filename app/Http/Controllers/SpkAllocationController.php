<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateSpkAllocationRequest;
use App\Models\Production;
use App\Models\SpkAllocation;
use App\Models\SpkAllocationDiamond;
use App\Models\SpkAllocationStone;
use App\Support\DiamondMountingStoneSynchronizer;
use App\Support\ProductionOrderTypeLabel;
use App\Support\SpkAllocationIndexRowMapper;
use App\Support\SpkAllocationReservedStock;
use App\Support\SpkAllocationSpkEligibility;
use App\Support\SpkAllocationSynchronizer;
use App\Support\SpkItemImageUrl;
use App\Support\SpkOrderReference;
use App\Support\SpkProductionStoneList;
use App\Support\SpkQtyUnit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class SpkAllocationController extends Controller
{
    public function index(
        Request $request,
        SpkAllocationReservedStock $reservedStock,
        SpkAllocationSpkEligibility $spkEligibility,
        SpkAllocationIndexRowMapper $indexRowMapper,
    ): Response {
        $search = $request->string('search')->trim()->toString();
        $allocationFilter = $request->string('allocation')->trim()->toString();
        if (! in_array($allocationFilter, ['all', 'allocated', 'empty'], true)) {
            $allocationFilter = 'all';
        }
        $perPage = max(10, min($request->integer('per_page', 50), 100));

        $productions = Production::query()
            ->notDeleted()
            ->whereNotNull('spk_no')
            ->tap(fn (Builder $query) => $spkEligibility->applyManagerApprovedScope($query))
            ->with(['sku', 'categoryPrefix'])
            ->when(
                in_array($allocationFilter, ['allocated', 'empty'], true),
                fn (Builder $query) => $this->applyAllocationFilterScope($query, $allocationFilter),
            )
            ->when($search !== '', function ($query) use ($search): void {
                $like = '%'.$search.'%';
                $query->where(function ($inner) use ($like): void {
                    $inner->where('spk_no', 'like', $like)
                        ->orWhere('item_name', 'like', $like)
                        ->orWhere('customer_name', 'like', $like);
                });
            })
            ->orderByDesc('row_id')
            ->paginate($perPage)
            ->withQueryString();

        $spkIds = $productions->getCollection()
            ->map(fn (Production $production): int => (int) $production->row_id)
            ->all();

        $summaries = $this->allocationSummariesForSpkIds($spkIds);
        $requestOrders = $indexRowMapper->requestOrdersByDocNo($productions->getCollection());

        $productions->setCollection(
            $productions->getCollection()
                ->map(function (Production $production) use ($summaries, $reservedStock, $indexRowMapper, $requestOrders): array {
                    $spkId = (int) $production->row_id;
                    $summary = $summaries[$spkId] ?? ['stoneLines' => 0, 'diamondLines' => 0];
                    $hasAllocation = $reservedStock->hasActiveLinesForSpk($spkId);
                    $requestOrder = $requestOrders[trim((string) $production->request_order_no)] ?? null;

                    return $indexRowMapper->map($production, $requestOrder, $summary, $hasAllocation);
                }),
        );

        return Inertia::render('alokasi-batu/index', [
            'productions' => $productions,
            'filters' => [
                'search' => $search,
                'allocation' => $allocationFilter,
                'per_page' => $perPage,
            ],
            'defaultFilters' => [
                'allocation' => 'all',
            ],
            'filterOptions' => [
                'allocation' => [
                    ['value' => 'all', 'label' => 'Semua'],
                    ['value' => 'allocated', 'label' => 'Sudah dialokasi'],
                    ['value' => 'empty', 'label' => 'Belum dialokasi'],
                ],
                'per_page' => [
                    ['value' => '10', 'label' => '10'],
                    ['value' => '25', 'label' => '25'],
                    ['value' => '50', 'label' => '50'],
                    ['value' => '100', 'label' => '100'],
                ],
            ],
        ]);
    }

    public function show(
        Production $production,
        DiamondMountingStoneSynchronizer $stoneSynchronizer,
        SpkProductionStoneList $stoneList,
        SpkAllocationSpkEligibility $spkEligibility,
    ): Response {
        abort_if($production->is_deleted === 1, 404);
        abort_unless($spkEligibility->isManagerApproved($production), 404);

        $spkId = (int) $production->row_id;
        $production->load(['sku', 'categoryPrefix']);

        $allocation = SpkAllocation::query()
            ->notDeleted()
            ->where('spk_id', $spkId)
            ->with([
                'stones' => fn ($query) => $query->notDeleted(),
                'diamonds' => fn ($query) => $query->notDeleted(),
            ])
            ->first();

        return Inertia::render('alokasi-batu/show', [
            'production' => $this->toProductionHeader($production),
            'skuStones' => $stoneList->forProduction($production, true),
            'stoneOptions' => $stoneSynchronizer->stoneOptions($spkId),
            'diamondOptions' => $stoneSynchronizer->allocationDiamondOptions($spkId),
            'form' => $this->toForm($allocation),
        ]);
    }

    public function update(
        UpdateSpkAllocationRequest $request,
        Production $production,
        SpkAllocationSynchronizer $synchronizer,
        SpkAllocationSpkEligibility $spkEligibility,
    ): RedirectResponse {
        abort_if($production->is_deleted === 1, 404);
        abort_unless($spkEligibility->isManagerApproved($production), 404);

        $actor = (string) ($request->user()?->name ?? 'system');

        DB::connection('third')->transaction(function () use (
            $production,
            $request,
            $synchronizer,
            $actor,
        ): void {
            $synchronizer->sync($production, $request->linePayload(), $actor);
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Alokasi batu berhasil disimpan.',
        ]);

        return to_route('alokasi-batu.show', $production);
    }

    private function applyAllocationFilterScope(Builder $query, string $allocationFilter): void
    {
        if (! Schema::connection('third')->hasTable('spk_allocation')) {
            return;
        }

        $hasActiveAllocation = function ($sub): void {
            $sub->selectRaw('1')
                ->from('spk_allocation as header')
                ->whereColumn('header.spk_id', 'spk.row_id')
                ->where('header.is_deleted', 0)
                ->where(function ($inner): void {
                    if (Schema::connection('third')->hasTable('spk_allocation_stone')) {
                        $inner->orWhereExists(function ($stone): void {
                            $stone->selectRaw('1')
                                ->from('spk_allocation_stone as line')
                                ->whereColumn('line.spk_allocation_id', 'header.id')
                                ->where('line.is_deleted', 0);
                        });
                    }

                    if (Schema::connection('third')->hasTable('spk_allocation_diamond')) {
                        $inner->orWhereExists(function ($diamond): void {
                            $diamond->selectRaw('1')
                                ->from('spk_allocation_diamond as line')
                                ->whereColumn('line.spk_allocation_id', 'header.id')
                                ->where('line.is_deleted', 0);
                        });
                    }
                });
        };

        if ($allocationFilter === 'allocated') {
            $query->whereExists($hasActiveAllocation);

            return;
        }

        $query->whereNotExists($hasActiveAllocation);
    }

    /**
     * @param  list<int>  $spkIds
     * @return array<int, array{stoneLines: int, diamondLines: int}>
     */
    private function allocationSummariesForSpkIds(array $spkIds): array
    {
        if (
            $spkIds === []
            || ! Schema::connection('third')->hasTable('spk_allocation')
        ) {
            return [];
        }

        $headers = SpkAllocation::query()
            ->notDeleted()
            ->whereIn('spk_id', $spkIds)
            ->get(['id', 'spk_id']);

        if ($headers->isEmpty()) {
            return [];
        }

        $allocationIds = $headers->pluck('id')->all();

        $stoneCounts = SpkAllocationStone::query()
            ->notDeleted()
            ->whereIn('spk_allocation_id', $allocationIds)
            ->selectRaw('spk_allocation_id, COUNT(*) as line_count')
            ->groupBy('spk_allocation_id')
            ->pluck('line_count', 'spk_allocation_id');

        $diamondCounts = SpkAllocationDiamond::query()
            ->notDeleted()
            ->whereIn('spk_allocation_id', $allocationIds)
            ->selectRaw('spk_allocation_id, COUNT(*) as line_count')
            ->groupBy('spk_allocation_id')
            ->pluck('line_count', 'spk_allocation_id');

        $map = [];

        foreach ($headers as $header) {
            $map[(int) $header->spk_id] = [
                'stoneLines' => (int) ($stoneCounts[$header->id] ?? 0),
                'diamondLines' => (int) ($diamondCounts[$header->id] ?? 0),
            ];
        }

        return $map;
    }

    /**
     * @return array<string, mixed>
     */
    private function toProductionHeader(Production $production): array
    {
        return [
            'spkId' => (int) $production->row_id,
            'spkNo' => (string) $production->spk_no,
            'customerName' => filled($production->customer_name)
                ? (string) $production->customer_name
                : null,
            'itemName' => filled($production->item_name)
                ? (string) $production->item_name
                : null,
            'orderReference' => SpkOrderReference::label($production),
            'orderTypeLabel' => app(ProductionOrderTypeLabel::class)->forProduction($production),
            'skuCode' => filled($production->sku?->sku_code)
                ? (string) $production->sku->sku_code
                : null,
            'typeCode' => filled($production->categoryPrefix?->prefix)
                ? (string) $production->categoryPrefix->prefix
                : null,
            'productItemName' => filled($production->sku?->item_original)
                ? (string) $production->sku->item_original
                : null,
            'spkImageUrl' => SpkItemImageUrl::fromFileName($production->file_name),
            'qty' => $production->qty ?? 1,
            'satuan' => SpkQtyUnit::label($production->qty, $production->satuan),
        ];
    }

    /**
     * @return array{settingStones: list<array<string, mixed>>, diamonds: list<array<string, mixed>>}
     */
    private function toForm(?SpkAllocation $allocation): array
    {
        if ($allocation === null) {
            return [
                'settingStones' => [],
                'diamonds' => [],
            ];
        }

        return [
            'settingStones' => $allocation->stones
                ->map(fn (SpkAllocationStone $line): array => [
                    'stone_id' => (string) $line->stone_id,
                    'pcs' => $line->pcs !== null ? (string) (int) round((float) $line->pcs) : '',
                    'crt' => $line->crt !== null ? (string) $line->crt : '',
                    'notes' => filled($line->notes) ? (string) $line->notes : '',
                ])
                ->values()
                ->all(),
            'diamonds' => $allocation->diamonds
                ->map(fn (SpkAllocationDiamond $line): array => [
                    'diamond_id' => (string) $line->trdiamond_id,
                ])
                ->values()
                ->all(),
        ];
    }
}
