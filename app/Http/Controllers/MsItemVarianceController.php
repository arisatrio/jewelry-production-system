<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMsItemVarianceRequest;
use App\Http\Requests\UpdateMsItemVarianceRequest;
use App\Models\MsItem;
use App\Models\MsItemVariance;
use App\Models\MsItemVarianceStone;
use App\Support\GoldColorOptions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class MsItemVarianceController extends Controller
{
    /**
     * Display a listing of item variances.
     */
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();
        $itemId = $request->integer('item_id') ?: null;
        $perPage = $request->integer('per_page', 10);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;

        $variances = MsItemVariance::query()
            ->notDeleted()
            ->with([
                'item',
                'stones' => function ($query): void {
                    $query->notDeleted()
                        ->with('shape')
                        ->orderByDesc('row_id');
                },
            ])
            ->when($itemId !== null, function ($query) use ($itemId): void {
                $query->where('item_id', $itemId);
            })
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhereHas('item', function ($query) use ($search): void {
                            $query->where('name', 'like', "%{$search}%");
                        });
                });
            })
            ->orderByDesc('row_id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (MsItemVariance $variance): array => $this->toListItem($variance));

        return Inertia::render('master-data/varian-item/index', [
            'variances' => $variances,
            'itemOptions' => $this->itemOptions(),
            'goldColorOptions' => GoldColorOptions::all(),
            'filters' => [
                'search' => $search,
                'item_id' => $itemId,
                'per_page' => $perPage,
                'create' => $request->boolean('create'),
                'edit' => $request->integer('edit') ?: null,
            ],
        ]);
    }

    /**
     * Redirect create page to index (modal-based UI).
     */
    public function create(): RedirectResponse
    {
        return to_route('master-data.varian-item.index', ['create' => 1]);
    }

    /**
     * Store a newly created item variance.
     */
    public function store(StoreMsItemVarianceRequest $request): RedirectResponse
    {
        $actor = $this->actorName($request);
        $validated = $request->validated();

        $variance = MsItemVariance::query()->create([
            'item_id' => $validated['item_id'],
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'diameter' => $validated['diameter'] ?? null,
            'dimensi' => $validated['dimensi'] ?? null,
            'ring_size' => $validated['ring_size'] ?? null,
            'diameter_length_ringsize' => $this->combinedSizeLabel(
                $validated['diameter'] ?? null,
                $validated['dimensi'] ?? null,
                $validated['ring_size'] ?? null,
            ),
            'gold_weight' => $validated['gold_weight'] ?? null,
            'gold_color' => $validated['gold_color'] ?? null,
            'jwcad_3d' => $validated['jwcad_3d'] ?? null,
            'is_deleted' => 0,
            'created_date' => now(),
            'created_by' => $actor,
            'modified_date' => now(),
            'modified_by' => $actor,
        ]);

        if ($request->hasFile('image')) {
            $variance->update([
                'image' => $this->storeImage($request->file('image'), $variance),
            ]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Varian item berhasil ditambahkan.',
        ]);

        return to_route('master-data.varian-item.index');
    }

    /**
     * Redirect edit page to index (modal-based UI).
     */
    public function edit(MsItemVariance $msItemVariance): RedirectResponse
    {
        abort_if($msItemVariance->is_deleted === 1, 404);

        return to_route('master-data.varian-item.index', [
            'edit' => $msItemVariance->row_id,
        ]);
    }

    /**
     * Return stones for the List Batu modal.
     */
    public function batu(MsItemVariance $msItemVariance): JsonResponse
    {
        abort_if($msItemVariance->is_deleted === 1, 404);

        $msItemVariance->loadMissing('item');

        $stones = $msItemVariance->stones()
            ->notDeleted()
            ->with('shape')
            ->orderByDesc('row_id')
            ->get()
            ->map(fn (MsItemVarianceStone $stone): array => $this->toStoneListItem($stone))
            ->values()
            ->all();

        return response()->json([
            'status' => true,
            'variance' => [
                'id' => (int) $msItemVariance->row_id,
                'name' => $msItemVariance->name,
                'itemName' => $msItemVariance->item?->name,
                'diameter' => $msItemVariance->diameter,
                'dimensi' => $msItemVariance->dimensi,
                'ringSize' => $msItemVariance->ring_size,
                'goldWeight' => $msItemVariance->gold_weight !== null
                    ? (string) $msItemVariance->gold_weight
                    : null,
                'goldColor' => $msItemVariance->gold_color,
                'jwcad3d' => $msItemVariance->jwcad_3d,
            ],
            'stones' => $stones,
        ]);
    }

    /**
     * Update the specified item variance.
     */
    public function update(
        UpdateMsItemVarianceRequest $request,
        MsItemVariance $msItemVariance,
    ): RedirectResponse {
        abort_if($msItemVariance->is_deleted === 1, 404);

        $validated = $request->validated();

        $attributes = [
            'item_id' => $validated['item_id'],
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'diameter' => $validated['diameter'] ?? null,
            'dimensi' => $validated['dimensi'] ?? null,
            'ring_size' => $validated['ring_size'] ?? null,
            'diameter_length_ringsize' => $this->combinedSizeLabel(
                $validated['diameter'] ?? null,
                $validated['dimensi'] ?? null,
                $validated['ring_size'] ?? null,
            ),
            'gold_weight' => $validated['gold_weight'] ?? null,
            'gold_color' => $validated['gold_color'] ?? null,
            'jwcad_3d' => $validated['jwcad_3d'] ?? null,
            'modified_date' => now(),
            'modified_by' => $this->actorName($request),
        ];

        if ($request->hasFile('image')) {
            $this->deleteImage($msItemVariance->image);
            $attributes['image'] = $this->storeImage($request->file('image'), $msItemVariance);
        }

        $msItemVariance->update($attributes);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Varian item berhasil diperbarui.',
        ]);

        return to_route('master-data.varian-item.index');
    }

    /**
     * Soft-delete the specified item variance.
     */
    public function destroy(Request $request, MsItemVariance $msItemVariance): RedirectResponse
    {
        abort_if($msItemVariance->is_deleted === 1, 404);

        $actor = $this->actorName($request);

        $msItemVariance->update([
            'is_deleted' => 1,
            'deleted_date' => now(),
            'deleted_by' => $actor,
            'modified_date' => now(),
            'modified_by' => $actor,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Varian item berhasil dihapus.',
        ]);

        return to_route('master-data.varian-item.index');
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    private function itemOptions(): array
    {
        return MsItem::query()
            ->notDeleted()
            ->orderBy('name')
            ->get(['row_id', 'name'])
            ->map(fn (MsItem $item): array => [
                'id' => (int) $item->row_id,
                'name' => (string) ($item->name ?? 'Tanpa nama'),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{
     *     id: int,
     *     shapeId: int|null,
     *     shapeName: string|null,
     *     pcs: int|null,
     *     caratPerPcs: string|null,
     *     totalCarat: string|null,
     *     size: string|null
     * }
     */
    private function toStoneListItem(MsItemVarianceStone $stone): array
    {
        $shape = $stone->shape;
        $shapeName = null;

        if ($shape !== null) {
            $parts = array_filter([
                $shape->name,
                $shape->code,
            ], fn ($value): bool => filled($value));

            $shapeName = $parts !== []
                ? implode(' - ', $parts)
                : 'Shape #'.$shape->row_id;
        }

        return [
            'id' => (int) $stone->row_id,
            'shapeId' => $stone->shape_id !== null ? (int) $stone->shape_id : null,
            'shapeName' => $shapeName,
            'pcs' => $stone->pcs,
            'caratPerPcs' => $stone->carat_per_pcs !== null ? (string) $stone->carat_per_pcs : null,
            'totalCarat' => $stone->total_carat !== null ? (string) $stone->total_carat : null,
            'size' => $stone->size !== null ? (string) $stone->size : null,
        ];
    }

    /**
     * @return array{
     *     id: int,
     *     itemId: int,
     *     itemName: string|null,
     *     name: string|null,
     *     description: string|null,
     *     diameter: string|null,
     *     dimensi: string|null,
     *     ringSize: string|null,
     *     goldWeight: string|null,
     *     goldColor: string|null,
     *     jwcad3d: string|null,
     *     image: string|null,
     *     imageUrl: string|null,
     *     stones: list<array{
     *         id: int,
     *         shapeId: int|null,
     *         shapeName: string|null,
     *         pcs: int|null,
     *         caratPerPcs: string|null,
     *         totalCarat: string|null,
     *         size: string|null
     *     }>,
     *     createdBy: string|null,
     *     createdDate: string|null,
     *     modifiedBy: string|null,
     *     modifiedDate: string|null
     * }
     */
    private function toListItem(MsItemVariance $variance): array
    {
        return [
            'id' => (int) $variance->row_id,
            'itemId' => (int) $variance->item_id,
            'itemName' => $variance->item?->name,
            'name' => $variance->name,
            'description' => $variance->description,
            'diameter' => $variance->diameter,
            'dimensi' => $variance->dimensi,
            'ringSize' => $variance->ring_size,
            'goldWeight' => $variance->gold_weight !== null
                ? (string) $variance->gold_weight
                : null,
            'goldColor' => $variance->gold_color,
            'jwcad3d' => $variance->jwcad_3d,
            'image' => $variance->image,
            'imageUrl' => $this->imageUrl($variance->image),
            'stones' => $variance->stones
                ->map(fn (MsItemVarianceStone $stone): array => $this->toStoneListItem($stone))
                ->values()
                ->all(),
            'createdBy' => $variance->created_by,
            'createdDate' => $variance->created_date?->format('Y-m-d H:i:s'),
            'modifiedBy' => $variance->modified_by,
            'modifiedDate' => $variance->modified_date?->format('Y-m-d H:i:s'),
        ];
    }

    private function storeImage(UploadedFile $file, MsItemVariance $variance): string
    {
        return $file->store('varian-item/'.$variance->row_id, 'public');
    }

    private function deleteImage(?string $path): void
    {
        if (filled($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    private function imageUrl(?string $path): ?string
    {
        if (! filled($path)) {
            return null;
        }

        return '/storage/'.ltrim(str_replace('\\', '/', $path), '/');
    }

    private function combinedSizeLabel(
        ?string $diameter,
        ?string $dimensi,
        ?string $ringSize,
    ): ?string {
        $parts = [
            filled($diameter) ? trim((string) $diameter) : '',
            filled($dimensi) ? trim((string) $dimensi) : '',
            filled($ringSize) ? trim((string) $ringSize) : '',
        ];

        if ($parts[0] === '' && $parts[1] === '' && $parts[2] === '') {
            return null;
        }

        return implode(' / ', $parts);
    }

    private function actorName(Request $request): string
    {
        return $request->user()?->name ?? 'system';
    }
}
