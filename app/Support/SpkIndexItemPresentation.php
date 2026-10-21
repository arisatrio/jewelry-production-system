<?php

namespace App\Support;

use App\Models\Production;

class SpkIndexItemPresentation
{
    public function typeSkuLabel(Production $production): ?string
    {
        if (! $this->hasAssignedSku($production)) {
            return null;
        }

        $production->loadMissing(['categoryPrefix', 'sku']);

        $typeCode = trim((string) ($production->categoryPrefix?->prefix ?? ''));
        $skuCode = trim((string) ($production->sku?->sku_code ?? ''));

        $parts = array_values(array_filter(
            [$typeCode, $skuCode],
            fn (string $part): bool => $part !== '',
        ));

        return $parts !== [] ? implode(' | ', $parts) : null;
    }

    public function itemDescription(Production $production): ?string
    {
        if (! filled($production->description)) {
            return null;
        }

        $description = trim((string) $production->description);

        return $description !== '' ? $description : null;
    }

    public function hasAssignedSku(Production $production): bool
    {
        if (! filled($production->sku_id) || (int) $production->sku_id === 0) {
            return false;
        }

        $production->loadMissing('sku');

        return $production->sku !== null;
    }

    public function customerListLabel(Production $production): string
    {
        $customerName = filled($production->customer_name) ? (string) $production->customer_name : '';

        if ($production->spk_type !== 'Pesanan') {
            return $customerName;
        }

        $orderNo = filled($production->request_order_no) ? (string) $production->request_order_no : '';

        if ($orderNo === '') {
            return $customerName !== '' ? $customerName : '-';
        }

        return app(RequestOrderRepository::class)->displayLabelByDocNo(
            $orderNo,
            $customerName !== '' ? $customerName : null,
        );
    }
}
