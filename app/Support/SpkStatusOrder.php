<?php

namespace App\Support;

use App\Models\Production;
use App\Models\SkuMaster;
use Illuminate\Database\Eloquent\Builder;

class SpkStatusOrder
{
    public const NEW_ORDER = 'NO';

    public const REPEAT_ORDER = 'RO';

    public function code(?int $skuId, ?int $exceptRowId = null): ?string
    {
        if ($skuId === null || $skuId <= 0) {
            return null;
        }

        return $this->existingCount($skuId, $exceptRowId) === 0
            ? self::NEW_ORDER
            : self::REPEAT_ORDER;
    }

    public function displayLabel(?int $skuId, ?int $productionRowId = null): string
    {
        if ($skuId === null || $skuId <= 0) {
            return '-';
        }

        $sequence = $this->sequenceNumber($skuId, $productionRowId);

        if ($sequence <= 1) {
            return 'New Order';
        }

        return sprintf('Repeat Order %03d', $sequence);
    }

    public function sequenceNumber(?int $skuId, ?int $productionRowId = null): int
    {
        if ($skuId === null || $skuId <= 0) {
            return 0;
        }

        if ($productionRowId !== null && $productionRowId > 0) {
            return $this->query($skuId)
                ->where('row_id', '<=', $productionRowId)
                ->count();
        }

        return $this->existingCount($skuId) + 1;
    }

    public function existingCount(?int $skuId, ?int $exceptRowId = null): int
    {
        if ($skuId === null || $skuId <= 0) {
            return 0;
        }

        $query = $this->query($skuId);

        if ($exceptRowId !== null && $exceptRowId > 0) {
            $query->where('row_id', '<>', $exceptRowId);
        }

        return (int) $query->count();
    }

    /**
     * @return list<int>
     */
    public function relatedSkuIds(int $skuId): array
    {
        $sku = SkuMaster::query()->find($skuId);
        $skuIds = [$skuId];

        if ($sku === null) {
            return $skuIds;
        }

        return array_values(array_unique([
            ...$skuIds,
            ...SkuMaster::idsSharingIdentity($sku->sku_code),
        ]));
    }

    /**
     * @return Builder<Production>
     */
    private function query(int $skuId): Builder
    {
        return Production::query()
            ->notDeleted()
            ->whereIn('sku_id', $this->relatedSkuIds($skuId));
    }
}
