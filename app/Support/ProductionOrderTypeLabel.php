<?php

namespace App\Support;

use App\Models\Production;

class ProductionOrderTypeLabel
{
    public function __construct(
        private RequestOrderRepository $requestOrders,
    ) {}

    public function forProduction(?Production $production): string
    {
        if ($production === null) {
            return '—';
        }

        $customerName = filled($production->customer_name)
            ? (string) $production->customer_name
            : '';

        if ($production->spk_type !== 'Pesanan') {
            return $customerName !== '' ? $customerName : '—';
        }

        $orderNo = filled($production->request_order_no)
            ? (string) $production->request_order_no
            : '';

        if ($orderNo === '') {
            return $customerName !== '' ? $customerName : '—';
        }

        return $this->requestOrders->displayLabelByDocNo(
            $orderNo,
            $customerName !== '' ? $customerName : null,
        );
    }
}
