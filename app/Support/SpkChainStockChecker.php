<?php

namespace App\Support;

use App\Models\SpkChain;
use Illuminate\Support\Collection;

class SpkChainStockChecker
{
    public const STATUS_AVAILABLE = 'available';

    public const STATUS_UNAVAILABLE = 'unavailable';

    private const REQUIRED_UNITS = 1;

    public function __construct(
        private readonly ChainStockService $chainStock,
        private readonly SpkChainFulfillment $fulfillment,
    ) {}

    /**
     * Status stok per baris rantai SPK, dikunci dengan line_id.
     *
     * @param  Collection<int, SpkChain>  $chains
     * @return array<int, array{
     *     status: 'available'|'unavailable',
     *     requiredUnits: int,
     *     availableUnits: int,
     *     balanceLength: string|null,
     *     note: string|null
     * }>
     */
    public function forChains(Collection $chains): array
    {
        if ($chains->isEmpty()) {
            return [];
        }

        $msChainIds = $chains
            ->pluck('mschain_id')
            ->filter(fn ($id): bool => $id !== null)
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        $countsByMsChain = $this->chainStock->countsByMsChain($msChainIds);

        $results = [];

        foreach ($chains as $chain) {
            $results[(int) $chain->line_id] = $this->forChain($chain, $countsByMsChain);
        }

        return $results;
    }

    /**
     * Status alokasi & fulfillment rantai per baris SPK.
     *
     * @param  Collection<int, SpkChain>  $chains
     * @return array<int, array{
     *     status: string,
     *     label: string,
     *     allocatedUnits: int,
     *     nextAction: string|null,
     *     nextActionLabel: string|null
     * }>
     */
    public function allocationForChains(Collection $chains): array
    {
        if ($chains->isEmpty()) {
            return [];
        }

        $results = [];

        foreach ($chains as $chain) {
            $results[(int) $chain->line_id] = $this->fulfillment->resolve($chain);
        }

        return $results;
    }

    /**
     * @param  array<int, array{stockIn: int, stockOut: int, balance: int, balanceLength: string}>  $countsByMsChain
     * @return array{
     *     status: 'available'|'unavailable',
     *     requiredUnits: int,
     *     availableUnits: int,
     *     balanceLength: string|null,
     *     note: string|null
     * }
     */
    private function forChain(SpkChain $chain, array $countsByMsChain): array
    {
        if ($chain->mschain_id === null) {
            return $this->result(
                self::REQUIRED_UNITS,
                0,
                null,
                'Master chain belum terhubung',
            );
        }

        $counts = $countsByMsChain[(int) $chain->mschain_id] ?? [
            'balance' => 0,
            'balanceLength' => '0.00',
        ];

        $availableUnits = (int) ($counts['balance'] ?? 0);
        $balanceLength = (string) ($counts['balanceLength'] ?? '0.00');

        return $this->result(
            self::REQUIRED_UNITS,
            $availableUnits,
            $balanceLength,
            null,
        );
    }

    /**
     * @return array{
     *     status: 'available'|'unavailable',
     *     requiredUnits: int,
     *     availableUnits: int,
     *     balanceLength: string|null,
     *     note: string|null
     * }
     */
    private function result(
        int $requiredUnits,
        int $availableUnits,
        ?string $balanceLength,
        ?string $note,
    ): array {
        $isAvailable = $requiredUnits > 0 && $availableUnits >= $requiredUnits;

        return [
            'status' => $isAvailable ? self::STATUS_AVAILABLE : self::STATUS_UNAVAILABLE,
            'requiredUnits' => $requiredUnits,
            'availableUnits' => $availableUnits,
            'balanceLength' => $balanceLength,
            'note' => $note,
        ];
    }
}
