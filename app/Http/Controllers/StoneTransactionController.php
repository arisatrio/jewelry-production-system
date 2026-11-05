<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStoneTransactionRequest;
use App\Support\StoneLedger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class StoneTransactionController extends Controller
{
    public function index(Request $request, StoneLedger $ledger): Response
    {
        $filters = Validator::make($request->only([
            'search',
            'date_from',
            'date_to',
            'event_type',
            'per_page',
            'sort',
            'direction',
        ]), [
            'search' => ['nullable', 'string', 'max:100'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'event_type' => ['nullable', Rule::in([
                StoneLedger::TRANSACTION_EVENT_ENTRY,
                StoneLedger::TRANSACTION_EVENT_OUT,
            ])],
            'per_page' => ['nullable', 'integer'],
            'sort' => ['nullable', Rule::in(StoneLedger::transactionSorts())],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ])->validate();

        $search = trim((string) ($filters['search'] ?? ''));
        $dateFrom = filled($filters['date_from'] ?? null)
            ? (string) $filters['date_from']
            : null;
        $dateTo = filled($filters['date_to'] ?? null)
            ? (string) $filters['date_to']
            : null;
        $eventType = filled($filters['event_type'] ?? null)
            ? (string) $filters['event_type']
            : null;
        $perPage = (int) ($filters['per_page'] ?? 50);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 50;
        $sort = StoneLedger::resolveSort(
            (string) ($filters['sort'] ?? StoneLedger::DEFAULT_TRANSACTION_SORT),
            StoneLedger::transactionSorts(),
            StoneLedger::DEFAULT_TRANSACTION_SORT,
        );
        $direction = StoneLedger::resolveDirection(
            (string) ($filters['direction'] ?? StoneLedger::DEFAULT_TRANSACTION_DIRECTION),
        );
        $activePeriod = $ledger->activePeriod();

        return Inertia::render('inventory/transaksi-batu/index', [
            'transactions' => $activePeriod === null
                ? $this->emptyPaginator($perPage)
                : $ledger->paginate(
                    $activePeriod['id'],
                    $search,
                    $dateFrom,
                    $dateTo,
                    $eventType,
                    $perPage,
                    $sort,
                    $direction,
                ),
            'total' => $activePeriod === null
                ? '0'
                : $ledger->total(
                    $activePeriod['id'],
                    $search,
                    $dateFrom,
                    $dateTo,
                    $eventType,
                ),
            'activePeriod' => $activePeriod,
            'stoneOptions' => $ledger->stoneOptions($activePeriod['id'] ?? null),
            'filters' => [
                'search' => $search,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'event_type' => $eventType,
                'per_page' => $perPage,
                'sort' => $sort,
                'direction' => $direction,
            ],
            'filterOptions' => [
                'sort' => StoneLedger::transactionSortOptions(),
                'direction' => StoneLedger::directionSortOptions(),
            ],
        ]);
    }

    public function store(
        StoreStoneTransactionRequest $request,
        StoneLedger $ledger,
    ): RedirectResponse {
        $activePeriod = $ledger->activePeriod();

        if ($activePeriod === null) {
            throw ValidationException::withMessages([
                'period' => 'Periode batu aktif tidak tersedia.',
            ]);
        }

        $validated = $request->validated();

        $ledger->store(
            $activePeriod['id'],
            (string) $validated['transaction_kind'],
            (int) $validated['stone_id'],
            (string) $validated['pcs'],
            (string) $validated['crt'],
            filled($validated['notes'] ?? null) ? (string) $validated['notes'] : null,
            $request->user()?->name ?? 'system',
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $validated['transaction_kind'] === 'addition'
                ? 'Penambahan batu berhasil disimpan.'
                : 'Pengurangan batu berhasil disimpan.',
        ]);

        return back();
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
