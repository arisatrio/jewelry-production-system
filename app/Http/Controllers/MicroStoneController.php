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
        ]), [
            'search' => ['nullable', 'string', 'max:100'],
            'stock_status' => ['nullable', Rule::in([
                StoneLedger::STOCK_STATUS_AVAILABLE,
                StoneLedger::STOCK_STATUS_EMPTY,
            ])],
            'per_page' => ['nullable', 'integer'],
        ])->validate();

        $search = trim((string) ($filters['search'] ?? ''));
        $stockStatus = filled($filters['stock_status'] ?? null)
            ? (string) $filters['stock_status']
            : null;
        $perPage = (int) ($filters['per_page'] ?? 10);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;
        $activePeriod = $ledger->activePeriod();

        return Inertia::render('inventory/batu-mikro/index', [
            'stones' => $activePeriod === null
                ? $this->emptyPaginator($perPage)
                : $ledger->paginateStock(
                    $activePeriod['id'],
                    $search,
                    $stockStatus,
                    $perPage,
                ),
            'totals' => $activePeriod === null
                ? ['pcs' => '0', 'crt' => '0.0000']
                : $ledger->stockTotals($activePeriod['id'], $search, $stockStatus),
            'activePeriod' => $activePeriod,
            'filters' => [
                'search' => $search,
                'stock_status' => $stockStatus,
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
