<?php

namespace App\Support;

use App\Models\Production;

class SpkOrderReference
{
    /**
     * Label pesanan "DP-XXXXX (NAMA CUST)" untuk SPK bertipe Pesanan.
     *
     * Membutuhkan kolom spk_type, request_order_no dan customer_name ter-load.
     */
    public static function label(?Production $production): ?string
    {
        if ($production === null || $production->spk_type !== 'Pesanan') {
            return null;
        }

        $orderNo = trim((string) ($production->request_order_no ?? ''));

        if ($orderNo === '' || $orderNo === '-') {
            return null;
        }

        $customerName = trim((string) ($production->customer_name ?? ''));

        return $customerName !== '' ? "{$orderNo} ({$customerName})" : $orderNo;
    }
}
