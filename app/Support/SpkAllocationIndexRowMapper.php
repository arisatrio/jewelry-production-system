<?php

namespace App\Support;

use App\Models\Production;
use Illuminate\Support\Collection;
use stdClass;

class SpkAllocationIndexRowMapper
{
    public function __construct(
        private SpkIndexItemPresentation $itemPresentation,
        private SpkProductionStoneList $stoneList,
        private RequestOrderRepository $requestOrders,
    ) {}

    /**
     * @param  Collection<int, Production>  $pageProductions
     * @return array<string, stdClass>
     */
    public function requestOrdersByDocNo(Collection $pageProductions): array
    {
        return $this->requestOrders->rowsByDocNos(array_values(
            $pageProductions
                ->where('spk_type', 'Pesanan')
                ->pluck('request_order_no')
                ->map(fn (mixed $docNo): string => trim((string) $docNo))
                ->all(),
        ));
    }

    /**
     * @param  array{stoneLines: int, diamondLines: int}  $summary
     * @return array<string, mixed>
     */
    public function map(
        Production $production,
        ?stdClass $requestOrder,
        array $summary,
        bool $hasAllocation,
    ): array {
        $orderReference = null;
        $paymentStatus = null;
        $orderType = null;

        if (SpkOrderReference::label($production) !== null) {
            $customerName = filled($production->customer_name)
                ? (string) $production->customer_name
                : (filled($requestOrder?->customer_name) ? (string) $requestOrder->customer_name : '-');

            $orderReference = $this->requestOrders->pesananDisplayLabel(
                trim((string) $production->request_order_no),
                $customerName,
            );
            $paymentStatus = $this->requestOrders->paymentStatusLabel($requestOrder?->is_fully_paid);
            $orderType = $this->requestOrders->typeOrderLabel($requestOrder?->type_order);
        }

        return [
            'rowId' => (int) $production->row_id,
            'produksiNo' => (string) $production->spk_no,
            'tipeProduksi' => $production->spk_type ?? '-',
            'customer' => $this->itemPresentation->customerListLabel($production),
            'orderReference' => $orderReference,
            'requestStockNo' => filled($production->request_stock_no)
                ? (string) $production->request_stock_no
                : null,
            'requestStockDrkNo' => filled($production->request_stock_drk_no)
                ? (string) $production->request_stock_drk_no
                : null,
            'paymentStatus' => $paymentStatus,
            'orderType' => $orderType,
            'typeSkuLabel' => $this->itemPresentation->typeSkuLabel($production),
            'itemDescription' => $this->itemPresentation->itemDescription($production),
            'skuAssigned' => $this->itemPresentation->hasAssignedSku($production),
            'spkImageUrl' => SpkItemImageUrl::fromFileName($production->file_name),
            'stoneSkuLines' => $this->stoneList->indexDisplayLines($production),
            'hasAllocation' => $hasAllocation,
            'stoneLineCount' => $summary['stoneLines'],
            'diamondLineCount' => $summary['diamondLines'],
        ];
    }
}
