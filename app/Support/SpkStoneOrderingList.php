<?php

namespace App\Support;

use App\Models\MsShape;
use App\Models\Production;
use App\Models\SpkStone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Schema;

class SpkStoneOrderingList
{
    public function listAvailable(): bool
    {
        $schema = Schema::connection('third');

        return $schema->hasTable('spkstone')
            && $schema->hasColumn('spkstone', 'fulfillment_status');
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
    public function paginate(string $search = '', int $perPage = 25, int $page = 1): array
    {
        if (! $this->listAvailable()) {
            return $this->emptyPaginator($perPage);
        }

        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 25;
        $page = max(1, $page);
        $search = trim($search);

        $query = SpkStone::query()
            ->notDeleted()
            ->whereIn('fulfillment_status', SpkStoneFulfillment::purchaseStatuses())
            ->whereHas('production', fn (Builder $production): Builder => $production->notDeleted());

        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search): void {
                $builder->whereHas('production', function (Builder $production) use ($search): void {
                    $production->notDeleted()
                        ->where(function (Builder $inner) use ($search): void {
                            $inner->where('spk_no', 'like', "%{$search}%")
                                ->orWhere('customer_name', 'like', "%{$search}%");

                            if (Schema::connection('third')->hasColumn('spk', 'item_name')) {
                                $inner->orWhere('item_name', 'like', "%{$search}%");
                            }
                        });
                })
                    ->orWhere('size', 'like', "%{$search}%")
                    ->orWhereHas('shape', function (Builder $shape) use ($search): void {
                        $shape->where(function (Builder $inner) use ($search): void {
                            $inner->where('name', 'like', "%{$search}%")
                                ->orWhere('code', 'like', "%{$search}%");
                        });
                    })
                    ->orWhereHas('position', function (Builder $position) use ($search): void {
                        $position->where('nama', 'like', "%{$search}%");
                    });
            });
        }

        /** @var LengthAwarePaginator<int, SpkStone> $paginator */
        $paginator = $query
            ->with(['production.sku', 'production.categoryPrefix', 'shape'])
            ->orderByDesc('modified_date')
            ->orderByDesc('line_id')
            ->paginate($perPage, ['*'], 'page', $page);

        return [
            'data' => $paginator->getCollection()
                ->map(fn (SpkStone $stone): array => $this->toRow($stone))
                ->values()
                ->all(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ];
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
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 25;

        return [
            'data' => [],
            'current_page' => 1,
            'last_page' => 1,
            'per_page' => $perPage,
            'total' => 0,
        ];
    }

    /**
     * @return array{
     *     lineId: int,
     *     spkId: int,
     *     spkNo: string,
     *     spkImageUrl: string|null,
     *     typeSkuLabel: string|null,
     *     itemDescription: string|null,
     *     skuAssigned: bool,
     *     stoneType: string,
     *     stoneTypeLabel: string,
     *     microStoneId: int|null,
     *     shapeName: string|null,
     *     size: string|null,
     *     pcs: int,
     *     caratPerPcs: string,
     *     totalCarat: string,
     *     fulfillmentStatus: string,
     *     fulfillmentLabel: string,
     *     orderedAt: string|null
     * }
     */
    private function toRow(SpkStone $stone): array
    {
        /** @var Production|null $production */
        $production = $stone->production;

        $pcs = (int) ($stone->pcs ?? 0);
        $totalCarat = (float) ($stone->carat ?? 0);
        $caratPerPcs = $pcs > 0 ? round($totalCarat / $pcs, 3) : 0.0;
        $fulfillmentStatus = in_array(
            (string) ($stone->fulfillment_status ?? ''),
            SpkStoneFulfillment::purchaseStatuses(),
            true,
        )
            ? (string) $stone->fulfillment_status
            : SpkStoneFulfillment::ORDERING;

        $stoneType = SpkStoneStockChecker::preferredSourceForStone($stone);
        $microStoneId = $stoneType === SpkStoneStockChecker::SOURCE_MICRO
            ? app(SpkStoneStockChecker::class)->primaryMicroStoneIdForRestock($stone)
            : null;

        $itemPresentation = $production !== null
            ? $this->listItemPresentation($production)
            : [
                'spkImageUrl' => null,
                'typeSkuLabel' => null,
                'itemDescription' => null,
                'skuAssigned' => false,
            ];

        return [
            'lineId' => (int) $stone->line_id,
            'spkId' => (int) $stone->row_id,
            'spkNo' => (string) ($production?->spk_no ?? '—'),
            ...$itemPresentation,
            'stoneType' => $stoneType,
            'stoneTypeLabel' => SpkStoneStockChecker::preferredSourceLabelForStone($stone),
            'microStoneId' => $microStoneId,
            'shapeName' => $this->shapeDisplayName($stone->shape),
            'size' => filled($stone->size) ? (string) $stone->size : null,
            'pcs' => $pcs,
            'caratPerPcs' => number_format($caratPerPcs, 3, '.', ''),
            'totalCarat' => number_format($totalCarat, 3, '.', ''),
            'fulfillmentStatus' => $fulfillmentStatus,
            'fulfillmentLabel' => SpkStoneFulfillment::label($fulfillmentStatus),
            'orderedAt' => $stone->modified_date?->format('Y-m-d H:i'),
        ];
    }

    /**
     * @return array{
     *     spkImageUrl: string|null,
     *     typeSkuLabel: string|null,
     *     itemDescription: string|null,
     *     skuAssigned: bool
     * }
     */
    private function listItemPresentation(Production $production): array
    {
        $production->loadMissing(['categoryPrefix', 'sku']);

        $skuAssigned = filled($production->sku_id)
            && (int) $production->sku_id !== 0
            && $production->sku !== null;

        $typeSkuLabel = null;

        if ($skuAssigned) {
            $typeCode = trim((string) ($production->categoryPrefix?->prefix ?? ''));
            $skuCode = trim((string) ($production->sku?->sku_code ?? ''));
            $parts = array_values(array_filter(
                [$typeCode, $skuCode],
                fn (string $part): bool => $part !== '',
            ));
            $typeSkuLabel = $parts !== [] ? implode(' | ', $parts) : null;
        }

        $itemDescription = null;

        if (filled($production->description)) {
            $description = trim((string) $production->description);

            $itemDescription = $description !== '' ? $description : null;
        }

        return [
            'spkImageUrl' => SpkItemImageUrl::fromFileName($production->file_name),
            'typeSkuLabel' => $typeSkuLabel,
            'itemDescription' => $itemDescription,
            'skuAssigned' => $skuAssigned,
        ];
    }

    private function shapeDisplayName(?MsShape $shape): ?string
    {
        if ($shape === null) {
            return null;
        }

        $name = trim((string) ($shape->name ?? ''));

        if ($name !== '') {
            return $name;
        }

        $code = trim((string) ($shape->code ?? ''));

        if ($code !== '') {
            return $code;
        }

        return 'Shape #'.$shape->row_id;
    }
}
