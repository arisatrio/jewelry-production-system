<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMaterialGoldRequest;
use App\Http\Requests\UpdateMaterialGoldRequest;
use App\Models\MaterialGold;
use App\Support\GoldMaterialLedger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MaterialGoldController extends Controller
{
    /**
     * Display a listing of bahan emas.
     */
    public function index(Request $request, GoldMaterialLedger $ledger): Response
    {
        $search = $request->string('search')->trim()->toString();
        $perPage = $request->integer('per_page', 10);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;
        $activePeriod = $ledger->activePeriod();

        $items = MaterialGold::query()
            ->notDeleted()
            ->when($search !== '', function ($query) use ($search): void {
                $query->where('name', 'like', "%{$search}%");
            })
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();

        $stocks = $ledger->stockByMaterial(
            $activePeriod['id'] ?? null,
            $items->getCollection()->map(fn (MaterialGold $item): int => (int) $item->row_id)->values()->all(),
        );

        $items->through(fn (MaterialGold $item): array => [
            ...$this->toListItem($item),
            'stock' => $stocks[(int) $item->row_id] ?? '0.00',
        ]);

        return Inertia::render('inventory/bahan-emas/index', [
            'items' => $items,
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
            ],
        ]);
    }

    /**
     * Show the form for creating a new bahan emas.
     */
    public function create(): Response
    {
        return Inertia::render('inventory/bahan-emas/create');
    }

    /**
     * Store a newly created bahan emas.
     */
    public function store(StoreMaterialGoldRequest $request): RedirectResponse
    {
        $actor = $this->actorName($request);

        MaterialGold::query()->create([
            'name' => $request->validated('name'),
            'is_deleted' => 0,
            'created_date' => now(),
            'created_by' => $actor,
            'modified_date' => now(),
            'modified_by' => $actor,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Bahan emas berhasil ditambahkan.',
        ]);

        return to_route('inventory.gold-materials.index');
    }

    /**
     * Display the specified bahan emas with its transactions in the active period.
     */
    public function show(Request $request, MaterialGold $materialGold, GoldMaterialLedger $ledger): Response
    {
        abort_if($materialGold->is_deleted === 1, 404);

        $materialId = (int) $materialGold->row_id;
        $activePeriod = $ledger->activePeriod();
        $perPage = $request->integer('per_page', 10);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;

        return Inertia::render('inventory/bahan-emas/show', [
            'item' => [
                ...$this->toListItem($materialGold),
                'stock' => $ledger->stockByMaterial($activePeriod['id'] ?? null, [$materialId])[$materialId] ?? '0.00',
            ],
            'activePeriod' => $activePeriod,
            'transactions' => $activePeriod === null
                ? [
                    'data' => [],
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => $perPage,
                    'total' => 0,
                ]
                : $ledger->paginate($activePeriod['id'], '', null, null, $perPage, $materialId),
            'filters' => [
                'per_page' => $perPage,
            ],
        ]);
    }

    /**
     * Show the form for editing the specified bahan emas.
     */
    public function edit(MaterialGold $materialGold): Response
    {
        abort_if($materialGold->is_deleted === 1, 404);

        return Inertia::render('inventory/bahan-emas/edit', [
            'item' => $this->toListItem($materialGold),
        ]);
    }

    /**
     * Update the specified bahan emas.
     */
    public function update(UpdateMaterialGoldRequest $request, MaterialGold $materialGold): RedirectResponse
    {
        abort_if($materialGold->is_deleted === 1, 404);

        $materialGold->update([
            'name' => $request->validated('name'),
            'modified_date' => now(),
            'modified_by' => $this->actorName($request),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Bahan emas berhasil diperbarui.',
        ]);

        return to_route('inventory.gold-materials.index');
    }

    /**
     * Soft-delete the specified bahan emas.
     */
    public function destroy(Request $request, MaterialGold $materialGold): RedirectResponse
    {
        abort_if($materialGold->is_deleted === 1, 404);

        $materialGold->update([
            'is_deleted' => 1,
            'deleted_date' => now(),
            'deleted_by' => $this->actorName($request),
            'modified_date' => now(),
            'modified_by' => $this->actorName($request),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Bahan emas berhasil dihapus.',
        ]);

        return to_route('inventory.gold-materials.index');
    }

    /**
     * @return array{id: int, name: string|null, createdBy: string|null, createdDate: string|null, modifiedBy: string|null, modifiedDate: string|null}
     */
    private function toListItem(MaterialGold $item): array
    {
        return [
            'id' => (int) $item->row_id,
            'name' => $item->name,
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
}
