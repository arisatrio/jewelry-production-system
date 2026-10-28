<?php

namespace App\Http\Controllers;

use App\Http\Requests\IssueTrxComponentStockRequest;
use App\Http\Requests\StoreMsComponentRequest;
use App\Http\Requests\StoreTrxComponentStockRequest;
use App\Http\Requests\UpdateMsComponentRequest;
use App\Models\MsComponent;
use App\Models\Production;
use App\Models\TrxComponent;
use App\Support\ComponentStockService;
use App\Support\GoldColorOptions;
use App\Support\InventoryMasterPhotoStorage;
use App\Support\SpkComponentStockRequestService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MsComponentController extends Controller
{
    private const INDEX_DEFAULT_SORT = 'category';

    private const INDEX_DEFAULT_DIRECTION = 'asc';

    /**
     * Display a listing of komponen masters.
     */
    public function index(
        Request $request,
        ComponentStockService $stock,
        SpkComponentStockRequestService $componentRequests,
    ): Response {
        $search = $request->string('search')->trim()->toString();
        $category = $request->string('category')->trim()->toString();
        $perPage = $request->integer('per_page', 50);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 50;
        $sort = $this->resolveIndexSort($request->string('sort')->toString());
        $direction = $this->resolveIndexDirection($request->string('direction')->toString());

        if (! in_array($category, $this->categoryOptions(), true)) {
            $category = '';
        }

        $stockTableAvailable = $stock->tableAvailable();

        $items = MsComponent::query()
            ->notDeleted()
            ->when($category !== '', function ($query) use ($category): void {
                $query->where('component_category', $category);
            })
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('component_category', 'like', "%{$search}%")
                        ->orWhere('item', 'like', "%{$search}%")
                        ->orWhere('item_code', 'like', "%{$search}%")
                        ->orWhere('item_name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->tap(fn (Builder $query) => $this->applyIndexSort($query, $sort, $direction, $stockTableAvailable))
            ->paginate($perPage)
            ->withQueryString();

        $stockCounts = $stock->tableAvailable()
            ? $stock->countsByMsComponent(
                $items->getCollection()->map(fn (MsComponent $item): int => (int) $item->row_id)->values()->all(),
            )
            : [];

        $items->through(function (MsComponent $item) use ($stockCounts): array {
            $counts = $stockCounts[(int) $item->row_id] ?? [
                'stockIn' => 0,
                'stockOut' => 0,
                'balance' => 0,
                'balanceLength' => '0.00',
            ];

            return [
                ...$this->toListItem($item),
                ...$counts,
            ];
        });

        $totalAvailable = $stock->tableAvailable()
            ? $stock->totalAvailableBalance($search, $category)['balance']
            : 0;

        return Inertia::render('inventory/component/index', [
            'items' => $items,
            'categories' => $this->categoryOptions(),
            'colorOptions' => GoldColorOptions::all(),
            'summary' => [
                'totalAvailable' => $totalAvailable,
            ],
            'componentRequestCount' => $componentRequests->tableAvailable()
                ? $componentRequests->pendingCount()
                : 0,
            'filters' => [
                'search' => $search,
                'category' => $category,
                'per_page' => $perPage,
                'sort' => $sort,
                'direction' => $direction,
            ],
            'filterOptions' => [
                'sort' => [
                    ['value' => 'category', 'label' => 'Kategori'],
                    ['value' => 'item', 'label' => 'Item'],
                    ['value' => 'code', 'label' => 'Kode'],
                    ['value' => 'name', 'label' => 'Nama item'],
                    ['value' => 'description', 'label' => 'Keterangan'],
                    ['value' => 'stock_in', 'label' => 'Tersedia'],
                    ['value' => 'stock_out', 'label' => 'Terpakai'],
                    ['value' => 'balance', 'label' => 'Stok'],
                    ['value' => 'id', 'label' => 'ID'],
                ],
                'direction' => [
                    ['value' => 'asc', 'label' => 'A–Z / terkecil'],
                    ['value' => 'desc', 'label' => 'Z–A / terbesar'],
                ],
            ],
        ]);
    }

    /**
     * Daftar kebutuhan komponen dari SPK (spkcomponents) untuk modal permintaan komponen.
     */
    public function componentRequests(
        Request $request,
        SpkComponentStockRequestService $componentRequests,
    ): JsonResponse {
        $tab = $request->string('tab')->trim()->toString();
        $search = $request->string('search')->trim()->toString();
        $page = max(1, $request->integer('page', 1));
        $perPage = $request->integer('per_page', 25);

        return response()->json($componentRequests->paginate(
            $tab !== '' ? $tab : SpkComponentStockRequestService::TAB_PENDING,
            $search,
            $page,
            $perPage,
        ));
    }

    /**
     * Store a newly created komponen master.
     */
    public function store(
        StoreMsComponentRequest $request,
        InventoryMasterPhotoStorage $photoStorage,
    ): RedirectResponse {
        $actor = $this->actorName($request);
        $attributes = $this->applyPhotoUpload(
            $request,
            $this->attributesFrom($request->validated()),
            $photoStorage,
        );

        $existing = MsComponent::query()
            ->whereIdentity(
                $attributes['component_category'],
                $attributes['item'],
                $attributes['item_code'],
            )
            ->first();

        if ($existing !== null) {
            $existing->update([
                ...$attributes,
                'is_deleted' => 0,
                'deleted_date' => null,
                'deleted_by' => null,
                'modified_date' => now(),
                'modified_by' => $actor,
            ]);
        } else {
            MsComponent::query()->create([
                ...$attributes,
                'is_deleted' => 0,
                'created_date' => now(),
                'created_by' => $actor,
                'modified_date' => now(),
                'modified_by' => $actor,
            ]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Komponen berhasil ditambahkan.',
        ]);

        return to_route('inventory.components.index');
    }

    /**
     * Display komponen master detail and trxcomponent unit history.
     */
    public function show(Request $request, MsComponent $msComponent, ComponentStockService $stock): Response
    {
        abort_if($msComponent->is_deleted === 1, 404);

        $perPage = $request->integer('per_page', 10);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;

        return Inertia::render('inventory/component/show', [
            'item' => [
                ...$this->toListItem($msComponent),
                ...$stock->countsForMsComponent($msComponent),
            ],
            'units' => $stock->tableAvailable()
                ? $stock->paginateUnits($msComponent, $perPage)
                : $this->emptyPaginator($perPage),
            'filters' => [
                'per_page' => $perPage,
            ],
            'colorOptions' => GoldColorOptions::all(),
        ]);
    }

    /**
     * Show the form for receiving komponen stock from a supplier.
     */
    public function createStock(MsComponent $msComponent): Response
    {
        abort_if($msComponent->is_deleted === 1, 404);

        return Inertia::render('inventory/component/stock/create', [
            'item' => $this->toListItem($msComponent),
            'colorOptions' => GoldColorOptions::all(),
        ]);
    }

    /**
     * Store a newly received komponen stock unit.
     */
    public function storeStock(
        StoreTrxComponentStockRequest $request,
        MsComponent $msComponent,
        ComponentStockService $stock,
    ): RedirectResponse {
        abort_if($msComponent->is_deleted === 1, 404);

        $validated = $request->validated();
        $pcs = (int) $validated['pcs'];
        unset($validated['pcs']);

        $units = $stock->receiveUnitsFromSupplier(
            $msComponent,
            $validated,
            $this->actorName($request),
            $pcs,
        );

        $count = count($units);
        $message = $count === 1
            ? 'Stok komponen berhasil ditambahkan.'
            : "{$count} unit stok komponen berhasil ditambahkan.";

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $message,
        ]);

        if ($request->input('return_to') === 'index') {
            return to_route('inventory.components.index');
        }

        return to_route('inventory.components.show', $msComponent);
    }

    /**
     * Manually issue an in-stock komponen unit.
     */
    public function issueStock(
        IssueTrxComponentStockRequest $request,
        TrxComponent $trxComponent,
        ComponentStockService $stock,
    ): RedirectResponse {
        abort_if($trxComponent->is_deleted === 1, 404);

        $validated = $request->validated();

        $spkKomponenLineId = filled($validated['spk_chain_line_id'] ?? null)
            ? (int) $validated['spk_chain_line_id']
            : null;

        $stock->issueManual(
            $trxComponent,
            (string) $validated['out_date'],
            (int) $validated['spk_id'],
            $validated['notes'] ?? null,
            $this->actorName($request),
            $spkKomponenLineId,
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Stok komponen berhasil dikurangi.',
        ]);

        if ($request->input('return_to') === 'spk') {
            $production = Production::query()
                ->notDeleted()
                ->where('row_id', (int) $validated['spk_id'])
                ->firstOrFail();

            return to_route('spk.show', $production);
        }

        return to_route('inventory.components.show', $trxComponent->mscomponent_id);
    }

    /**
     * Show the form for editing the specified komponen master.
     */
    public function edit(MsComponent $msComponent): Response
    {
        abort_if($msComponent->is_deleted === 1, 404);

        return Inertia::render('inventory/component/edit', [
            'item' => $this->toListItem($msComponent),
            'categories' => $this->categoryOptions(),
        ]);
    }

    /**
     * Update the specified komponen master.
     */
    public function update(
        UpdateMsComponentRequest $request,
        MsComponent $msComponent,
        InventoryMasterPhotoStorage $photoStorage,
    ): RedirectResponse {
        abort_if($msComponent->is_deleted === 1, 404);

        $msComponent->update([
            ...$this->applyPhotoUpload(
                $request,
                $this->attributesFrom($request->validated()),
                $photoStorage,
            ),
            'modified_date' => now(),
            'modified_by' => $this->actorName($request),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Komponen berhasil diperbarui.',
        ]);

        return to_route('inventory.components.index');
    }

    /**
     * Soft-delete the specified komponen master.
     */
    public function destroy(Request $request, MsComponent $msComponent): RedirectResponse
    {
        abort_if($msComponent->is_deleted === 1, 404);

        $actor = $this->actorName($request);

        $msComponent->update([
            'is_deleted' => 1,
            'deleted_date' => now(),
            'deleted_by' => $actor,
            'modified_date' => now(),
            'modified_by' => $actor,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Komponen berhasil dihapus.',
        ]);

        return to_route('inventory.components.index');
    }

    /**
     * @return list<string>
     */
    private function categoryOptions(): array
    {
        return [
            MsComponent::CATEGORY_KOMPONEN,
            MsComponent::CATEGORY_STOPPER,
        ];
    }

    /**
     * @param  array{component_category: string, item: string, item_code: string, item_name?: string|null, description?: string|null}  $validated
     * @return array{component_category: string, item: string, item_code: string, item_name: string, description: string|null}
     */
    private function attributesFrom(array $validated): array
    {
        $itemName = $validated['item_name'] ?? null;

        if (! is_string($itemName) || $itemName === '') {
            $itemName = MsComponent::composeItemName($validated['item'], $validated['item_code']);
        }

        return [
            'component_category' => $validated['component_category'],
            'item' => $validated['item'],
            'item_code' => $validated['item_code'],
            'item_name' => $itemName,
            'description' => $validated['description'] ?? null,
        ];
    }

    /**
     * @return array{
     *     id: int,
     *     componentCategory: string,
     *     item: string,
     *     itemCode: string,
     *     itemName: string|null,
     *     description: string|null,
     *     photo: string|null,
     *     createdBy: string|null,
     *     createdDate: string|null,
     *     modifiedBy: string|null,
     *     modifiedDate: string|null
     * }
     */
    private function toListItem(MsComponent $item): array
    {
        return [
            'id' => (int) $item->row_id,
            'componentCategory' => $item->component_category,
            'item' => $item->item,
            'itemCode' => $item->item_code,
            'itemName' => $item->item_name,
            'description' => $item->description,
            'photo' => $item->photo,
            'createdBy' => $item->created_by,
            'createdDate' => $item->created_date?->format('Y-m-d H:i:s'),
            'modifiedBy' => $item->modified_by,
            'modifiedDate' => $item->modified_date?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function applyPhotoUpload(
        StoreMsComponentRequest|UpdateMsComponentRequest $request,
        array $attributes,
        InventoryMasterPhotoStorage $photoStorage,
    ): array {
        $file = $request->file('photo');

        if ($file !== null) {
            $attributes['photo'] = $photoStorage->storeComponentPhoto($file);
        }

        return $attributes;
    }

    private function actorName(Request $request): string
    {
        return $request->user()?->name ?? 'system';
    }

    private function resolveIndexSort(string $sort): string
    {
        $allowed = [
            'category',
            'item',
            'code',
            'name',
            'description',
            'stock_in',
            'stock_out',
            'balance',
            'id',
        ];

        return in_array($sort, $allowed, true) ? $sort : self::INDEX_DEFAULT_SORT;
    }

    private function resolveIndexDirection(string $direction): string
    {
        return in_array($direction, ['asc', 'desc'], true)
            ? $direction
            : self::INDEX_DEFAULT_DIRECTION;
    }

    /**
     * @param  Builder<MsComponent>  $query
     */
    private function applyIndexSort(
        Builder $query,
        string $sort,
        string $direction,
        bool $stockTableAvailable,
    ): void {
        $order = $direction === 'asc' ? 'asc' : 'desc';

        if (in_array($sort, ['stock_in', 'stock_out', 'balance'], true) && ! $stockTableAvailable) {
            $sort = self::INDEX_DEFAULT_SORT;
        }

        match ($sort) {
            'category' => $query
                ->orderBy('component_category', $order)
                ->orderBy('item', 'asc')
                ->orderBy('item_code', 'asc'),
            'item' => $query
                ->orderBy('item', $order)
                ->orderBy('component_category', 'asc')
                ->orderBy('item_code', 'asc'),
            'code' => $query
                ->orderBy('item_code', $order)
                ->orderBy('component_category', 'asc')
                ->orderBy('item', 'asc'),
            'name' => $query
                ->orderBy('item_name', $order)
                ->orderBy('component_category', 'asc')
                ->orderBy('item', 'asc'),
            'description' => $query
                ->orderBy('description', $order)
                ->orderBy('component_category', 'asc')
                ->orderBy('item', 'asc'),
            'stock_in' => $query->orderBy(
                $this->stockCountSubquery('stock_in'),
                $order,
            )->orderBy('component_category', 'asc')->orderBy('item', 'asc'),
            'stock_out' => $query->orderBy(
                $this->stockCountSubquery('stock_out'),
                $order,
            )->orderBy('component_category', 'asc')->orderBy('item', 'asc'),
            'balance' => $query->orderBy(
                $this->stockCountSubquery('balance'),
                $order,
            )->orderBy('component_category', 'asc')->orderBy('item', 'asc'),
            'id' => $query->orderBy('row_id', $order),
            default => $query
                ->orderBy('component_category', 'asc')
                ->orderBy('item', 'asc')
                ->orderBy('item_code', 'asc'),
        };
    }

    /**
     * @return \Illuminate\Database\Query\Builder
     */
    private function stockCountSubquery(string $metric)
    {
        $expression = match ($metric) {
            'stock_in' => 'SUM(CASE WHEN entry_date IS NOT NULL THEN 1 ELSE 0 END)',
            'stock_out' => 'SUM(CASE WHEN out_date IS NOT NULL THEN 1 ELSE 0 END)',
            'balance' => 'SUM(CASE WHEN entry_date IS NOT NULL AND out_date IS NULL THEN 1 ELSE 0 END)',
            default => '0',
        };

        return TrxComponent::query()
            ->notDeleted()
            ->selectRaw($expression)
            ->whereColumn('trxcomponent.mscomponent_id', 'mscomponent.row_id');
    }

    /**
     * @return array{
     *     data: array<int, mixed>,
     *     current_page: int,
     *     last_page: int,
     *     per_page: int,
     *     total: int
     * }
     */
    private function emptyPaginator(int $perPage): array
    {
        return [
            'data' => [],
            'current_page' => 1,
            'last_page' => 1,
            'per_page' => $perPage,
            'total' => 0,
        ];
    }
}
