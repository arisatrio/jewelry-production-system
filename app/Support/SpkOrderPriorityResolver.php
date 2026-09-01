<?php

namespace App\Support;

class SpkOrderPriorityResolver
{
    public const LEVEL_HIGH = 'high';

    public const LEVEL_MEDIUM = 'medium';

    public const LEVEL_LOW = 'low';

    public function __construct(private RequestOrderRepository $requestOrders) {}

    /**
     * @return array{level: string, label: string}|null
     */
    public function resolve(?string $spkType, ?string $requestOrderNo): ?array
    {
        if ($spkType !== 'Pesanan' || blank($requestOrderNo)) {
            return null;
        }

        $context = $this->requestOrders->priorityContextByDocNo((string) $requestOrderNo);

        if ($context === null) {
            return null;
        }

        if ($this->isNabungBareng($context['typeOrder'])) {
            return [
                'level' => self::LEVEL_LOW,
                'label' => 'Prioritas Rendah',
            ];
        }

        return match ($context['isFullyPaid']) {
            true => [
                'level' => self::LEVEL_HIGH,
                'label' => 'Prioritas Tinggi',
            ],
            false => [
                'level' => self::LEVEL_MEDIUM,
                'label' => 'Prioritas Sedang',
            ],
            default => null,
        };
    }

    private function isNabungBareng(?string $typeOrder): bool
    {
        return strcasecmp(trim((string) $typeOrder), 'Nabung Bareng') === 0;
    }
}
