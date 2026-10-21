<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTrKomponenRequest;
use App\Http\Requests\UpdateTrKomponenRequest;
use App\Models\TrKomponen;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TrKomponenController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();
        $perPage = $request->integer('per_page', 10);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;

        $items = TrKomponen::query()
            ->notDeleted()
            ->with('production:row_id,spk_no')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('jenis_komponen', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('item_code', 'like', "%{$search}%")
                        ->orWhere('supplier', 'like', "%{$search}%")
                        ->orWhere('color', 'like', "%{$search}%")
                        ->orWhereHas('production', fn ($productionQuery) => $productionQuery
                            ->where('spk_no', 'like', "%{$search}%"));
                });
            })
            ->orderByDesc('row_id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (TrKomponen $item): array => $this->toListItem($item));

        return Inertia::render('inventory/transaksi-komponen/index', [
            'items' => $items,
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('inventory/transaksi-komponen/create');
    }

    public function store(StoreTrKomponenRequest $request): RedirectResponse
    {
        $actor = $this->actorName($request);

        TrKomponen::query()->create([
            ...$this->payloadFromValidated($request->validated()),
            'is_deleted' => 0,
            'created_date' => now(),
            'created_by' => $actor,
            'modified_date' => now(),
            'modified_by' => $actor,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Transaksi komponen berhasil ditambahkan.',
        ]);

        return to_route('inventory.component-transactions.index');
    }

    public function show(TrKomponen $trKomponen): Response
    {
        abort_if($trKomponen->is_deleted === 1, 404);

        return Inertia::render('inventory/transaksi-komponen/show', [
            'item' => $this->toDetailItem($trKomponen),
        ]);
    }

    public function edit(TrKomponen $trKomponen): Response
    {
        abort_if($trKomponen->is_deleted === 1, 404);

        return Inertia::render('inventory/transaksi-komponen/edit', [
            'item' => $this->toFormItem($trKomponen),
        ]);
    }

    public function update(UpdateTrKomponenRequest $request, TrKomponen $trKomponen): RedirectResponse
    {
        abort_if($trKomponen->is_deleted === 1, 404);

        $trKomponen->update([
            ...$this->payloadFromValidated($request->validated()),
            'modified_date' => now(),
            'modified_by' => $this->actorName($request),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Transaksi komponen berhasil diperbarui.',
        ]);

        return to_route('inventory.component-transactions.index');
    }

    public function destroy(Request $request, TrKomponen $trKomponen): RedirectResponse
    {
        abort_if($trKomponen->is_deleted === 1, 404);

        $actor = $this->actorName($request);

        $trKomponen->update([
            'is_deleted' => 1,
            'deleted_date' => now(),
            'deleted_by' => $actor,
            'modified_date' => now(),
            'modified_by' => $actor,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Transaksi komponen berhasil dihapus.',
        ]);

        return to_route('inventory.component-transactions.index');
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function payloadFromValidated(array $validated): array
    {
        return [
            'entry_date' => $validated['entry_date'] ?? null,
            'out_date' => $validated['out_date'] ?? null,
            'jenis_komponen' => $validated['jenis_komponen'] ?? null,
            'name' => $validated['name'] ?? null,
            'item_code' => $validated['item_code'] ?? null,
            'supplier' => $validated['supplier'] ?? null,
            'length' => $validated['length'] ?? null,
            'color' => $validated['color'] ?? null,
            'weight' => $validated['weight'] ?? null,
            'spk_id' => $validated['spk_id'] ?? null,
            'is_used' => array_key_exists('is_used', $validated)
                ? (int) $validated['is_used']
                : null,
            'is_reserved' => array_key_exists('is_reserved', $validated)
                ? (int) $validated['is_reserved']
                : null,
        ];
    }

    /**
     * @return array<string, int|string|null>
     */
    private function toListItem(TrKomponen $item): array
    {
        return [
            'id' => (int) $item->row_id,
            'jenisKomponen' => $item->jenis_komponen,
            'name' => $item->name,
            'itemCode' => $item->item_code,
            'supplier' => $item->supplier,
            'length' => $this->formatDecimal($item->length),
            'color' => $item->color,
            'weight' => $this->formatDecimal($item->weight),
            'spkId' => $item->spk_id,
            'productionNo' => filled($item->production?->spk_no)
                ? (string) $item->production->spk_no
                : null,
            'status' => $item->inventoryStatus(),
            'entryDate' => $item->entry_date?->format('Y-m-d'),
            'outDate' => $item->out_date?->format('Y-m-d'),
            'createdBy' => $item->created_by,
            'createdDate' => $item->created_date?->format('Y-m-d H:i:s'),
            'modifiedBy' => $item->modified_by,
            'modifiedDate' => $item->modified_date?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * @return array<string, int|string|null>
     */
    private function toDetailItem(TrKomponen $item): array
    {
        return $this->toListItem($item);
    }

    /**
     * @return array<string, int|string|null>
     */
    private function toFormItem(TrKomponen $item): array
    {
        return [
            'id' => (int) $item->row_id,
            'entry_date' => $item->entry_date?->format('Y-m-d'),
            'out_date' => $item->out_date?->format('Y-m-d'),
            'jenis_komponen' => $item->jenis_komponen,
            'name' => $item->name,
            'item_code' => $item->item_code,
            'supplier' => $item->supplier,
            'length' => $this->formatDecimal($item->length),
            'color' => $item->color,
            'weight' => $this->formatDecimal($item->weight),
            'spk_id' => $item->spk_id,
            'is_used' => $item->is_used,
            'is_reserved' => $item->is_reserved,
        ];
    }

    private function formatDecimal(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return number_format((float) $value, 2, '.', '');
    }

    private function actorName(Request $request): string
    {
        return $request->user()?->name ?? 'system';
    }
}
