<?php

namespace App\Http\Controllers;

use App\Support\StoneLedger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MicroStoneController extends Controller
{
    public function index(Request $request, StoneLedger $ledger): Response
    {
        $filters = Validator::make($request->only([
            'search',
            'stock_status',
            'per_page',
            'sort',
            'direction',
        ]), [
            'search' => ['nullable', 'string', 'max:100'],
            'stock_status' => ['nullable', Rule::in([
                StoneLedger::STOCK_STATUS_AVAILABLE,
                StoneLedger::STOCK_STATUS_EMPTY,
            ])],
            'per_page' => ['nullable', 'integer'],
            'sort' => ['nullable', Rule::in(StoneLedger::stockSorts())],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ])->validate();

        $search = trim((string) ($filters['search'] ?? ''));
        $stockStatus = filled($filters['stock_status'] ?? null)
            ? (string) $filters['stock_status']
            : null;
        $perPage = (int) ($filters['per_page'] ?? 50);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 50;
        $sort = StoneLedger::resolveSort(
            (string) ($filters['sort'] ?? StoneLedger::DEFAULT_STOCK_SORT),
            StoneLedger::stockSorts(),
            StoneLedger::DEFAULT_STOCK_SORT,
        );
        $direction = StoneLedger::resolveDirection(
            (string) ($filters['direction'] ?? StoneLedger::DEFAULT_STOCK_DIRECTION),
        );
        $activePeriod = $ledger->activePeriod();

        $addStockStoneId = filled($request->query('stone_id'))
            ? (int) $request->query('stone_id')
            : 0;
        $openAddStockModal = $request->query('add_stock') === '1'
            && $addStockStoneId > 0
            && $activePeriod !== null;

        $addStockStone = null;

        if ($openAddStockModal) {
            $rows = $ledger->stockByStoneIds($activePeriod['id'], [$addStockStoneId]);
            $addStockStone = $rows[0] ?? null;
        }

        return Inertia::render('inventory/batu-mikro/index', [
            'stones' => $activePeriod === null
                ? $this->emptyPaginator($perPage)
                : $ledger->paginateStock(
                    $activePeriod['id'],
                    $search,
                    $stockStatus,
                    $perPage,
                    $sort,
                    $direction,
                ),
            'totals' => $activePeriod === null
                ? ['pcs' => '0', 'crt' => '0.0000']
                : $ledger->stockTotals($activePeriod['id'], $search, $stockStatus),
            'statusSummaries' => $activePeriod === null
                ? [
                    'balance' => [
                        'stoneCount' => 0,
                        'pcs' => '0',
                        'crt' => '0.0000',
                        'reservedPcs' => '0',
                    ],
                    'available' => [
                        'stoneCount' => 0,
                        'pcs' => '0',
                        'crt' => '0.0000',
                        'reservedPcs' => '0',
                    ],
                    'empty' => [
                        'stoneCount' => 0,
                        'pcs' => '0',
                        'crt' => '0.0000',
                        'reservedPcs' => '0',
                    ],
                ]
                : $ledger->stockSummaries($activePeriod['id'], $search),
            'activePeriod' => $activePeriod,
            'addStockStone' => $addStockStone,
            'filters' => [
                'search' => $search,
                'stock_status' => $stockStatus,
                'per_page' => $perPage,
                'sort' => $sort,
                'direction' => $direction,
            ],
            'filterOptions' => [
                'sort' => StoneLedger::stockSortOptions(),
                'direction' => StoneLedger::directionSortOptions(),
            ],
        ]);
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
        return [
            'data' => [],
            'current_page' => 1,
            'last_page' => 1,
            'per_page' => $perPage,
            'total' => 0,
        ];
    }
}
