<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMsItemRequest;
use App\Http\Requests\UpdateMsItemRequest;
use App\Models\MsItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MsItemController extends Controller
{
    /**
     * Display a listing of tipe items.
     */
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();
        $perPage = $request->integer('per_page', 10);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;

        $items = MsItem::query()
            ->notDeleted()
            ->when($search !== '', function ($query) use ($search): void {
                $query->where('name', 'like', "%{$search}%");
            })
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (MsItem $item): array => $this->toListItem($item));

        return Inertia::render('master-data/tipe-item/index', [
            'items' => $items,
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
            ],
        ]);
    }

    /**
     * Show the form for creating a new tipe item.
     */
    public function create(): Response
    {
        return Inertia::render('master-data/tipe-item/create');
    }

    /**
     * Store a newly created tipe item.
     */
    public function store(StoreMsItemRequest $request): RedirectResponse
    {
        $actor = $this->actorName($request);

        MsItem::query()->create([
            'name' => $request->validated('name'),
            'is_deleted' => 0,
            'created_date' => now(),
            'created_by' => $actor,
            'modified_date' => now(),
            'modified_by' => $actor,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Tipe item berhasil ditambahkan.',
        ]);

        return to_route('master-data.tipe-item.index');
    }

    /**
     * Show the form for editing the specified tipe item.
     */
    public function edit(MsItem $msItem): Response
    {
        abort_if($msItem->is_deleted === 1, 404);

        return Inertia::render('master-data/tipe-item/edit', [
            'item' => $this->toListItem($msItem),
        ]);
    }

    /**
     * Update the specified tipe item.
     */
    public function update(UpdateMsItemRequest $request, MsItem $msItem): RedirectResponse
    {
        abort_if($msItem->is_deleted === 1, 404);

        $msItem->update([
            'name' => $request->validated('name'),
            'modified_date' => now(),
            'modified_by' => $this->actorName($request),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Tipe item berhasil diperbarui.',
        ]);

        return to_route('master-data.tipe-item.index');
    }

    /**
     * Soft-delete the specified tipe item.
     */
    public function destroy(Request $request, MsItem $msItem): RedirectResponse
    {
        abort_if($msItem->is_deleted === 1, 404);

        $msItem->update([
            'is_deleted' => 1,
            'deleted_date' => now(),
            'deleted_by' => $this->actorName($request),
            'modified_date' => now(),
            'modified_by' => $this->actorName($request),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Tipe item berhasil dihapus.',
        ]);

        return to_route('master-data.tipe-item.index');
    }

    /**
     * @return array{id: int, name: string|null, createdBy: string|null, createdDate: string|null, modifiedBy: string|null, modifiedDate: string|null}
     */
    private function toListItem(MsItem $item): array
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
