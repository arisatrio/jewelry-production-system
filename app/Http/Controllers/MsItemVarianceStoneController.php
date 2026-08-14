<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMsItemVarianceStoneRequest;
use App\Http\Requests\UpdateMsItemVarianceStoneRequest;
use App\Models\MsItemVariance;
use App\Models\MsItemVarianceStone;
use App\Models\MsPosition;
use App\Models\MsShape;
use App\Support\MsItemVarianceStoneCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MsItemVarianceStoneController extends Controller
{
    /**
     * Display the stones management page for a variance.
     */
    public function index(MsItemVariance $msItemVariance): Response
    {
        abort_if($msItemVariance->is_deleted === 1, 404);

        $msItemVariance->loadMissing('item');

        $stones = $msItemVariance->stones()
            ->notDeleted()
            ->with(['shape', 'position'])
            ->orderByDesc('row_id')
            ->get()
            ->map(fn (MsItemVarianceStone $stone): array => $this->toListItem($stone))
            ->values()
            ->all();

        return Inertia::render('master-data/varian-item/stones/index', [
            'variance' => $this->toVarianceDetail($msItemVariance),
            'stones' => $stones,
            'shapeOptions' => $this->shapeOptions(),
            'positionOptions' => $this->positionOptions(),
        ]);
    }

    /**
     * Store a newly created variance stone.
     */
    public function store(
        StoreMsItemVarianceStoneRequest $request,
        MsItemVariance $msItemVariance,
    ): RedirectResponse {
        abort_if($msItemVariance->is_deleted === 1, 404);

        $actor = $this->actorName($request);
        $validated = $request->validated();

        $pcs = $validated['pcs'] ?? null;
        $caratPerPcs = $validated['carat_per_pcs'] ?? null;

        $msItemVariance->stones()->create([
            'shape_id' => $validated['shape_id'] ?? null,
            'position_id' => $this->resolvePositionId($validated),
            'pcs' => $pcs,
            'carat_per_pcs' => $caratPerPcs,
            'total_carat' => MsItemVarianceStoneCalculator::totalCarat($pcs, $caratPerPcs),
            'size' => $validated['size'] ?? null,
            'is_deleted' => 0,
            'created_date' => now(),
            'created_by' => $actor,
            'modified_date' => now(),
            'modified_by' => $actor,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Batu varian item berhasil ditambahkan.',
        ]);

        return to_route('master-data.varian-item.stones.index', $msItemVariance);
    }

    /**
     * Update the specified variance stone.
     */
    public function update(
        UpdateMsItemVarianceStoneRequest $request,
        MsItemVariance $msItemVariance,
        MsItemVarianceStone $msItemVarianceStone,
    ): RedirectResponse {
        abort_if($msItemVariance->is_deleted === 1, 404);
        abort_if($msItemVarianceStone->is_deleted === 1, 404);
        abort_if($msItemVarianceStone->item_variance_id !== $msItemVariance->row_id, 404);

        $validated = $request->validated();
        $pcs = $validated['pcs'] ?? null;
        $caratPerPcs = $validated['carat_per_pcs'] ?? null;

        $msItemVarianceStone->update([
            'shape_id' => $validated['shape_id'] ?? null,
            'position_id' => $this->resolvePositionId($validated),
            'pcs' => $pcs,
            'carat_per_pcs' => $caratPerPcs,
            'total_carat' => MsItemVarianceStoneCalculator::totalCarat($pcs, $caratPerPcs),
            'size' => $validated['size'] ?? null,
            'modified_date' => now(),
            'modified_by' => $this->actorName($request),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Batu varian item berhasil diperbarui.',
        ]);

        return to_route('master-data.varian-item.stones.index', $msItemVariance);
    }

    /**
     * Soft-delete the specified variance stone.
     */
    public function destroy(
        Request $request,
        MsItemVariance $msItemVariance,
        MsItemVarianceStone $msItemVarianceStone,
    ): RedirectResponse {
        abort_if($msItemVariance->is_deleted === 1, 404);
        abort_if($msItemVarianceStone->is_deleted === 1, 404);
        abort_if($msItemVarianceStone->item_variance_id !== $msItemVariance->row_id, 404);

        $actor = $this->actorName($request);

        $msItemVarianceStone->update([
            'is_deleted' => 1,
            'deleted_date' => now(),
            'deleted_by' => $actor,
            'modified_date' => now(),
            'modified_by' => $actor,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Batu varian item berhasil dihapus.',
        ]);

        return to_route('master-data.varian-item.stones.index', $msItemVariance);
    }

    /**
     * @param  array{position_id?: int|null, position_nama?: string|null}  $validated
     */
    private function resolvePositionId(array $validated): ?int
    {
        return MsPosition::resolveId(
            isset($validated['position_id']) ? (int) $validated['position_id'] : null,
            $validated['position_nama'] ?? null,
        );
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    private function shapeOptions(): array
    {
        return MsShape::query()
            ->notDeleted()
            ->orderBy('name')
            ->get(['row_id', 'name', 'code'])
            ->map(fn (MsShape $shape): array => [
                'id' => (int) $shape->row_id,
                'name' => $this->shapeLabel($shape),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    private function positionOptions(): array
    {
        return MsPosition::query()
            ->orderBy('nama')
            ->get(['id', 'nama'])
            ->map(fn (MsPosition $position): array => [
                'id' => (int) $position->id,
                'name' => $position->nama,
            ])
            ->values()
            ->all();
    }

    private function shapeLabel(MsShape $shape): string
    {
        $parts = array_filter([
            $shape->name,
            $shape->code,
        ], fn ($value): bool => filled($value));

        return $parts !== []
            ? implode(' - ', $parts)
            : 'Shape #'.$shape->row_id;
    }

    /**
     * @return array{
     *     id: int,
     *     name: string|null,
     *     itemName: string|null,
     *     diameter: string|null,
     *     dimensi: string|null,
     *     ringSize: string|null,
     *     goldWeight: string|null,
     *     goldColor: string|null,
     *     jwcad3d: string|null
     * }
     */
    private function toVarianceDetail(MsItemVariance $variance): array
    {
        return [
            'id' => (int) $variance->row_id,
            'name' => $variance->name,
            'itemName' => $variance->item?->name,
            'diameter' => $variance->diameter,
            'dimensi' => $variance->dimensi,
            'ringSize' => $variance->ring_size,
            'goldWeight' => $variance->gold_weight !== null
                ? (string) $variance->gold_weight
                : null,
            'goldColor' => $variance->gold_color,
            'jwcad3d' => $variance->jwcad_3d,
        ];
    }

    /**
     * @return array{
     *     id: int,
     *     itemVarianceId: int,
     *     positionId: int|null,
     *     positionName: string|null,
     *     shapeId: int|null,
     *     shapeName: string|null,
     *     pcs: int|null,
     *     caratPerPcs: string|null,
     *     totalCarat: string|null,
     *     size: string|null
     * }
     */
    private function toListItem(MsItemVarianceStone $stone): array
    {
        return [
            'id' => (int) $stone->row_id,
            'itemVarianceId' => (int) $stone->item_variance_id,
            'positionId' => $stone->position_id !== null ? (int) $stone->position_id : null,
            'positionName' => $stone->position?->nama,
            'shapeId' => $stone->shape_id !== null ? (int) $stone->shape_id : null,
            'shapeName' => $stone->shape !== null ? $this->shapeLabel($stone->shape) : null,
            'pcs' => $stone->pcs,
            'caratPerPcs' => $stone->carat_per_pcs !== null ? (string) $stone->carat_per_pcs : null,
            'totalCarat' => $stone->total_carat !== null ? (string) $stone->total_carat : null,
            'size' => $stone->size !== null ? (string) $stone->size : null,
        ];
    }

    private function actorName(Request $request): string
    {
        return $request->user()?->name ?? 'system';
    }
}
