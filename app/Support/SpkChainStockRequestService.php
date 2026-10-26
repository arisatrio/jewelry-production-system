<?php

namespace App\Support;

use App\Models\Production;
use App\Models\SpkChain;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class SpkChainStockRequestService
{
    public const TAB_PENDING = 'pending';

    public const TAB_FULFILLED = 'fulfilled';

    public const TABS = [self::TAB_PENDING, self::TAB_FULFILLED];

    public function tableAvailable(): bool
    {
        return Schema::connection('third')->hasTable('spkchains');
    }

    public function pendingCount(): int
    {
        if (! $this->tableAvailable()) {
            return 0;
        }

        return $this->baseQuery(self::TAB_PENDING)->count();
    }

    /**
     * @return array{
     *     data: list<array{
     *         lineId: int,
     *         spkId: int,
     *         spkNo: string,
     *         spkStatus: string|null,
     *         chainCategory: string|null,
     *         chainItem: string|null,
     *         chainColor: string|null,
     *         chainSize: string|null,
     *         msChainId: int|null,
     *         masterItemName: string|null,
     *         masterItemCode: string|null,
     *         createdDate: string|null
     *     }>,
     *     meta: array{
     *         currentPage: int,
     *         lastPage: int,
     *         perPage: int,
     *         total: int,
     *         tabCounts: array{pending: int, fulfilled: int}
     *     }
     * }
     */
    public function paginate(
        string $tab = self::TAB_PENDING,
        string $search = '',
        int $page = 1,
        int $perPage = 25,
    ): array {
        if (! $this->tableAvailable()) {
            return [
                'data' => [],
                'meta' => [
                    'currentPage' => 1,
                    'lastPage' => 1,
                    'perPage' => $perPage,
                    'total' => 0,
                    'tabCounts' => [
                        self::TAB_PENDING => 0,
                        self::TAB_FULFILLED => 0,
                    ],
                ],
            ];
        }

        $tab = in_array($tab, self::TABS, true) ? $tab : self::TAB_PENDING;
        $page = max(1, $page);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 25;

        $query = $this->baseQuery($tab);

        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search): void {
                $builder->whereHas('production', function (Builder $production) use ($search): void {
                    $production->notDeleted()
                        ->where(function (Builder $inner) use ($search): void {
                            $inner->where('spk_no', 'like', "%{$search}%")
                                ->orWhere('item', 'like', "%{$search}%")
                                ->orWhere('customer_name', 'like', "%{$search}%");
                        });
                })
                    ->orWhere('category', 'like', "%{$search}%")
                    ->orWhere('item', 'like', "%{$search}%")
                    ->orWhere('color', 'like', "%{$search}%")
                    ->orWhereHas('msChain', function (Builder $msChain) use ($search): void {
                        $msChain->notDeleted()
                            ->where(function (Builder $inner) use ($search): void {
                                $inner->where('item_name', 'like', "%{$search}%")
                                    ->orWhere('item_code', 'like', "%{$search}%")
                                    ->orWhere('item', 'like', "%{$search}%");
                            });
                    });
            });
        }

        $paginator = $query
            ->with(['production', 'msChain'])
            ->orderByDesc('line_id')
            ->paginate($perPage, ['*'], 'page', $page);

        return [
            'data' => $paginator->getCollection()
                ->map(fn (SpkChain $chain): array => $this->toRow($chain))
                ->values()
                ->all(),
            'meta' => [
                'currentPage' => $paginator->currentPage(),
                'lastPage' => $paginator->lastPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
                'tabCounts' => [
                    self::TAB_PENDING => $this->baseQuery(self::TAB_PENDING)->count(),
                    self::TAB_FULFILLED => $this->baseQuery(self::TAB_FULFILLED)->count(),
                ],
            ],
        ];
    }

    /**
     * @return Builder<SpkChain>
     */
    private function baseQuery(string $tab): Builder
    {
        $query = SpkChain::query()
            ->notDeleted()
            ->whereHas('production', fn (Builder $production): Builder => $production->notDeleted());

        if ($tab === self::TAB_FULFILLED) {
            $query->whereNotNull('mschain_id')
                ->whereExists(function ($subquery): void {
                    $subquery->selectRaw('1')
                        ->from('trxchain')
                        ->whereColumn('trxchain.spk_id', 'spkchains.row_id')
                        ->whereColumn('trxchain.mschain_id', 'spkchains.mschain_id')
                        ->where('trxchain.is_deleted', 0)
                        ->whereNotNull('trxchain.out_date');
                });

            return $query;
        }

        $query->where(function (Builder $builder): void {
            $builder->whereNull('mschain_id')
                ->orWhereNotExists(function ($subquery): void {
                    $subquery->selectRaw('1')
                        ->from('trxchain')
                        ->whereColumn('trxchain.spk_id', 'spkchains.row_id')
                        ->whereColumn('trxchain.mschain_id', 'spkchains.mschain_id')
                        ->where('trxchain.is_deleted', 0)
                        ->whereNotNull('trxchain.out_date');
                });
        });

        return $query;
    }

    /**
     * @return array{
     *     lineId: int,
     *     spkId: int,
     *     spkNo: string,
     *     spkStatus: string|null,
     *     chainCategory: string|null,
     *     chainItem: string|null,
     *     chainColor: string|null,
     *     chainSize: string|null,
     *     msChainId: int|null,
     *     masterItemName: string|null,
     *     masterItemCode: string|null,
     *     createdDate: string|null
     * }
     */
    private function toRow(SpkChain $chain): array
    {
        /** @var Production|null $production */
        $production = $chain->production;

        return [
            'lineId' => (int) $chain->line_id,
            'spkId' => (int) $chain->row_id,
            'spkNo' => (string) ($production?->spk_no ?? '—'),
            'spkStatus' => $production?->status,
            'chainCategory' => $chain->category,
            'chainItem' => $chain->item,
            'chainColor' => $chain->color,
            'chainSize' => $chain->size,
            'msChainId' => $chain->mschain_id !== null ? (int) $chain->mschain_id : null,
            'masterItemName' => $chain->msChain?->item_name ?? $chain->msChain?->item,
            'masterItemCode' => $chain->msChain?->item_code,
            'createdDate' => $chain->created_date?->format('Y-m-d'),
        ];
    }
}
