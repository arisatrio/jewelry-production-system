<?php

namespace App\Http\Controllers;

use App\Http\Requests\IssueTrxChainStockRequest;
use App\Http\Requests\StoreMsChainRequest;
use App\Http\Requests\StoreTrxChainStockRequest;
use App\Http\Requests\UpdateMsChainRequest;
use App\Models\MsChain;
use App\Models\Production;
use App\Models\TrxChain;
use App\Support\ChainStockService;
use App\Support\GoldColorOptions;
use App\Support\SpkChainStockRequestService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MsChainController extends Controller
{
    private const INDEX_DEFAULT_SORT = 'category';

    private const INDEX_DEFAULT_DIRECTION = 'asc';

    /**
     * Display a listing of chain masters.
     */
    public function index(
        Request $request,
        ChainStockService $stock,
        SpkChainStockRequestService $chainRequests,
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

        $items = MsChain::query()
            ->notDeleted()
            ->when($category !== '', function ($query) use ($category): void {
                $query->where('chain_category', $category);
            })
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('chain_category', 'like', "%{$search}%")
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
            ? $stock->countsByMsChain(
                $items->getCollection()->map(fn (MsChain $item): int => (int) $item->row_id)->values()->all(),
            )
            : [];

        $items->through(function (MsChain $item) use ($stockCounts): array {
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

        return Inertia::render('inventory/chain/index', [
            'items' => $items,
            'categories' => $this->categoryOptions(),
            'colorOptions' => GoldColorOptions::all(),
            'summary' => [
                'totalAvailable' => $totalAvailable,
            ],
            'chainRequestCount' => $chainRequests->tableAvailable()
                ? $chainRequests->pendingCount()
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
                    ['value' => 'length', 'label' => 'Panjang'],
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
     * Daftar kebutuhan rantai dari SPK (spkchains) untuk modal permintaan rantai.
     */
    public function chainRequests(
        Request $request,
        SpkChainStockRequestService $chainRequests,
    ): JsonResponse {
        $tab = $request->string('tab')->trim()->toString();
        $search = $request->string('search')->trim()->toString();
        $page = max(1, $request->integer('page', 1));
        $perPage = $request->integer('per_page', 25);

        return response()->json($chainRequests->paginate(
            $tab !== '' ? $tab : SpkChainStockRequestService::TAB_PENDING,
            $search,
            $page,
            $perPage,
        ));
    }

    /**
     * Store a newly created chain master.
     */
    public function store(StoreMsChainRequest $request): RedirectResponse
    {
        $actor = $this->actorName($request);
        $attributes = $this->attributesFrom($request->validated());

        $existing = MsChain::query()
            ->whereIdentity(
                $attributes['chain_category'],
                $attributes['item'],
                $attributes['item_code'],
                $attributes['length'],
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
            MsChain::query()->create([
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
            'message' => 'Chain berhasil ditambahkan.',
        ]);

        return to_route('inventory.chains.index');
    }

    /**
     * Display chain master detail and trxchain unit history.
     */
    public function show(Request $request, MsChain $msChain, ChainStockService $stock): Response
    {
        abort_if($msChain->is_deleted === 1, 404);

        $perPage = $request->integer('per_page', 10);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;

        return Inertia::render('inventory/chain/show', [
            'item' => [
                ...$this->toListItem($msChain),
                ...$stock->countsForMsChain($msChain),
            ],
            'units' => $stock->tableAvailable()
                ? $stock->paginateUnits($msChain, $perPage)
                : $this->emptyPaginator($perPage),
            'filters' => [
                'per_page' => $perPage,
            ],
            'colorOptions' => GoldColorOptions::all(),
        ]);
    }

    /**
     * Show the form for receiving chain stock from a supplier.
     */
    public function createStock(MsChain $msChain): Response
    {
        abort_if($msChain->is_deleted === 1, 404);

        return Inertia::render('inventory/chain/stock/create', [
            'item' => $this->toListItem($msChain),
            'colorOptions' => GoldColorOptions::all(),
        ]);
    }

    /**
     * Store a newly received chain stock unit.
     */
    public function storeStock(
        StoreTrxChainStockRequest $request,
        MsChain $msChain,
        ChainStockService $stock,
    ): RedirectResponse {
        abort_if($msChain->is_deleted === 1, 404);

        $stock->receiveFromSupplier(
            $msChain,
            $request->validated(),
            $this->actorName($request),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Stok chain berhasil ditambahkan.',
        ]);

        if ($request->input('return_to') === 'index') {
            return to_route('inventory.chains.index');
        }

        return to_route('inventory.chains.show', $msChain);
    }

    /**
     * Manually issue an in-stock chain unit.
     */
    public function issueStock(
        IssueTrxChainStockRequest $request,
        TrxChain $trxChain,
        ChainStockService $stock,
    ): RedirectResponse {
        abort_if($trxChain->is_deleted === 1, 404);

        $validated = $request->validated();

        $stock->issueManual(
            $trxChain,
            (string) $validated['out_date'],
            (int) $validated['spk_id'],
            $validated['notes'] ?? null,
            $this->actorName($request),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Stok chain berhasil dikurangi.',
        ]);

        if ($request->input('return_to') === 'spk') {
            $production = Production::query()
                ->notDeleted()
                ->where('row_id', (int) $validated['spk_id'])
                ->firstOrFail();

            return to_route('spk.show', $production);
        }

        return to_route('inventory.chains.show', $trxChain->mschain_id);
    }

    /**
     * Show the form for editing the specified chain master.
     */
    public function edit(MsChain $msChain): Response
    {
        abort_if($msChain->is_deleted === 1, 404);

        return Inertia::render('inventory/chain/edit', [
            'item' => $this->toListItem($msChain),
            'categories' => $this->categoryOptions(),
        ]);
    }

    /**
     * Update the specified chain master.
     */
    public function update(UpdateMsChainRequest $request, MsChain $msChain): RedirectResponse
    {
        abort_if($msChain->is_deleted === 1, 404);

        $msChain->update([
            ...$this->attributesFrom($request->validated()),
            'modified_date' => now(),
            'modified_by' => $this->actorName($request),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Chain berhasil diperbarui.',
        ]);

        return to_route('inventory.chains.index');
    }

    /**
     * Soft-delete the specified chain master.
     */
    public function destroy(Request $request, MsChain $msChain): RedirectResponse
    {
        abort_if($msChain->is_deleted === 1, 404);

        $actor = $this->actorName($request);

        $msChain->update([
            'is_deleted' => 1,
            'deleted_date' => now(),
            'deleted_by' => $actor,
            'modified_date' => now(),
            'modified_by' => $actor,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Chain berhasil dihapus.',
        ]);

        return to_route('inventory.chains.index');
    }

    /**
     * @return list<string>
     */
    private function categoryOptions(): array
    {
        return [
            MsChain::CATEGORY_BRACELET,
            MsChain::CATEGORY_PENDANT,
        ];
    }

    /**
     * @param  array{chain_category: string, item: string, item_code: string, length: string|int|float, item_name?: string|null, description?: string|null}  $validated
     * @return array{chain_category: string, item: string, item_code: string, length: string, item_name: string, description: string|null}
     */
    private function attributesFrom(array $validated): array
    {
        $length = number_format((float) $validated['length'], 2, '.', '');
        $itemName = $validated['item_name'] ?? null;

        if (! is_string($itemName) || $itemName === '') {
            $itemName = MsChain::composeItemName($validated['item'], $validated['item_code'], $length);
        }

        return [
            'chain_category' => $validated['chain_category'],
            'item' => $validated['item'],
            'item_code' => $validated['item_code'],
            'length' => $length,
            'item_name' => $itemName,
            'description' => $validated['description'] ?? null,
        ];
    }

    /**
     * @return array{
     *     id: int,
     *     chainCategory: string,
     *     item: string,
     *     itemCode: string,
     *     length: string,
     *     itemName: string|null,
     *     description: string|null,
     *     createdBy: string|null,
     *     createdDate: string|null,
     *     modifiedBy: string|null,
     *     modifiedDate: string|null
     * }
     */
    private function toListItem(MsChain $item): array
    {
        return [
            'id' => (int) $item->row_id,
            'chainCategory' => $item->chain_category,
            'item' => $item->item,
            'itemCode' => $item->item_code,
            'length' => (string) $item->length,
            'itemName' => $item->item_name,
            'description' => $item->description,
            'createdBy' => $item->created_by,
            'createdDate' => $item->created_date?->format('Y-m-d H:i:s'),
            'modifiedBy' => $item->modified_by,
            'modifiedDate' => $item->modified_date?->format('Y-m-d H:i:s'),
        ];
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
            'length',
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
     * @param  Builder<MsChain>  $query
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
                ->orderBy('chain_category', $order)
                ->orderBy('item', 'asc')
                ->orderBy('length', 'asc')
                ->orderBy('item_code', 'asc'),
            'item' => $query
                ->orderBy('item', $order)
                ->orderBy('chain_category', 'asc')
                ->orderBy('length', 'asc')
                ->orderBy('item_code', 'asc'),
            'code' => $query
                ->orderBy('item_code', $order)
                ->orderBy('chain_category', 'asc')
                ->orderBy('item', 'asc'),
            'length' => $query
                ->orderBy('length', $order)
                ->orderBy('chain_category', 'asc')
                ->orderBy('item', 'asc'),
            'name' => $query
                ->orderBy('item_name', $order)
                ->orderBy('chain_category', 'asc')
                ->orderBy('item', 'asc'),
            'description' => $query
                ->orderBy('description', $order)
                ->orderBy('chain_category', 'asc')
                ->orderBy('item', 'asc'),
            'stock_in' => $query->orderBy(
                $this->stockCountSubquery('stock_in'),
                $order,
            )->orderBy('chain_category', 'asc')->orderBy('item', 'asc'),
            'stock_out' => $query->orderBy(
                $this->stockCountSubquery('stock_out'),
                $order,
            )->orderBy('chain_category', 'asc')->orderBy('item', 'asc'),
            'balance' => $query->orderBy(
                $this->stockCountSubquery('balance'),
                $order,
            )->orderBy('chain_category', 'asc')->orderBy('item', 'asc'),
            'id' => $query->orderBy('row_id', $order),
            default => $query
                ->orderBy('chain_category', 'asc')
                ->orderBy('item', 'asc')
                ->orderBy('length', 'asc')
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

        return TrxChain::query()
            ->notDeleted()
            ->selectRaw($expression)
            ->whereColumn('trxchain.mschain_id', 'mschain.row_id');
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
