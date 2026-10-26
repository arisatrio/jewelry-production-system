<?php

namespace App\Http\Controllers;

use App\Support\ChainStockService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TrxChainController extends Controller
{
    /**
     * Display a paginated log of all trxchain stock units.
     */
    public function index(Request $request, ChainStockService $stock): Response
    {
        $search = $request->string('search')->trim()->toString();
        $perPage = $request->integer('per_page', 50);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 50;

        return Inertia::render('inventory/chain/transactions/index', [
            'transactions' => $stock->tableAvailable()
                ? $stock->paginateAllUnits($search, $perPage)
                : $this->emptyPaginator($perPage),
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
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
